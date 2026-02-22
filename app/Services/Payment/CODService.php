<?php

namespace App\Services\Payment;

use App\Models\CODOrder;
use App\Models\CODRiskProfile;
use Illuminate\Support\Facades\Log;

/**
 * Cash on Delivery Service — Middle East e-commerce reality.
 *
 * COD is still very common in KSA + UAE, especially for:
 * - First-time buyers with no trust in online payment
 * - Rural areas
 * - Low-income segments
 *
 * Risk engine prevents abuse:
 * - Users with >40% return rate → blocked or OTP required
 * - Per-user COD limits (KSA: 1000 SAR, UAE: 500 AED)
 * - Blacklist for fraud / fake addresses
 */
class CODService
{
    const RISK_THRESHOLD_RETURN  = 0.40;  // 40% return rate = high risk
    const RISK_THRESHOLD_CANCELS = 3;     // 3+ no-shows = blacklist candidate
    const COD_LIMIT_SAR          = 1000;
    const COD_LIMIT_AED          = 500;

    // ── Eligibility ────────────────────────────────────────────────────────

    /**
     * Check if COD is available for this user + amount.
     */
    public function isAvailable(int $userId, float $amount, string $currency): array
    {
        $limit = $currency === 'AED' ? self::COD_LIMIT_AED : self::COD_LIMIT_SAR;

        if ($amount > $limit) {
            return [
                'available' => false,
                'reason'    => "COD limit is {$currency} {$limit}. Amount exceeds limit.",
            ];
        }

        $risk = $this->assessRisk($userId);

        if ($risk['blacklisted']) {
            return [
                'available' => false,
                'reason'    => 'COD not available due to account history.',
            ];
        }

        return [
            'available'     => true,
            'requires_otp'  => $risk['high_risk'],  // High-risk users must OTP first
            'risk_level'    => $risk['level'],
        ];
    }

    // ── Risk Assessment ────────────────────────────────────────────────────

    /**
     * Assess COD risk for a user.
     * Factors: return rate, cancellation count, fraud flags, address validity.
     */
    public function assessRisk(int $userId): array
    {
        $profile = CODRiskProfile::firstOrCreate(
            ['user_id' => $userId],
            ['return_rate' => 0, 'cancellation_count' => 0, 'is_blacklisted' => false, 'risk_score' => 0]
        );

        $score = 0;

        if ($profile->is_blacklisted) {
            return ['blacklisted' => true, 'high_risk' => true, 'level' => 'blacklisted', 'score' => 100];
        }

        if ($profile->return_rate > self::RISK_THRESHOLD_RETURN) {
            $score += 50;
        }

        if ($profile->cancellation_count >= self::RISK_THRESHOLD_CANCELS) {
            $score += 30;
        }

        $score += $profile->risk_score;

        $level     = $score >= 80 ? 'high' : ($score >= 40 ? 'medium' : 'low');
        $highRisk  = $score >= 50;

        return [
            'blacklisted' => false,
            'high_risk'   => $highRisk,
            'level'       => $level,
            'score'       => $score,
        ];
    }

    // ── Order Creation ─────────────────────────────────────────────────────

    /**
     * Create COD order — status starts as 'pending_payment'.
     */
    public function createOrder(array $data): CODOrder
    {
        $risk  = $this->assessRisk($data['user_id']);
        $order = CODOrder::create([
            'tenant_id'          => $data['tenant_id'],
            'user_id'            => $data['user_id'],
            'amount'             => $data['amount'],
            'currency'           => $data['currency'] ?? 'SAR',
            'status'             => 'pending_payment',
            'risk_score'         => $risk['score'],
            'otp_required'       => $risk['high_risk'],
            'delivery_address'   => $data['address'],
            'notes'              => $data['notes'] ?? '',
        ]);

        // If high risk, generate OTP before shipment is allowed
        if ($risk['high_risk']) {
            $this->generateOTP($order);
            Log::warning("COD: High-risk user {$data['user_id']}, OTP required before shipment");
        }

        Log::info("COD order created: #{$order->id} amount={$data['amount']} risk={$risk['level']}");

        return $order;
    }

    // ── OTP before shipment ────────────────────────────────────────────────

    /**
     * Generate OTP for high-risk COD orders.
     * OTP must be verified before delivery is dispatched.
     */
    public function generateOTP(CODOrder $order): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $order->update([
            'otp_code'       => bcrypt($otp),
            'otp_expires_at' => now()->addMinutes(30),
        ]);

        // Send OTP via SMS (use your SMS provider here)
        // SmsService::send($order->user->phone, "Your delivery OTP: {$otp}");

        Log::info("COD OTP generated for order #{$order->id}");

        return $otp; // Return for testing; in prod only send via SMS
    }

    /**
     * Verify OTP before allowing shipment dispatch.
     */
    public function verifyOTP(CODOrder $order, string $otp): bool
    {
        if ($order->otp_expires_at < now()) {
            return false; // Expired
        }

        $valid = \Hash::check($otp, $order->otp_code);

        if ($valid) {
            $order->update(['otp_verified_at' => now()]);
            Log::info("COD OTP verified for order #{$order->id}");
        }

        return $valid;
    }

    // ── Delivery Confirmation ──────────────────────────────────────────────

    /**
     * Mark COD payment as collected after successful delivery.
     */
    public function confirmDelivery(CODOrder $order, string $deliveryAgentId): void
    {
        $order->update([
            'status'              => 'payment_collected',
            'collected_at'        => now(),
            'delivery_agent_id'   => $deliveryAgentId,
        ]);

        // Improve user's risk profile on successful payment
        $this->updateRiskProfile($order->user_id, 'success');

        Log::info("COD payment collected: order #{$order->id}");
    }

    /**
     * Mark COD order as failed (customer refused / not home).
     */
    public function reportFailure(CODOrder $order, string $reason): void
    {
        $order->update([
            'status'         => 'failed',
            'failure_reason' => $reason,
        ]);

        $this->updateRiskProfile($order->user_id, 'failure');

        Log::warning("COD failure: order #{$order->id} reason={$reason}");
    }

    // ── Risk Profile Updates ───────────────────────────────────────────────

    protected function updateRiskProfile(int $userId, string $outcome): void
    {
        $profile  = CODRiskProfile::firstOrCreate(['user_id' => $userId]);
        $total    = CODOrder::where('user_id', $userId)->count();
        $failed   = CODOrder::where('user_id', $userId)->whereIn('status', ['failed', 'returned'])->count();

        $returnRate = $total > 0 ? $failed / $total : 0;
        $cancels    = CODOrder::where('user_id', $userId)->where('status', 'failed')->count();

        $profile->update([
            'return_rate'        => $returnRate,
            'cancellation_count' => $cancels,
            'is_blacklisted'     => $cancels >= 5 || $returnRate > 0.6, // Auto-blacklist
        ]);
    }
}
