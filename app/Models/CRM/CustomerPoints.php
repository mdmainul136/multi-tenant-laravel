<?php

namespace App\Models\CRM;

use App\Models\TenantBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CustomerPoints extends TenantBaseModel
{
    protected $table = 'customer_points';

    protected $fillable = [
        'customer_id',
        'points_balance',
        'lifetime_earned',
        'lifetime_redeemed',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function addPoints(int $points, string $type, string $description, ?string $referenceType = null, ?int $referenceId = null)
    {
        return DB::transaction(function () use ($points, $type, $description, $referenceType, $referenceId) {
            $this->increment('points_balance', $points);
            if ($points > 0) {
                $this->increment('lifetime_earned', $points);
            }

            return LoyaltyTransaction::create([
                'customer_id'    => $this->customer_id,
                'points'         => $points,
                'type'           => $type,
                'description'    => $description,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
            ]);
        });
    }

    public function redeemPoints(int $points, string $description, ?int $orderId = null)
    {
        return $this->addPoints(-abs($points), 'redeem', $description, 'order', $orderId);
    }
}
