<?php

namespace App\Modules\CrossBorderIOR\Services;

class ComplianceSafetyService
{
    /**
     * Restricted keywords and reasons.
     */
    private array $restrictedKeywords = [
        'battery'    => 'Power sources are restricted for air shipping (DG rules).',
        'perfume'    => 'Flammable liquids are restricted.',
        'cologne'    => 'Flammable liquids are restricted.',
        'explosive'  => 'Prohibited item.',
        'poison'     => 'Hazardous material.',
        'gold'       => 'High-value metal requiring special insurance/license.',
        'knife'      => 'Sharp weapon; restricted import.',
        'pepper spray' => 'Self-defense weapon; restricted.',
        'drone'      => 'Communication equipment; may require BTRC license in BD.',
    ];

    /**
     * Check if a product is restricted based on global and local policies.
     */
    public function __construct(
        private GlobalGovernanceService $governance
    ) {}

    public function check(string $title, string $description = '', ?string $originCountry = null): array
    {
        $text = strtolower($title . ' ' . $description);
        $found = [];

        // 1. Fetch Global Policy from Landlord (via Service)
        $globalRules = $this->governance->getGlobalRestrictedItems($originCountry);
        
        foreach ($globalRules as $rule) {
            if (str_contains($text, strtolower($rule->keyword))) {
                $found[] = [
                    'keyword' => $rule->keyword,
                    'reason'  => $rule->reason,
                    'source'  => 'global_policy',
                    'severity'=> $rule->severity
                ];
            }
        }

        // 2. Fallback / Hardcoded defaults if nothing found in DB
        if (empty($found)) {
            foreach ($this->restrictedKeywords as $keyword => $reason) {
                if (str_contains($text, $keyword)) {
                    $found[] = [
                        'keyword' => $keyword,
                        'reason'  => $reason,
                        'source'  => 'system_default',
                        'severity'=> 'warning'
                    ];
                }
            }
        }

        return [
            'is_restricted' => !empty($found),
            'flags' => $found,
            'origin_country' => $originCountry,
            'advice' => !empty($found) ? 'Consult with operations before purchasing.' : 'Safe for standard shipping.'
        ];
    }
}
