<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Register a new user in the tenant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        // Validation
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:6',
        ]);

        try {
            // Check if user exists in tenant database
            $existingUser = DB::connection('tenant_dynamic')
                ->table('users')
                ->where('email', $validated['email'])
                ->first();

            if ($existingUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User already exists with this email',
                ], 400);
            }

            // Create user
            $userId = DB::connection('tenant_dynamic')
                ->table('users')
                ->insertGetId([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            // Get user data
            $user = DB::connection('tenant_dynamic')
                ->table('users')
                ->where('id', $userId)
                ->first();

            // Create token (we'll use a simple approach since Sanctum needs Eloquent model)
            // For now, we'll use jwt-style token generation
            $token = $this->generateToken($userId, $request->input('tenant')['id']);

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'token' => $token,
                ],
            ], 201);
        } catch (\Exception $e) {
            \Log::error('User registration error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error registering user',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Login user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Validation
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        try {
            // Get user from tenant database
            $user = DB::connection('tenant_dynamic')
                ->table('users')
                ->where('email', $validated['email'])
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                ], 401);
            }

            // Check password
            if (!Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                ], 401);
            }

            // Generate token
            $token = $this->generateToken($user->id, $request->input('tenant')['id']);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'token' => $token,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Login error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error logging in',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Logout user (revoke token).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // For simple token-based auth, we'll just return success
        // In production, you'd want to invalidate the token in a blacklist
        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ]);
    }

    /**
     * Get current authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        try {
            $userId = $request->user_id ?? null;

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $user = DB::connection('tenant_dynamic')
                ->table('users')
                ->where('id', $userId)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Get user error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching user',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Generate a JWT-style token.
     *
     * @param  int  $userId
     * @param  string  $tenantId
     * @return string
     */
    protected function generateToken(int $userId, string $tenantId): string
    {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'id' => $userId,
            'tenantId' => $tenantId,
            'exp' => time() + (7 * 24 * 60 * 60), // 7 days
        ]));
        $signature = hash_hmac('sha256', "$header.$payload", config('app.key'), true);
        $signature = base64_encode($signature);

        return "$header.$payload.$signature";
    }
}
