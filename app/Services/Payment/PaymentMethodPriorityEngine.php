<?php

namespace App\Services\Payment;

/**
 * Country + Device + Amount + History-aware payment method priority engine.
 *
 * Usage:
 *   $methods = (new PaymentMethodPriorityEngine)->resolve($country, $device, $amount, $userId);
 *   // ['stc_pay', 'mada', 'cod']
 */
class PaymentMethodPriorityEngine
{
    // All known method keys
    const MADA        = 'mada';
    const VISA        = 'visa_mastercard';
    const APPLE_PAY   = 'apple_pay';
    const GOOGLE_PAY  = 'google_pay';
    const STC_PAY     = 'stc_pay';
    const TABBY       = 'tabby';
    const TAMARA      = 'tamara';
    const POSTPAY     = 'postpay';
    const COD         = 'cod';
    const BANK_XFER   = 'bank_transfer';

    // BNPL min/max eligibility amounts (USD equiv)
    const BNPL_MIN    = 50;
    const BNPL_MAX    = 5000;

    // COD max amount (SAR/AED)
    const COD_MAX_SA  = 1000;
    const COD_MAX_UAE = 500;

    /**
     * Resolve priority-ordered payment methods.
     *
     * @param string      $country   ISO-2: 'SA' | 'AE' | ...
     * @param string      $device    'ios' | 'android' | 'desktop'
     * @param float       $amount    Cart/subscription amount in local currency
     * @param int|null    $userId    For personalised history scoring
     * @return array<string>         Ordered list of method keys
     */
    public function resolve(string $country, string $device, float $amount, ?int $userId = null): array
    {
        $country = strtoupper($country);
        $device  = strtolower($device);

        $methods = match ($country) {
            'SA'    => $this->saudiMethods($device, $amount, $userId),
            'AE'    => $this->uaeMethods($device, $amount, $userId),
            default => $this->defaultMethods($device, $amount),
        };

        // Filter by COD risk if user has bad history
        if ($userId && $this->hasCODRisk($userId)) {
            $methods = array_filter($methods, fn($m) => $m !== self::COD);
        }

        return array_values($methods);
    }

    /**
     * Saudi Arabia (KSA) priority logic.
     * MADA is mandatory and shown first for cards.
     */
    protected function saudiMethods(string $device, float $amount, ?int $userId): array
    {
        $methods = [];

        // Mobile-first: STC Pay leads on mobile/iOS/Android
        if (in_array($device, ['ios', 'android', 'mobile'])) {
            $methods[] = self::STC_PAY;
        }

        // MADA always required for SA (before Visa/MC)
        $methods[] = self::MADA;
        $methods[] = self::VISA;

        // Apple Pay on iOS
        if ($device === 'ios') {
            array_splice($methods, 0, 0, [self::APPLE_PAY]); // prepend
        }

        // BNPL for mid-range amounts (50–5000 SAR)
        if ($amount >= self::BNPL_MIN && $amount <= self::BNPL_MAX) {
            $methods[] = self::TAMARA;  // Tamara strong in KSA
            $methods[] = self::TABBY;
        }

        // COD for lower amounts
        if ($amount <= self::COD_MAX_SA) {
            $methods[] = self::COD;
        }

        return array_unique($methods);
    }

    /**
     * UAE priority logic.
     * Apple Pay + international cards dominate.
     */
    protected function uaeMethods(string $device, float $amount, ?int $userId): array
    {
        $methods = [];

        // Apple Pay leads on iPhone
        if ($device === 'ios') {
            $methods[] = self::APPLE_PAY;
        }

        if ($device === 'android') {
            $methods[] = self::GOOGLE_PAY;
        }

        $methods[] = self::VISA;
        $methods[] = self::MADA; // Some UAE users have MADA

        // BNPL — very strong in UAE (fashion, electronics, SaaS)
        if ($amount >= self::BNPL_MIN && $amount <= self::BNPL_MAX) {
            $methods[] = self::TABBY;    // Tabby strong in UAE
            $methods[] = self::TAMARA;
            $methods[] = self::POSTPAY;
        }

        // Corporate / high value → bank transfer
        if ($amount > 5000) {
            $methods[] = self::BANK_XFER;
        }

        // COD for low amounts
        if ($amount <= self::COD_MAX_UAE) {
            $methods[] = self::COD;
        }

        return array_unique($methods);
    }

    /**
     * Default (other countries) fallback.
     */
    protected function defaultMethods(string $device, float $amount): array
    {
        $methods = [];
        if ($device === 'ios')     $methods[] = self::APPLE_PAY;
        if ($device === 'android') $methods[] = self::GOOGLE_PAY;
        $methods[] = self::VISA;

        if ($amount >= self::BNPL_MIN && $amount <= self::BNPL_MAX) {
            $methods[] = self::TABBY;
        }
        return array_unique($methods);
    }

    /**
     * Check if user has COD risk (high return rate / blacklisted).
     */
    protected function hasCODRisk(int $userId): bool
    {
        return \App\Models\CODRiskProfile::where('user_id', $userId)
            ->where(fn($q) => $q->where('is_blacklisted', true)->orWhere('return_rate', '>', 0.4))
            ->exists();
    }

    /**
     * Return display labels for the frontend.
     */
    public function getMethodLabel(string $key): array
    {
        return match ($key) {
            self::MADA      => ['label' => 'mada',         'label_ar' => 'مدى',        'icon' => 'mada'],
            self::VISA      => ['label' => 'Card',          'label_ar' => 'بطاقة',      'icon' => 'card'],
            self::APPLE_PAY => ['label' => 'Apple Pay',     'label_ar' => 'Apple Pay',  'icon' => 'apple_pay'],
            self::GOOGLE_PAY=> ['label' => 'Google Pay',    'label_ar' => 'Google Pay', 'icon' => 'google_pay'],
            self::STC_PAY   => ['label' => 'STC Pay',       'label_ar' => 'STC Pay',    'icon' => 'stc_pay'],
            self::TABBY     => ['label' => 'Tabby',         'label_ar' => 'تابي',       'icon' => 'tabby'],
            self::TAMARA    => ['label' => 'Tamara',        'label_ar' => 'تمارا',      'icon' => 'tamara'],
            self::POSTPAY   => ['label' => 'Postpay',       'label_ar' => 'Postpay',    'icon' => 'postpay'],
            self::COD       => ['label' => 'Cash on Delivery','label_ar'=>'الدفع عند الاستلام','icon'=>'cod'],
            self::BANK_XFER => ['label' => 'Bank Transfer', 'label_ar' => 'تحويل بنكي', 'icon' => 'bank'],
            default         => ['label' => $key,            'label_ar' => $key,         'icon' => 'card'],
        };
    }
}
