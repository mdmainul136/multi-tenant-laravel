# 📦 Multi-Tenant Platform — Core Codebase Archive

> Full source code with syntax highlighting for every model, middleware, controller, service, and trait.

---

## 📋 Table of Contents

| Section | Files |
|---|---|
| [🗄️ Models](#models) | Tenant, User, Module, TenantModule, Invoice, Payment, PaymentMethod, TenantBaseModel |
| [🎭 Traits](#traits) | TenantAware |
| [🛡️ Middlewares](#middlewares) | IdentifyTenant, AuthenticateToken, CheckModuleAccess, EnforceDatabaseQuota, DynamicCors |
| [🎮 Controllers](#controllers) | Auth, Tenant, User, Subscription, Payment, Invoice, PaymentMethod, PaymentHistory, TenantDatabase, SuperAdmin, SuperAdminAuth, ModuleManagement |
| [⚙️ Services](#services) | DatabaseManager, TenantService, ModuleService, ModuleMigrationManager, TenantDatabaseIsolationService, TenantDatabaseAnalyticsService, AppServiceProvider |

---

## 🗄️ Models {#models}

### `app/Models/Tenant.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $connection = 'mysql'; // Master DB

    protected $fillable = [
        'tenant_id', 'tenant_name', 'name', 'company_name', 'business_type',
        'admin_name', 'database_name', 'email', 'admin_email', 'domain',
        'phone', 'address', 'city', 'country', 'status', 'provisioning_status',
        'database_plan_id', 'db_username', 'db_password_encrypted',
    ];

    protected $hidden = ['db_password_encrypted'];

    protected function casts(): array
    {
        return [
            'created_at'           => 'datetime',
            'updated_at'           => 'datetime',
            'db_password_encrypted'=> 'encrypted', // AES-256-CBC
        ];
    }

    // ── Scopes ─────────────────────────────────────────────────────────────
    public function scopeActive($query) { return $query->where('status', 'active'); }

    // ── Static Helpers ──────────────────────────────────────────────────────
    public static function generateDatabaseName(string $tenantId): string
    {
        return config('tenant.database_prefix', 'tenant_') . $tenantId;
    }

    // ── Relationships ───────────────────────────────────────────────────────
    public function databasePlan()    { return $this->belongsTo(TenantDatabasePlan::class, 'database_plan_id'); }
    public function databaseStats()   { return $this->hasMany(TenantDatabaseStat::class)->orderByDesc('recorded_at'); }
    public function latestDatabaseStat() { return $this->hasOne(TenantDatabaseStat::class)->latestOfMany('recorded_at'); }
    public function tenantModules()   { return $this->hasMany(TenantModule::class); }
}
```

---

### `app/Models/User.php`

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    // Connection is switched dynamically to 'tenant_dynamic' by IdentifyTenant middleware

    protected $fillable = ['name', 'email', 'password', 'role', 'status'];
    protected $hidden   = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }
}
```

---

### `app/Models/Module.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $connection = 'mysql';

    protected $fillable = ['module_key', 'module_name', 'description', 'version', 'is_active', 'price'];

    protected $casts = ['is_active' => 'boolean', 'price' => 'decimal:2'];

    public function tenantModules()      { return $this->hasMany(TenantModule::class); }
    public function activeSubscriptions(){ return $this->hasMany(TenantModule::class)->where('status', 'active'); }
    public function scopeActive($query)  { return $query->where('is_active', true); }
}
```

---

### `app/Models/TenantModule.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantModule extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id', 'module_id', 'module_version',
        'subscription_type', 'status', 'subscribed_at', 'expires_at',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'expires_at'    => 'datetime',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function module() { return $this->belongsTo(Module::class); }

    public function isActive(): bool
    {
        if ($this->status !== 'active') return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        return true;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
```

---

### `app/Models/Invoice.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id', 'payment_id', 'module_id', 'invoice_number',
        'invoice_date', 'due_date', 'subscription_type',
        'subtotal', 'tax', 'discount', 'total', 'status', 'notes', 'metadata',
    ];

    protected $casts = [
        'invoice_date' => 'date', 'due_date' => 'date',
        'subtotal' => 'decimal:2', 'tax' => 'decimal:2',
        'discount' => 'decimal:2', 'total' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function tenant()  { return $this->belongsTo(Tenant::class); }
    public function payment() { return $this->belongsTo(Payment::class); }
    public function module()  { return $this->belongsTo(Module::class); }

    public static function generateInvoiceNumber()
    {
        $prefix = 'INV-' . now()->format('Ym') . '-';
        $last = self::where('invoice_number', 'like', "{$prefix}%")->orderBy('invoice_number', 'desc')->first();
        $next = $last ? ((int) substr($last->invoice_number, -5)) + 1 : 1;
        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    public function markAsPaid()        { $this->update(['status' => 'paid']); }
    public function markAsCancelled()   { $this->update(['status' => 'cancelled']); }
    public function isPaid(): bool      { return $this->status === 'paid'; }
    public function isOverdue(): bool   { return $this->status === 'pending' && $this->due_date?->isPast(); }

    public function scopePaid($q)               { return $q->where('status', 'paid'); }
    public function scopePending($q)            { return $q->where('status', 'pending'); }
    public function scopeForTenant($q, $tid)    { return $q->where('tenant_id', $tid); }
}
```

---

### `app/Models/Payment.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id', 'module_id', 'amount', 'currency',
        'payment_method', 'payment_status', 'transaction_id',
        'stripe_session_id', 'stripe_payment_intent_id',
        'payment_gateway_response', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_gateway_response' => 'array',
        'paid_at' => 'datetime',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function module() { return $this->belongsTo(Module::class); }

    public function isCompleted(): bool { return $this->payment_status === 'completed'; }
    public function isPending(): bool   { return $this->payment_status === 'pending'; }

    public function markAsCompleted(string $transactionId = null): void
    {
        $this->update(['payment_status' => 'completed', 'transaction_id' => $transactionId ?? $this->transaction_id, 'paid_at' => now()]);
    }

    public function markAsFailed(): void
    {
        $this->update(['payment_status' => 'failed']);
    }
}
```

---

### `app/Models/PaymentMethod.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PaymentMethod extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id', 'stripe_payment_method_id', 'type',
        'brand', 'last4', 'exp_month', 'exp_year',
        'is_default', 'is_active', 'metadata',
    ];

    protected $casts = [
        'is_default' => 'boolean', 'is_active' => 'boolean', 'metadata' => 'array',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }

    public function setAsDefault()
    {
        self::where('tenant_id', $this->tenant_id)->where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->update(['is_default' => true]);
    }

    public function isExpired(): bool
    {
        if (!$this->exp_month || !$this->exp_year) return false;
        return Carbon::createFromDate($this->exp_year, $this->exp_month, 1)->endOfMonth()->isPast();
    }

    public function getDisplayNameAttribute(): string { return ucfirst($this->brand ?? 'Card') . " •••• {$this->last4}"; }
    public function getExpiryDisplayAttribute(): ?string
    {
        return ($this->exp_month && $this->exp_year) ? sprintf('%02d/%d', $this->exp_month, $this->exp_year) : null;
    }

    public function scopeDefault($q)           { return $q->where('is_default', true); }
    public function scopeActive($q)            { return $q->where('is_active', true); }
    public function scopeForTenant($q, $tid)   { return $q->where('tenant_id', $tid); }
}
```

---

## 🎭 Traits {#traits}

### `app/Traits/TenantAware.php`

```php
<?php

namespace App\Traits;

use App\Services\DatabaseManager;
use Illuminate\Support\Facades\Log;

trait TenantAware
{
    public string $tenantId;

    /** Assign this job to a tenant. */
    public function forTenant(string $tenantId): self
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    /**
     * Switch the DB connection to this tenant's database.
     * Called automatically by the Global Queue Guard in AppServiceProvider.
     */
    public function applyTenantContext(): void
    {
        if (empty($this->tenantId)) {
            Log::warning('TenantAware Job ' . get_class($this) . ' dispatched without tenantId.');
            return;
        }

        try {
            app(DatabaseManager::class)->switchToTenantDatabase($this->tenantId);
            Log::debug("Applied tenant context ({$this->tenantId}) for job: " . get_class($this));
        } catch (\Exception $e) {
            Log::error('Failed to apply tenant context: ' . $e->getMessage());
            throw $e;
        }
    }
}
```

---

## 🛡️ Middlewares {#middlewares}

### `app/Http/Middleware/IdentifyTenant.php`

```php
<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\DatabaseManager;
use Closure;
use Illuminate\Http\Request;

class IdentifyTenant
{
    public function __construct(protected DatabaseManager $databaseManager) {}

    public function handle(Request $request, Closure $next)
    {
        // 1️⃣ Header → 2️⃣ Custom Domain → 3️⃣ Subdomain
        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId) {
            $host = $request->getHost();
            $domain = TenantDomain::where('domain', $host)->where('status', 'verified')->first();
            if ($domain) {
                $tenantId = $domain->tenant_id;
            } elseif (str_ends_with($host, '.' . parse_url(config('app.url'), PHP_URL_HOST))) {
                $tenantId = str_replace('.' . parse_url(config('app.url'), PHP_URL_HOST), '', $host);
            }
        }

        if (!$tenantId) {
            return response()->json(['success' => false, 'message' => 'Tenant identification required'], 400);
        }

        $tenant = Tenant::where('tenant_id', $tenantId)->first();

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        // Kill-switch for suspended/terminated tenants
        if (in_array($tenant->status, ['suspended', 'terminated', 'billing_failed'])) {
            return response()->json(['success' => false, 'message' => 'Tenant account is ' . $tenant->status], 403);
        }

        // Switch DB connection to this tenant's isolated database
        $this->databaseManager->switchToTenantDatabase($tenantId);
        $request->attributes->set('tenant_id', $tenant->tenant_id);

        return $next($request);
    }
}
```

---

### `app/Http/Middleware/AuthenticateToken.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticateToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken() ?: $request->query('token');

        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Unauthorized - No token provided'], 401);
        }

        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) throw new \Exception('Invalid token format');

            [$header, $payload, $signature] = $parts;

            // Verify HS256 signature
            $expectedSig = base64_encode(hash_hmac('sha256', "$header.$payload", config('app.key'), true));
            if ($signature !== $expectedSig) throw new \Exception('Invalid token signature');

            $payloadData = json_decode(base64_decode($payload), true);

            // Check expiry
            if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
                throw new \Exception('Token expired');
            }

            $tokenTenantId     = $payloadData['tenantId'] ?? null;
            $identifiedTenantId= $request->attributes->get('tenant_id');

            // STRICT ISOLATION: token tenant must match URL tenant
            if ($identifiedTenantId && $tokenTenantId && $identifiedTenantId !== $tokenTenantId) {
                return response()->json(['success' => false, 'message' => 'Access denied for this tenant'], 403);
            }

            $request->merge(['user_id' => $payloadData['id'] ?? null, 'token_tenant_id' => $tokenTenantId]);
            return $next($request);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Unauthorized - ' . $e->getMessage()], 401);
        }
    }
}
```

---

### `app/Http/Middleware/CheckModuleAccess.php`

```php
<?php

namespace App\Http\Middleware;

use App\Services\ModuleService;
use Closure;
use Illuminate\Http\Request;

class CheckModuleAccess
{
    public function __construct(protected ModuleService $moduleService) {}

    public function handle(Request $request, Closure $next, string $moduleKey)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$this->moduleService->isModuleActive($tenantId, $moduleKey)) {
            return response()->json([
                'success' => false,
                'message' => "Module '{$moduleKey}' is not active. Please subscribe to access this feature.",
            ], 402); // 402 Payment Required
        }

        return $next($request);
    }
}
```

---

### `app/Http/Middleware/EnforceDatabaseQuota.php`

```php
<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantDatabaseIsolationService;
use Closure;
use Illuminate\Http\Request;

class EnforceDatabaseQuota
{
    public function __construct(protected TenantDatabaseIsolationService $isolationService) {}

    public function handle(Request $request, Closure $next)
    {
        // Only check on write operations
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        $tenantId = $request->attributes->get('tenant_id') ?? $request->input('token_tenant_id');
        $tenant   = Tenant::where('tenant_id', $tenantId)->first();

        if ($tenant && $this->isolationService->isOverQuota($tenant)) {
            return response()->json([
                'success' => false,
                'message' => 'Database quota exceeded. Please upgrade your plan.',
            ], 403);
        }

        return $next($request);
    }
}
```

---

### `app/Http/Middleware/DynamicCors.php`

```php
<?php

namespace App\Http\Middleware;

use App\Models\TenantDomain;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DynamicCors
{
    public function handle(Request $request, Closure $next)
    {
        $origin = $request->headers->get('Origin');
        if (!$origin) return $next($request);

        if ($this->isAllowed($origin)) {
            $response = $next($request);
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Tenant-ID, Accept');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            if ($request->getMethod() === 'OPTIONS') $response->setStatusCode(204);
            return $response;
        }

        return $next($request);
    }

    private function isAllowed(string $origin): bool
    {
        if (in_array($origin, config('cors.allowed_origins', []))) return true;
        foreach (config('cors.allowed_origins_patterns', []) as $pattern) {
            if (preg_match($pattern, $origin)) return true;
        }
        $host = strtolower(explode(':', parse_url($origin, PHP_URL_HOST) ?? '')[0]);
        if (!$host || filter_var($host, FILTER_VALIDATE_IP)) return false;

        // Check verified tenant custom domains (cached 10 min)
        return Cache::remember('tenant_domain_' . md5($host), 600, function () use ($host) {
            return TenantDomain::where('domain', $host)->where('status', 'verified')->exists();
        });
    }
}
```

---

##  Controllers {#controllers}

### `app/Http/Controllers/Api/AuthController.php`

```php
<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /** Register new user in tenant DB */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255',
            'password' => 'required|string|min:6',
        ]);

        $existing = DB::connection('tenant_dynamic')->table('users')->where('email', $validated['email'])->first();
        if ($existing) return response()->json(['success' => false, 'message' => 'User already exists'], 400);

        $userId = DB::connection('tenant_dynamic')->table('users')->insertGetId([
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'password'   => Hash::make($validated['password']),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $token = $this->generateToken($userId, $request->input('tenant')['id']);
        return response()->json(['success' => true, 'data' => ['id' => $userId, 'token' => $token]], 201);
    }

    /** Login user in tenant DB */
    public function login(Request $request)
    {
        $validated = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = DB::connection('tenant_dynamic')->table('users')->where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }

        $token = $this->generateToken($user->id, $request->input('tenant')['id']);
        return response()->json(['success' => true, 'data' => ['id' => $user->id, 'name' => $user->name, 'token' => $token]]);
    }

    public function logout(Request $request)
    {
        return response()->json(['success' => true, 'message' => 'Logout successful']);
    }

    /** Get current authenticated user */
    public function me(Request $request)
    {
        $userId = $request->user_id ?? null;
        if (!$userId) return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);

        $user = DB::connection('tenant_dynamic')->table('users')->where('id', $userId)->first();
        if (!$user) return response()->json(['success' => false, 'message' => 'User not found'], 404);

        return response()->json(['success' => true, 'data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]]);
    }

    /** Generate HS256 JWT */
    protected function generateToken(int $userId, string $tenantId): string
    {
        $header  = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode(['id' => $userId, 'tenantId' => $tenantId, 'exp' => time() + (7 * 24 * 60 * 60)]));
        $sig     = base64_encode(hash_hmac('sha256', "$header.$payload", config('app.key'), true));
        return "$header.$payload.$sig";
    }
}
```

---

### `app/Http/Controllers/Api/TenantController.php`

```php
<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TenantService;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(protected TenantService $tenantService) {}

    /** Register new tenant (provisioning state machine) */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'tenantId'      => 'required|string|regex:/^[a-z0-9-]+$/|unique:tenants,tenant_id|max:50',
            'tenantName'    => 'required|string|max:255',
            'companyName'   => 'required|string|max:255',
            'businessType'  => 'required|in:sole_proprietorship,partnership,llc,corporation',
            'adminName'     => 'required|string|max:255',
            'adminEmail'    => 'required|email|max:255',
            'adminPassword' => 'required|string|min:8',
            'phone'         => 'required|regex:/^[0-9+\-\s()]+$/|max:20',
            'address'       => 'required|string|max:500',
            'city'          => 'required|string|max:100',
            'country'       => 'required|string|max:100',
        ]);

        $tenant = $this->tenantService->createTenant($validated);
        return response()->json(['success' => true, 'data' => ['tenantId' => $tenant->tenant_id, 'databaseName' => $tenant->database_name]], 201);
    }

    /** Get single tenant by ID */
    public function show(string $tenantId)
    {
        $tenant = $this->tenantService->getTenantByTenantId($tenantId);
        if (!$tenant) return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        return response()->json(['success' => true, 'data' => $tenant]);
    }

    /** Get current tenant from request context */
    public function current(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');
        if (!$tenantId) return response()->json(['success' => false, 'message' => 'No tenant identified'], 400);

        $tenant = $this->tenantService->getTenantByTenantId($tenantId);
        return response()->json(['success' => true, 'data' => [
            'tenant_id'    => $tenant->tenant_id,
            'platform_ip'  => env('PLATFORM_IP', '127.0.0.1'),
            'base_domain'  => parse_url(config('app.url'), PHP_URL_HOST),
        ]]);
    }

    public function index()
    {
        $tenants = $this->tenantService->getAllTenants();
        return response()->json(['success' => true, 'count' => $tenants->count(), 'data' => $tenants]);
    }
}
```

---

### `app/Http/Controllers/Api/UserController.php`

```php
<?php
namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends \App\Http\Controllers\Controller
{
    /** List users  search, role/status filter, pagination */
    public function index(Request $request)
    {
        $query = User::query();
        if ($request->search) $query->where(fn($q) => $q->where('name','LIKE',"%{$request->search}%")->orWhere('email','LIKE',"%{$request->search}%"));
        if ($request->role)   $query->where('role', $request->role);
        if ($request->status) $query->where('status', $request->status);
        return response()->json(['success'=>true,'data'=>$query->orderByDesc('created_at')->paginate($request->get('per_page',10))]);
    }

    /** Create user in tenant DB */
    public function store(Request $request)
    {
        $data = $request->validate(['name'=>'required','email'=>'required|email|unique:users,email','password'=>'required|min:8','role'=>'required|in:admin,manager,user','status'=>'required|in:active,inactive']);
        $user = User::create([...$data, 'password'=>Hash::make($data['password'])]);
        return response()->json(['success'=>true,'data'=>$user],201);
    }

    public function show($id) { return response()->json(['success'=>true,'data'=>User::findOrFail($id)]); }

    /** Update user  only changes password if provided */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $data = $request->validate(['name'=>'required','email'=>['required','email',Rule::unique('users')->ignore($id)],'password'=>'nullable|min:8','role'=>'required|in:admin,manager,user','status'=>'required|in:active,inactive']);
        $user->fill($data);
        if ($request->filled('password')) $user->password = Hash::make($request->password);
        $user->save();
        return response()->json(['success'=>true,'data'=>$user]);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) return response()->json(['success'=>false,'message'=>'Cannot delete yourself'],403);
        $user->delete();
        return response()->json(['success'=>true,'message'=>'User deleted']);
    }
}
```

---

### `app/Http/Controllers/Api/SubscriptionController.php`

```php
<?php
namespace App\Http\Controllers\Api;

use App\Services\ModuleService;
use Illuminate\Http\Request;

class SubscriptionController extends \App\Http\Controllers\Controller
{
    public function __construct(protected ModuleService $moduleService) {}

    /** All available modules */
    public function index()
    {
        return response()->json(['success'=>true,'data'=>$this->moduleService->getAvailableModules()]);
    }

    /** Modules subscribed by current tenant */
    public function tenantModules(Request $request)
    {
        return response()->json(['success'=>true,'data'=>$this->moduleService->getTenantModules($request->attributes->get('tenant_id'))]);
    }

    /** Subscribe to a module  runs migrations on subscribe */
    public function subscribe(Request $request)
    {
        $request->validate(['module_key'=>'required|string']);
        $result = $this->moduleService->subscribeModule($request->attributes->get('tenant_id'), $request->module_key);
        return response()->json($result, $result['success'] ? 201 : 400);
    }

    /** Unsubscribe  non-destructive (archives tables) */
    public function unsubscribe(Request $request, $moduleKey)
    {
        $result = $this->moduleService->unsubscribeModule($request->attributes->get('tenant_id'), $moduleKey);
        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function checkAccess(Request $request, $moduleKey)
    {
        return response()->json(['success'=>true,'has_access'=>$this->moduleService->isModuleActive($request->attributes->get('tenant_id'),$moduleKey)]);
    }
}
```

---

### `app/Http/Controllers/Api/SuperAdminController.php`

```php
<?php
namespace App\Http\Controllers\Api;

use App\Models\{Tenant, Module, TenantModule};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminController extends \App\Http\Controllers\Controller
{
    /** Platform-wide dashboard stats */
    public function dashboard()
    {
        return response()->json(['success'=>true,'data'=>[
            'total_tenants'       => Tenant::count(),
            'active_tenants'      => Tenant::where('status','active')->count(),
            'total_modules'       => Module::count(),
            'total_subscriptions' => TenantModule::where('status','active')->count(),
            'total_revenue'       => TenantModule::where('status','active')->join('modules','tenant_modules.module_id','=','modules.id')->sum('modules.price'),
            'monthly_revenue'     => TenantModule::where('status','active')->whereMonth('subscribed_at',now()->month)->join('modules','tenant_modules.module_id','=','modules.id')->sum('modules.price'),
        ]]);
    }

    /** List + search + filter tenants */
    public function tenants(Request $request)
    {
        $q = Tenant::query();
        if ($request->search) $q->where(fn($sq)=>$sq->where('tenant_id','like',"%{$request->search}%")->orWhere('company_name','like',"%{$request->search}%"));
        if ($request->status) $q->where('status',$request->status);
        return response()->json(['success'=>true,'data'=>$q->with('tenantModules.module')->orderByDesc('created_at')->paginate($request->per_page??10)]);
    }

    public function tenantDetails($id)
    {
        $tenant = Tenant::with('tenantModules.module')->findOrFail($id);
        return response()->json(['success'=>true,'data'=>['tenant'=>$tenant,'subscriptions'=>TenantModule::where('tenant_id',$id)->with('module')->get()]]);
    }

    public function approveTenant($id)  { Tenant::findOrFail($id)->update(['status'=>'active']); return response()->json(['success'=>true,'message'=>'Approved']); }
    public function suspendTenant($id)  { Tenant::findOrFail($id)->update(['status'=>'suspended']); return response()->json(['success'=>true,'message'=>'Suspended']); }

    public function deleteTenant($id)
    {
        $tenant = Tenant::findOrFail($id);
        DB::statement("DROP DATABASE IF EXISTS {$tenant->database_name}");
        $tenant->delete();
        return response()->json(['success'=>true,'message'=>'Tenant deleted']);
    }
}
```

---

### `app/Http/Controllers/Api/SuperAdminAuthController.php`

```php
<?php
namespace App\Http\Controllers\Api;

use App\Models\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SuperAdminAuthController extends \App\Http\Controllers\Controller
{
    public function login(Request $request)
    {
        $request->validate(['email'=>'required|email','password'=>'required|min:6']);
        $admin = SuperAdmin::where('email',$request->email)->first();
        if (!$admin || !Hash::check($request->password,$admin->password)) return response()->json(['success'=>false,'message'=>'Invalid credentials'],401);
        if (!$admin->isActive()) return response()->json(['success'=>false,'message'=>'Account inactive'],403);
        $admin->updateLastLogin();
        return response()->json(['success'=>true,'data'=>['super_admin'=>$admin,'token'=>$admin->createToken('super-admin-token')->plainTextToken]]);
    }

    public function me(Request $request) { return response()->json(['success'=>true,'data'=>$request->user()]); }
    public function logout(Request $request) { $request->user()->currentAccessToken()->delete(); return response()->json(['success'=>true,'message'=>'Logged out']); }

    public function changePassword(Request $request)
    {
        $request->validate(['current_password'=>'required','new_password'=>'required|min:6|confirmed']);
        $admin = $request->user();
        if (!Hash::check($request->current_password,$admin->password)) return response()->json(['success'=>false,'message'=>'Current password incorrect'],400);
        $admin->update(['password'=>Hash::make($request->new_password)]);
        return response()->json(['success'=>true,'message'=>'Password changed']);
    }
}
```

---

### `app/Http/Controllers/Api/PaymentMethodController.php`

```php
<?php
namespace App\Http\Controllers\Api;

use App\Models\{PaymentMethod, Tenant};
use Illuminate\Http\Request;

class PaymentMethodController extends \App\Http\Controllers\Controller
{
    /** List active payment methods for tenant */
    public function index(Request $request)
    {
        $tenant = Tenant::where('tenant_id', $request->input('token_tenant_id'))->firstOrFail();
        $methods = PaymentMethod::where('tenant_id',$tenant->id)->where('is_active',true)->orderByDesc('is_default')->orderByDesc('created_at')->get()->map(fn($pm)=>['id'=>$pm->id,'brand'=>$pm->brand,'last4'=>$pm->last4,'is_default'=>$pm->is_default,'display_name'=>$pm->display_name,'expiry_display'=>$pm->expiry_display,'is_expired'=>$pm->isExpired()]);
        return response()->json(['success'=>true,'data'=>$methods]);
    }

    /** Attach a Stripe payment method to tenant  deduplication built-in */
    public function store(Request $request)
    {
        $request->validate(['stripe_payment_method_id'=>'required|string']);
        $tenant = Tenant::where('tenant_id',$request->input('token_tenant_id'))->firstOrFail();
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
        $spm = \Stripe\PaymentMethod::retrieve($request->stripe_payment_method_id);

        if (PaymentMethod::where('stripe_payment_method_id',$spm->id)->exists()) {
            return response()->json(['success'=>false,'message'=>'Payment method already exists'],400);
        }

        $pm = PaymentMethod::create(['tenant_id'=>$tenant->id,'stripe_payment_method_id'=>$spm->id,'type'=>$spm->type,'brand'=>$spm->card->brand,'last4'=>$spm->card->last4,'exp_month'=>$spm->card->exp_month,'exp_year'=>$spm->card->exp_year,'is_active'=>true]);
        if (PaymentMethod::where('tenant_id',$tenant->id)->count() === 1) $pm->setAsDefault();

        return response()->json(['success'=>true,'message'=>'Payment method added','data'=>['id'=>$pm->id,'display_name'=>$pm->display_name]],201);
    }

    /** Soft-delete (is_active = false) + reassign default if needed */
    public function destroy(Request $request, $id)
    {
        $tenant = Tenant::where('tenant_id',$request->input('token_tenant_id'))->firstOrFail();
        $pm = PaymentMethod::where('id',$id)->where('tenant_id',$tenant->id)->firstOrFail();
        $wasDefault = $pm->is_default;
        $pm->update(['is_active'=>false]);
        if ($wasDefault) {
            $next = PaymentMethod::where('tenant_id',$tenant->id)->where('is_active',true)->where('id','!=',$id)->first();
            if ($next) $next->setAsDefault();
        }
        return response()->json(['success'=>true,'message'=>'Removed']);
    }

    public function setDefault(Request $request, $id)
    {
        $tenant = Tenant::where('tenant_id',$request->input('token_tenant_id'))->firstOrFail();
        PaymentMethod::where('id',$id)->where('tenant_id',$tenant->id)->where('is_active',true)->firstOrFail()->setAsDefault();
        return response()->json(['success'=>true,'message'=>'Default updated']);
    }
}
```

---

### `app/Http/Controllers/Api/PaymentHistoryController.php`

```php
<?php
namespace App\Http\Controllers\Api;

use App\Models\{Invoice, Payment, Tenant};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PaymentHistoryController extends \App\Http\Controllers\Controller
{
    /** Paginated payment history for tenant */
    public function index(Request $request)
    {
        $tenant = Tenant::where('tenant_id',$request->input('token_tenant_id'))->firstOrFail();
        $payments = Payment::with('module')->where('tenant_id',$tenant->id)->orderByDesc('created_at')->get()->map(fn($p)=>['id'=>$p->id,'module_name'=>$p->module->module_name??'N/A','amount'=>$p->amount,'status'=>$p->payment_status,'created_at'=>$p->created_at]);
        return response()->json(['success'=>true,'data'=>$payments]);
    }

    /** Total spend, count, last payment */
    public function statistics(Request $request)
    {
        $tenant = Tenant::where('tenant_id',$request->input('token_tenant_id'))->firstOrFail();
        return response()->json(['success'=>true,'data'=>[
            'total_paid'     => Payment::where('tenant_id',$tenant->id)->where('payment_status','completed')->sum('amount'),
            'total_payments' => Payment::where('tenant_id',$tenant->id)->count(),
            'last_payment'   => Payment::where('tenant_id',$tenant->id)->where('payment_status','completed')->latest()->first(),
        ]]);
    }

    /** Download PDF invoice  builds virtual invoice if no Invoice record exists */
    public function downloadInvoice(Request $request, $paymentId)
    {
        $tenant  = Tenant::where('tenant_id',$request->input('token_tenant_id'))->firstOrFail();
        $payment = Payment::with(['module','tenant'])->where('id',$paymentId)->where('tenant_id',$tenant->id)->firstOrFail();
        $invoice = Invoice::with(['module','payment','tenant'])->where('payment_id',$payment->id)->where('tenant_id',$tenant->id)->first();

        if (!$invoice) {
            $invoice = new Invoice(['invoice_number'=>'INV-'.str_pad($payment->id,6,'0',STR_PAD_LEFT),'invoice_date'=>$payment->created_at,'due_date'=>$payment->created_at,'subtotal'=>$payment->amount,'tax'=>0,'total'=>$payment->amount,'status'=>$payment->payment_status==='completed'?'paid':$payment->payment_status]);
            $invoice->setRelation('tenant',$tenant)->setRelation('module',$payment->module)->setRelation('payment',$payment);
        }

        return Pdf::loadView('invoices.pdf',compact('invoice'))->download('invoice-'.$invoice->invoice_number.'.pdf');
    }
}
```

---

### `app/Http/Controllers/Api/TenantDatabaseController.php` (Summary)

```php
<?php
namespace App\Http\Controllers\Api;

// Exposes database analytics to authenticated tenants
// Uses TenantDatabaseAnalyticsService + TenantDatabaseIsolationService

class TenantDatabaseController extends \App\Http\Controllers\Controller
{
    public function __construct(
        protected \App\Services\TenantDatabaseAnalyticsService $analyticsService,
        protected \App\Services\TenantDatabaseIsolationService $isolationService
    ) {}

    // GET /api/database/analytics  usage MB, quota, smart alert thresholds (75%/90%/100%)
    public function analytics(Request $request) { /* ... */ }

    // GET /api/database/tables  per-table size, rows, engine breakdown
    public function tables(Request $request) { /* ... */ }

    // GET /api/database/growth?days=30  daily size snapshots (clamped 1-365 days)
    public function growth(Request $request) { /* ... */ }

    // GET /api/database/plans  available upgrade plans ordered by storage limit
    public function plans() { /* ... */ }
}
```

---

##  Services {#services}

### `app/Services/DatabaseManager.php`

```php
<?php
namespace App\Services;

use Illuminate\Support\Facades\{DB, Hash, Config};

class DatabaseManager
{
    protected static array $connectionCache = [];

    /** Create MySQL database for new tenant, set up isolated user, assign starter plan */
    public function createTenantDatabase(string $databaseName): void
    {
        DB::statement("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        try {
            $tenant = \App\Models\Tenant::where('database_name', $databaseName)->first();
            if ($tenant) {
                app(TenantDatabaseIsolationService::class)->createIsolatedUser($tenant);
                if (!$tenant->database_plan_id) {
                    $plan = \App\Models\TenantDatabasePlan::where('slug','starter')->first();
                    if ($plan) $tenant->update(['database_plan_id'=>$plan->id]);
                }
            }
        } catch (\Exception $e) { \Log::warning("Could not create isolated user: ".$e->getMessage()); }
    }

    /** Create users + personal_access_tokens tables in new tenant DB */
    public function runTenantMigrations(string $databaseName): void
    {
        $conn = $this->getTenantConnection($databaseName);
        $conn->statement("CREATE TABLE IF NOT EXISTS users (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, role ENUM('admin','manager','user') DEFAULT 'user', status ENUM('active','inactive') DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $conn->statement("CREATE TABLE IF NOT EXISTS personal_access_tokens (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tokenable_type VARCHAR(255) NOT NULL, tokenable_id BIGINT UNSIGNED NOT NULL, name VARCHAR(255) NOT NULL, token VARCHAR(64) NOT NULL UNIQUE, abilities TEXT NULL, last_used_at TIMESTAMP NULL, expires_at TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function createAdminUser(string $databaseName, string $email, string $password): void
    {
        $this->getTenantConnection($databaseName)->table('users')->insert(['name'=>'Admin','email'=>$email,'password'=>Hash::make($password),'role'=>'admin','status'=>'active','created_at'=>now(),'updated_at'=>now()]);
    }

    /** Get (cached) named DB connection for a specific tenant database */
    public function getTenantConnection(string $databaseName)
    {
        if (isset(self::$connectionCache[$databaseName])) return self::$connectionCache[$databaseName];
        $tenant = \App\Models\Tenant::where('database_name',$databaseName)->first();
        $connName = 'tenant_'.$databaseName;
        Config::set("database.connections.{$connName}", ['driver'=>'mysql','host'=>config('tenant.database.host'),'database'=>$databaseName,'username'=>$tenant->db_username??config('tenant.database.username'),'password'=>$tenant->db_password_encrypted??config('tenant.database.password'),'charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci']);
        return self::$connectionCache[$databaseName] = DB::connection($connName);
    }

    /** Purge + reconnect tenant_dynamic connection  called by IdentifyTenant middleware */
    public function switchToTenantDatabase(string $tenantId): void
    {
        $tenant = \App\Models\Tenant::where('tenant_id',$tenantId)->where('status','active')->firstOrFail();
        Config::set('database.connections.tenant_dynamic', ['driver'=>'mysql','host'=>config('tenant.database.host'),'database'=>$tenant->database_name,'username'=>$tenant->db_username??config('tenant.database.username'),'password'=>$tenant->db_password_encrypted??config('tenant.database.password'),'charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci']);
        DB::purge('tenant_dynamic');
        DB::reconnect('tenant_dynamic');
        DB::setDefaultConnection('tenant_dynamic');
    }
}
```

---

### `app/Services/TenantService.php`

```php
<?php
namespace App\Services;

use App\Models\{Tenant, TenantDomain};
use Illuminate\Support\Facades\Log;

class TenantService
{
    public function __construct(protected DatabaseManager $databaseManager) {}

    /**
     * Atomic provisioning state machine:
     * pending  db_created  migrated  active (or failed)
     */
    public function createTenant(array $data): Tenant
    {
        $dbName = Tenant::generateDatabaseName($data['tenantId']);
        $domain = $data['tenantId'].'.'.parse_url(config('app.url'),PHP_URL_HOST);

        $tenant = Tenant::create([...$data,'database_name'=>$dbName,'domain'=>$domain,'status'=>'inactive','provisioning_status'=>'pending']);

        try {
            $this->databaseManager->createTenantDatabase($dbName);
            $tenant->update(['provisioning_status'=>'db_created']);

            $this->databaseManager->runTenantMigrations($dbName);
            $tenant->update(['provisioning_status'=>'migrated']);

            $this->databaseManager->createAdminUser($dbName,$data['adminEmail'],$data['adminPassword']);
            $tenant->update(['provisioning_status'=>'active','status'=>'active']);

            TenantDomain::create(['tenant_id'=>$tenant->tenant_id,'domain'=>$domain,'is_primary'=>true,'is_verified'=>true,'status'=>'verified']);

            return $tenant->fresh();
        } catch (\Exception $e) {
            $tenant->update(['provisioning_status'=>'failed']);
            Log::critical("Provisioning failed for {$data['tenantId']}: ".$e->getMessage());
            throw $e;
        }
    }

    /** Remove failed/pending tenants older than N hours */
    public function cleanupFailedProvisioning(int $olderThanHours = 24): int
    {
        $count = 0;
        Tenant::whereIn('provisioning_status',['failed','pending'])->where('created_at','<=',now()->subHours($olderThanHours))->each(function($tenant) use (&$count) {
            try { DB::statement("DROP DATABASE IF EXISTS `{$tenant->database_name}`"); $tenant->delete(); $count++; }
            catch (\Exception $e) { Log::error("Cleanup failed: ".$e->getMessage()); }
        });
        return $count;
    }

    public function getAllTenants()         { return Tenant::all(); }
    public function getTenantByTenantId($id){ return Tenant::where('tenant_id',$id)->first(); }
}
```

---

### `app/Services/TenantDatabaseIsolationService.php`

```php
<?php
namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantDatabaseIsolationService
{
    /** CREATE MySQL user with GRANT only to their own database */
    public function createIsolatedUser(Tenant $tenant): array
    {
        $username = substr('tu_'.Str::slug($tenant->tenant_id,'_'), 0, 32);
        $password = Str::random(32);

        DB::statement("DROP USER IF EXISTS '{$username}'@'%'");
        DB::statement("CREATE USER '{$username}'@'%' IDENTIFIED BY '{$password}'");
        DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES ON `{$tenant->database_name}`.* TO '{$username}'@'%'");
        DB::statement("FLUSH PRIVILEGES");

        $tenant->update(['db_username'=>$username,'db_password_encrypted'=>$password]);
        return compact('username','password');
    }

    public function revokeAccess(Tenant $tenant): void
    {
        if (!$tenant->db_username) return;
        DB::statement("REVOKE ALL PRIVILEGES, GRANT OPTION FROM '{$tenant->db_username}'@'%'");
        DB::statement("FLUSH PRIVILEGES");
    }

    public function dropIsolatedUser(Tenant $tenant): void
    {
        if (!$tenant->db_username) return;
        DB::statement("DROP USER IF EXISTS '{$tenant->db_username}'@'%'");
        DB::statement("FLUSH PRIVILEGES");
    }

    public function checkQuotaUsage(Tenant $tenant): array
    {
        $plan = $tenant->databasePlan;
        if (!$plan) return ['over_quota'=>false,'usage_mb'=>0,'limit_mb'=>0,'usage_percent'=>0];
        $usageMb = (float)($tenant->latestDatabaseStat->database_size_mb ?? 0);
        $limitMb = $plan->storage_limit_mb;
        return ['over_quota'=>$usageMb>=$limitMb,'usage_mb'=>$usageMb,'limit_mb'=>$limitMb,'usage_percent'=>$limitMb>0?round($usageMb/$limitMb*100,2):0,'plan'=>$plan->name];
    }

    public function isOverQuota(Tenant $tenant): bool { return $this->checkQuotaUsage($tenant)['over_quota']; }
}
```

---

### `app/Services/ModuleService.php`

```php
<?php
namespace App\Services;

use App\Models\{Module, TenantModule};
use Illuminate\Support\Facades\DB;

class ModuleService
{
    public function __construct(protected ModuleMigrationManager $migrationManager) {}

    /** Subscribe tenant to module + run its migrations (transactional) */
    public function subscribeModule(string $tenantId, string $moduleKey): array
    {
        $module = Module::where('module_key',$moduleKey)->where('is_active',true)->first();
        if (!$module) return ['success'=>false,'message'=>'Module not found or inactive'];

        $existing = TenantModule::where('tenant_id',$tenantId)->where('module_id',$module->id)->where('status','active')->first();
        if ($existing) return ['success'=>false,'message'=>'Already subscribed'];

        return DB::transaction(function() use ($tenantId,$module) {
            $subscription = TenantModule::create(['tenant_id'=>$tenantId,'module_id'=>$module->id,'status'=>'active','subscribed_at'=>now()]);

            $tenant = \App\Models\Tenant::where('tenant_id',$tenantId)->first();
            $this->migrationManager->runModuleMigrations($tenant->database_name, $module->module_key);

            return ['success'=>true,'message'=>'Subscribed successfully','data'=>$subscription];
        });
    }

    /** Unsubscribe  sets inactive, tables archived (non-destructive) */
    public function unsubscribeModule(string $tenantId, string $moduleKey): array
    {
        $module = Module::where('module_key',$moduleKey)->first();
        if (!$module) return ['success'=>false,'message'=>'Module not found'];

        $subscription = TenantModule::where('tenant_id',$tenantId)->where('module_id',$module->id)->where('status','active')->first();
        if (!$subscription) return ['success'=>false,'message'=>'Not subscribed'];

        $subscription->update(['status'=>'inactive']);

        $tenant = \App\Models\Tenant::where('tenant_id',$tenantId)->first();
        $this->migrationManager->rollbackModuleMigrations($tenant->database_name, $module->module_key);

        return ['success'=>true,'message'=>'Unsubscribed. Data archived.'];
    }

    public function isModuleActive(string $tenantId, string $moduleKey): bool
    {
        return TenantModule::where('tenant_id',$tenantId)->whereHas('module',fn($q)=>$q->where('module_key',$moduleKey))->where('status','active')->exists();
    }

    public function getAvailableModules() { return Module::active()->get(); }
    public function getTenantModules(string $tenantId) { return TenantModule::where('tenant_id',$tenantId)->where('status','active')->with('module')->get(); }
}
```

---

### `app/Services/ModuleMigrationManager.php` (Key Methods)

```php
<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class ModuleMigrationManager
{
    /**
     * Run module migrations with:
     * - Quota guard (blocks if tenant over storage limit)
     * - Row-level lock (prevents race conditions)
     * - Batch tracking in master `module_migrations` table
     */
    public function runModuleMigrations(string $databaseName, string $moduleKey): void
    {
        $tenant = \App\Models\Tenant::where('database_name',$databaseName)->first();
        if ($tenant && app(TenantDatabaseIsolationService::class)->isOverQuota($tenant)) {
            throw new \Exception("Cannot run migrations: quota exceeded for {$databaseName}");
        }

        // Acquire row-level lock to prevent concurrent migrations
        DB::connection('mysql')->table('module_migrations')
            ->where('tenant_database',$databaseName)->where('module_key',$moduleKey)
            ->lockForUpdate()->get();

        $migrations = $this->getModuleMigrations($moduleKey);
        $batch = $this->getNextBatchNumber($databaseName,$moduleKey);

        foreach ($migrations as $migration) {
            if ($this->hasRun($databaseName,$moduleKey,$migration['name'])) continue;
            $this->executeMigration($databaseName,$migration);
            $this->recordMigration($databaseName,$moduleKey,$migration['name'],$batch);
        }
    }

    /**
     * Non-destructive rollback: RENAME TABLE instead of DROP
     * ec_products  ec_products_archived_2024_01_15
     */
    public function rollbackModuleMigrations(string $databaseName, string $moduleKey): void
    {
        $this->archiveModuleTables($databaseName,$moduleKey);
        DB::connection('mysql')->table('module_migrations')
            ->where('tenant_database',$databaseName)->where('module_key',$moduleKey)->delete();
    }

    public function archiveModuleTables(string $databaseName, string $moduleKey): void
    {
        $suffix = '_archived_'.now()->format('Y_m_d');
        $tables = $this->getModuleTableNames($moduleKey);
        foreach ($tables as $table) {
            try {
                DB::statement("RENAME TABLE `{$databaseName}`.`{$table}` TO `{$databaseName}`.`{$table}{$suffix}`");
            } catch (\Exception $e) { \Log::warning("Could not archive table {$table}: ".$e->getMessage()); }
        }
    }
}
```

---

### `app/Providers/AppServiceProvider.php` (Global Queue Guard)

```php
<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\{Queue, Log};

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /**
         * GLOBAL QUEUE GUARD
         * Intercepts every queued job before execution.
         * If the job uses the TenantAware trait, it switches the
         * tenant_dynamic DB connection BEFORE the job runs.
         * Prevents cross-tenant data leaks in background jobs.
         */
        Queue::before(function (\Illuminate\Queue\Events\JobProcessing $event) {
            $data = json_decode($event->job->getRawBody(), true);
            if (isset($data['data']['command'])) {
                $command = unserialize($data['data']['command']);
                if (method_exists($command, 'applyTenantContext')) {
                    $command->applyTenantContext();
                    Log::info('Global Queue Guard: Applied tenant context for '.get_class($command));
                }
            }
        });
    }
}
```

---

##  Complete Route Map (`routes/api.php`)

```php
<?php
// Public
Route::get('/health', fn() => response()->json(['status'=>'OK']));
Route::post('/stripe/webhook', [PaymentController::class, 'stripeWebhook']);

// Tenant Registration (no middleware)
Route::prefix('tenants')->group(function() {
    Route::post('/register', [TenantController::class, 'register']);
    Route::get('/{tenantId}', [TenantController::class, 'show']);
    Route::get('/', [TenantController::class, 'index']);
});

// Super Admin (Sanctum)
Route::prefix('super-admin')->group(function() {
    Route::post('/login', [SuperAdminAuthController::class, 'login']);
    Route::middleware(['auth:sanctum'])->group(function() {
        Route::post('/logout',  [SuperAdminAuthController::class, 'logout']);
        Route::get('/me',       [SuperAdminAuthController::class, 'me']);
        Route::post('/change-password', [SuperAdminAuthController::class, 'changePassword']);
        Route::get('/dashboard',       [SuperAdminController::class, 'dashboard']);
        Route::get('/tenants',         [SuperAdminController::class, 'tenants']);
        Route::post('/tenants/{id}/approve',  [SuperAdminController::class, 'approveTenant']);
        Route::post('/tenants/{id}/suspend',  [SuperAdminController::class, 'suspendTenant']);
        Route::delete('/tenants/{id}',        [SuperAdminController::class, 'deleteTenant']);
        Route::get('/modules',            [ModuleManagementController::class, 'index']);
        Route::post('/modules/upload',    [ModuleManagementController::class, 'upload']);
        Route::post('/modules',           [ModuleManagementController::class, 'store']);
        Route::put('/modules/{id}',       [ModuleManagementController::class, 'update']);
        Route::delete('/modules/{id}',    [ModuleManagementController::class, 'destroy']);
    });
});

// Tenant Routes (IdentifyTenant switches DB)
Route::middleware([IdentifyTenant::class])->group(function() {
    Route::get('/tenants/current', [TenantController::class, 'current']);

    Route::prefix('auth')->controller(AuthController::class)->group(function() {
        Route::middleware('quota.enforce')->group(fn() => Route::post('/register','register')->post('/login','login'));
        Route::middleware(AuthenticateToken::class)->group(fn() => Route::post('/logout','logout')->get('/me','me'));
    });

    Route::prefix('users')->middleware([AuthenticateToken::class,'quota.enforce'])->controller(UserController::class)->group(fn()=>Route::get('/','index')->post('/','store')->get('/{id}','show')->put('/{id}','update')->delete('/{id}','destroy'));

    Route::prefix('modules')->middleware(AuthenticateToken::class)->controller(SubscriptionController::class)->group(fn()=>Route::get('/','index')->get('/subscribed','tenantModules')->post('/subscribe','subscribe')->delete('/{moduleKey}','unsubscribe')->get('/{moduleKey}/access','checkAccess'));

    Route::prefix('payment')->group(function() {
        Route::post('/checkout',          [PaymentController::class,'createCheckoutSession'])->middleware(AuthenticateToken::class);
        Route::post('/verify',            [PaymentController::class,'verifyPayment'])->middleware(AuthenticateToken::class);
        Route::get('/history',            [PaymentHistoryController::class,'index'])->middleware(AuthenticateToken::class);
        Route::get('/statistics',         [PaymentHistoryController::class,'statistics'])->middleware(AuthenticateToken::class);
        Route::get('/{id}/invoice',       [PaymentHistoryController::class,'downloadInvoice'])->middleware(AuthenticateToken::class);
    });

    Route::prefix('invoices')->middleware(AuthenticateToken::class)->controller(InvoiceController::class)->group(fn()=>Route::get('/','index')->get('/{id}','show')->get('/{id}/download','download')->post('/{id}/pay','pay'));

    Route::prefix('payment-methods')->middleware(AuthenticateToken::class)->controller(PaymentMethodController::class)->group(fn()=>Route::get('/','index')->post('/','store')->delete('/{id}','destroy')->post('/{id}/default','setDefault'));

    Route::prefix('database')->middleware(AuthenticateToken::class)->controller(TenantDatabaseController::class)->group(fn()=>Route::get('/analytics','analytics')->get('/tables','tables')->get('/growth','growth')->get('/plans','plans'));

    Route::prefix('domains')->middleware(AuthenticateToken::class)->group(function() {
        Route::get('/',    [DomainController::class,'index'])->post('/',[DomainController::class,'store']);
        Route::get('/store/orders',              [DomainStoreController::class,'orders']);
        Route::post('/store/purchase',           [DomainStoreController::class,'purchase']);
        Route::post('/store/verify-purchase',    [DomainStoreController::class,'verifyPurchase']);
        Route::get('/store/search',              [DomainStoreController::class,'search']);
        Route::post('/{id}/verify',              [DomainController::class,'verify']);
        Route::get('/{id}/dns',                  [DomainController::class,'getDNSHosts']);
        Route::post('/{id}/dns',                 [DomainController::class,'updateDNSHosts']);
        Route::delete('/{id}',                   [DomainController::class,'destroy']);
    });

    Route::prefix('ecommerce')->middleware([AuthenticateToken::class,'module.access:ecommerce','quota.enforce'])->group(function() {
        Route::get('/stats', [EcommerceDashboardController::class,'stats']);
        Route::apiResource('/products',  ProductController::class);
        Route::apiResource('/categories',CategoryController::class);
        Route::apiResource('/orders',    OrderController::class);
        Route::apiResource('/customers', CustomerController::class);
    });
});
```
