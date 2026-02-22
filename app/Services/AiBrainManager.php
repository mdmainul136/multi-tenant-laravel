<?php

namespace App\Services;

use App\Models\Tenant;

class AiBrainManager
{
    /**
     * Construct a contextual system prompt ("The Brain") for the AI.
     */
    public function constructBrain(Tenant $tenant): string
    {
        $businessType = $tenant->business_type ?? 'General Retail';
        $region = $tenant->country ?? 'Global';
        $modules = $tenant->tenantModules()->pluck('module_key')->toArray();
        $moduleList = !empty($modules) ? implode(', ', $modules) : 'Basic Storefront';

        $prompt = "You are the 'AI Storefront Brain' for a professional multi-tenant SaaS platform.\n";
        $prompt .= "Your goal is to generate branding and content for a merchant with the following profile:\n";
        $prompt .= "- Business Type: {$businessType}\n";
        $prompt .= "- Region: {$region}\n";
        $prompt .= "- Active Modules: {$moduleList}\n";

        // Inject persistent training notes
        $aiSettings = \App\Models\TenantAiSetting::where('tenant_id', $tenant->tenant_id)->first();
        if ($aiSettings && !empty($aiSettings->training_notes)) {
            $prompt .= "- MERCHANT CUSTOM PREFERENCES (TRAIN THE BRAIN): {$aiSettings->training_notes}\n";
        }

        $prompt .= "\n";

        $prompt .= "### GUIDELINES:\n";
        
        // Regional Context
        if (stripos($region, 'Saudi') !== false || stripos($region, 'KSA') !== false) {
            $prompt .= "- Region focus: Saudi Arabia. Use high-end, professional tones. Emphasize trust and local value.\n";
            $prompt .= "- If generating colors, consider elegant deep greens, golds, or modern minimalist palettes popular in the GCC.\n";
        } else {
            $prompt .= "- Region focus: Global. Use modern, universally appealing design standards.\n";
        }

        // Vertical Context
        $prompt .= $this->getVerticalInstructions($businessType);

        // Module Context
        if (in_array('ecommerce', $modules)) {
            $prompt .= "- Context: E-commerce active. Focus on conversion-driven headlines and clear CTAs.\n";
        }
        if (in_array('pos', $modules)) {
            $prompt .= "- Context: POS active. Mention physical presence or 'Order Online, Pick Up in Store' if appropriate.\n";
        }

        $prompt .= "\n### RESPONSE FORMAT:\n";
        $prompt .= "Return ONLY a valid JSON object. DO NOT include any markdown or text outside the JSON.\n";
        $prompt .= "JSON Schema:\n";
        $prompt .= "{\n";
        $prompt .= '  "brandName": "A catchy, relevant business name",' . "\n";
        $prompt .= '  "primaryColor": "Hex code (e.g., #10b981)",' . "\n";
        $prompt .= '  "headingFont": "A Google Font name (e.g., Outfit, Inter, Montserrat)",' . "\n";
        $prompt .= '  "heroHeading": "A powerful 4-6 word headline",' . "\n";
        $prompt .= '  "heroSubtext": "A detailed 12-18 word sub-description",' . "\n";
        $prompt .= '  "accentColor": "Hex code for buttons/accents"' . "\n";
        $prompt .= "}";

        return $prompt;
    }

    /**
     * Specialized instructions based on business type.
     */
    private function getVerticalInstructions(string $type): string
    {
        $type = strtolower($type);
        
        switch (true) {
            case strpos($type, 'pharmacy') !== false || strpos($type, 'health') !== false:
                return "- Vertical: Pharmacy/Health. Use clean, sterile, and trustworthy tones. Greens or medical blues are preferred.\n";
            
            case strpos($type, 'coffee') !== false || strpos($type, 'cafe') !== false:
                return "- Vertical: F&B / Cafe. Use warm, inviting, and sensory language. Earth tones or vibrant accent colors work well.\n";
            
            case strpos($type, 'fashion') !== false || strpos($type, 'clothing') !== false:
                return "- Vertical: Fashion/Retail. Use trendy, bold, and high-energy language. High contrast typography is recommended.\n";
            
            case strpos($type, 'real estate') !== false:
                return "- Vertical: Real Estate. Focus on 'Home', 'Luxury', and 'Investments'. Professional serif or modern sans-serif fonts.\n";

            default:
                return "- Vertical: General Retail. Use professional and clear language suitable for a wide audience.\n";
        }
    }
}
