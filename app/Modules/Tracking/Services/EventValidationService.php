<?php

namespace App\Modules\Tracking\Services;

/**
 * Event Validation Service.
 *
 * Validates events against Meta CAPI schema before sending.
 * Provides:
 *   - Required field validation
 *   - Event name validation (standard vs custom)
 *   - User data completeness checks
 *   - Custom data type validation
 *   - Quality feedback with actionable recommendations
 */
class EventValidationService
{
    private const REQUIRED_FIELDS = ['event_name', 'event_time', 'action_source'];

    private const WEBSITE_REQUIRED = ['event_source_url', 'user_data.client_user_agent'];

    private const STANDARD_EVENTS = [
        'AddPaymentInfo', 'AddToCart', 'AddToWishlist', 'CompleteRegistration',
        'Contact', 'CustomizeProduct', 'Donate', 'FindLocation',
        'InitiateCheckout', 'Lead', 'PageView', 'Purchase',
        'Schedule', 'Search', 'StartTrial', 'SubmitApplication',
        'Subscribe', 'ViewContent',
    ];

    private const ACTION_SOURCES = [
        'website', 'app', 'email', 'phone_call', 'chat',
        'physical_store', 'system_generated', 'business_messaging', 'other',
    ];

    /**
     * Validate an event and return a detailed report.
     */
    public function validate(array $event): array
    {
        $errors   = [];
        $warnings = [];
        $info     = [];

        // ── Required fields ───────────────────────────────
        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty(data_get($event, $field))) {
                $errors[] = [
                    'field'   => $field,
                    'message' => "Required field '{$field}' is missing",
                    'fix'     => "Add '{$field}' to your event payload",
                ];
            }
        }

        // ── event_name validation ─────────────────────────
        $eventName = $event['event_name'] ?? '';
        if ($eventName && !in_array($eventName, self::STANDARD_EVENTS)) {
            $info[] = [
                'field'   => 'event_name',
                'message' => "'{$eventName}' is a custom event (not a Meta standard event)",
                'fix'     => 'Custom events work but standard events get better optimization',
            ];
        }

        // ── action_source validation ──────────────────────
        $actionSource = $event['action_source'] ?? '';
        if ($actionSource && !in_array($actionSource, self::ACTION_SOURCES)) {
            $errors[] = [
                'field'   => 'action_source',
                'message' => "Invalid action_source: '{$actionSource}'",
                'fix'     => 'Use one of: ' . implode(', ', self::ACTION_SOURCES),
            ];
        }

        // ── Website-specific requirements ─────────────────
        if ($actionSource === 'website') {
            foreach (self::WEBSITE_REQUIRED as $field) {
                if (empty(data_get($event, $field))) {
                    $warnings[] = [
                        'field'   => $field,
                        'message' => "'{$field}' is required for website events",
                        'fix'     => "Include '{$field}' for better event matching",
                    ];
                }
            }
        }

        // ── event_time validation ─────────────────────────
        $eventTime = $event['event_time'] ?? null;
        if ($eventTime) {
            $maxAge = 7 * 24 * 3600; // 7 days
            if ($eventTime < (time() - $maxAge)) {
                $errors[] = [
                    'field'   => 'event_time',
                    'message' => 'event_time is older than 7 days (Meta rejects these)',
                    'fix'     => 'Use a Unix timestamp within the last 7 days',
                ];
            }
            if ($eventTime > (time() + 300)) { // 5 min future tolerance
                $warnings[] = [
                    'field'   => 'event_time',
                    'message' => 'event_time is in the future',
                    'fix'     => 'Ensure your server clock is properly synced',
                ];
            }
        }

        // ── user_data validation ──────────────────────────
        $userData = $event['user_data'] ?? [];
        if (empty($userData)) {
            $errors[] = [
                'field'   => 'user_data',
                'message' => 'user_data is required and empty',
                'fix'     => 'Add at least email (em) and client_ip_address',
            ];
        } else {
            $this->validateUserData($userData, $warnings, $info);
        }

        // ── event_id (dedup) validation ──────────────────
        if (empty($event['event_id'])) {
            $warnings[] = [
                'field'   => 'event_id',
                'message' => 'No event_id provided — deduplication with Pixel will not work',
                'fix'     => 'Generate a unique event_id and use the same value in both Pixel and CAPI',
            ];
        }

        // ── Purchase-specific validation ─────────────────
        if (($event['event_name'] ?? '') === 'Purchase') {
            $this->validatePurchaseEvent($event, $warnings);
        }

        // ── Build result ─────────────────────────────────
        $isValid = empty($errors);

        return [
            'valid'    => $isValid,
            'errors'   => $errors,
            'warnings' => $warnings,
            'info'     => $info,
            'summary'  => $this->buildSummary($errors, $warnings, $info),
        ];
    }

    /**
     * Validate user_data fields.
     */
    private function validateUserData(array $userData, array &$warnings, array &$info): void
    {
        // Check for unhashed PII
        $hashableFields = ['em', 'ph', 'fn', 'ln', 'ge', 'db', 'ct', 'st', 'zp', 'country'];
        foreach ($hashableFields as $field) {
            $value = $userData[$field] ?? null;
            if ($value && !preg_match('/^[a-f0-9]{64}$/', $value)) {
                $warnings[] = [
                    'field'   => "user_data.{$field}",
                    'message' => "'{$field}' appears unhashed — will be auto-hashed by our service",
                    'fix'     => 'Pre-hash with SHA-256 for best performance, or let our CAPI service handle it',
                ];
            }
        }

        // Recommend high-value matching fields
        $highValue = ['em', 'ph', 'fbc', 'fbp', 'client_ip_address', 'client_user_agent'];
        $missing = array_filter($highValue, fn($f) => empty($userData[$f]));

        if (count($missing) > 3) {
            $warnings[] = [
                'field'   => 'user_data',
                'message' => 'Low match quality: missing ' . implode(', ', $missing),
                'fix'     => 'Provide email (em), phone (ph), and browser identifiers for best matching',
            ];
        }
    }

    /**
     * Validate Purchase-specific event data.
     */
    private function validatePurchaseEvent(array $event, array &$warnings): void
    {
        $customData = $event['custom_data'] ?? [];

        if (empty($customData['value'])) {
            $warnings[] = [
                'field'   => 'custom_data.value',
                'message' => 'Purchase event missing value — required for ROAS optimization',
                'fix'     => 'Include the purchase amount in custom_data.value',
            ];
        }

        if (empty($customData['currency'])) {
            $warnings[] = [
                'field'   => 'custom_data.currency',
                'message' => 'Purchase event missing currency — required for accurate ROAS',
                'fix'     => 'Include ISO 4217 currency code (e.g., USD) in custom_data.currency',
            ];
        }
    }

    /**
     * Build human-readable summary.
     */
    private function buildSummary(array $errors, array $warnings, array $info): string
    {
        if (empty($errors) && empty($warnings)) {
            return '✅ Event is fully valid and optimized for Meta CAPI.';
        }

        $parts = [];
        if (!empty($errors)) {
            $parts[] = count($errors) . ' error(s)';
        }
        if (!empty($warnings)) {
            $parts[] = count($warnings) . ' warning(s)';
        }
        if (!empty($info)) {
            $parts[] = count($info) . ' note(s)';
        }

        return implode(', ', $parts) . '. Fix errors before sending.';
    }

    /**
     * Batch validate multiple events.
     */
    public function validateBatch(array $events): array
    {
        $results = [];
        $totalErrors = 0;
        $totalWarnings = 0;

        foreach ($events as $i => $event) {
            $result = $this->validate($event);
            $result['index'] = $i;
            $results[] = $result;
            $totalErrors += count($result['errors']);
            $totalWarnings += count($result['warnings']);
        }

        return [
            'total_events'    => count($events),
            'valid_events'    => count(array_filter($results, fn($r) => $r['valid'])),
            'total_errors'    => $totalErrors,
            'total_warnings'  => $totalWarnings,
            'events'          => $results,
        ];
    }
}
