<?php

namespace App\Services;

use App\Services\NamecheapService;
use Illuminate\Support\Facades\Log;

class DomainSearchService
{
    protected NamecheapService $namecheap;

    public function __construct(NamecheapService $namecheap)
    {
        $this->namecheap = $namecheap;
    }
    /**
     * Check if a domain is available for registration
     * For now, this is a mock implementation. In production, this would call a Registrar API.
     */
    public function checkAvailability(string $domain): array
    {
        try {
            // If no TLD provided (just a keyword), default to .com
            if (!str_contains($domain, '.')) {
                $domain .= '.com';
            }

            // Robust domain validation regex
            if (!preg_match('/^([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,11}$/', $domain)) {
                return [
                    'available' => false,
                    'message' => 'Invalid domain format'
                ];
            }

            $result = $this->namecheap->checkAvailability($domain);
            Log::debug("Namecheap availability for {$domain}:", $result);

            // For non-premium domains, namecheap.domains.check returns price=0.
            // Fetch real pricing via namecheap.users.getPricing instead.
            $price = $result['price'];
            try {
                if (!$result['is_premium'] || $price == 0) {
                    $tld    = strtolower(substr($domain, strrpos($domain, '.') + 1));
                    $prices = $this->namecheap->getPricing([$tld]);
                    $price  = $prices[$tld] ?? $this->getDefaultPrice($domain);
                }
            } catch (\Exception $priceErr) {
                Log::warning("Real pricing fetch failed for {$domain}, using fallback: " . $priceErr->getMessage());
                $price = $this->getDefaultPrice($domain);
            }

            return [
                'domain'     => $result['domain'],
                'available'  => $result['available'],
                'price'      => $price,
                'currency'   => 'USD',
                'is_premium' => $result['is_premium'],
            ];
        } catch (\Exception $e) {
            Log::error('Domain availability check error: ' . $e->getMessage());
            return [
                'domain'     => $domain,
                'available'  => false,
                'price'      => $this->getDefaultPrice($domain),
                'currency'   => 'USD',
                'is_premium' => false,
                'message'    => 'Error checking availability: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get WHOIS data for a domain
     */
    public function getWhoisData(string $domain): array
    {
        // Always do a public DNS lookup first for nameservers (works for any domain)
        $nameservers = [];
        $aRecords = [];
        try {
            $nsRes = dns_get_record($domain, DNS_NS);
            if ($nsRes) {
                $nameservers = array_map(fn($r) => $r['target'], $nsRes);
            }
            $aRes = dns_get_record($domain, DNS_A);
            if ($aRes) {
                $aRecords = array_map(fn($r) => $r['ip'], $aRes);
            }
        } catch (\Exception $dnsErr) {
            Log::warning("Public DNS lookup failed for {$domain}: " . $dnsErr->getMessage());
        }

        try {
            // Attempt WHOIS via public socket (works for any domain)
            $info = $this->namecheap->getWhois($domain);

            // Domain not registered / available
            if (isset($info['is_registered']) && $info['is_registered'] === false) {
                return [
                    'domain'        => $domain,
                    'is_registered' => false,
                    'status'        => 'available',
                    'registrar'     => null,
                    'creation_date' => null,
                    'expiry_date'   => null,
                    'updated_date'  => null,
                    'nameservers'   => $info['nameservers'] ?? [],
                    'raw_text'      => $info['raw'] ?? "Domain: {$domain}\nStatus: Available (Not Registered)",
                ];
            }

            // Merge Namecheap nameservers with public DNS ones
            $allNameservers = array_unique(array_merge($info['nameservers'] ?? [], $nameservers));

            return [
                'domain'        => $domain,
                'registrar'     => $info['registrar'] ?: ($allNameservers ? 'Registered (External)' : 'Unknown'),
                'creation_date' => $info['created'] ?: null,
                'expiry_date'   => $info['expires'] ?: null,
                'updated_date'  => $info['updated'] ?? null,
                'status'        => $info['status'] ?: 'Registered',
                'nameservers'   => $allNameservers,
                'raw_text'      => $info['raw'] ?: implode("\n", [
                    "Domain:    {$domain}",
                    "Status:    Registered",
                    "Nameservers: " . implode(', ', $allNameservers),
                ]),
            ];
        } catch (\Exception $e) {
            Log::warning("Namecheap WHOIS fetch failed for {$domain}: " . $e->getMessage());

            // Build a clean raw text from public DNS data
            $rawLines = [
                "Domain:    {$domain}",
                "Registrar: Unknown (domain not in your Namecheap account)",
                "Status:    Registered (DNS resolving)",
                "",
            ];
            if (!empty($nameservers)) {
                $rawLines[] = "Nameservers (via public DNS):";
                foreach ($nameservers as $ns) {
                    $rawLines[] = "  " . $ns;
                }
            }
            if (!empty($aRecords)) {
                $rawLines[] = "";
                $rawLines[] = "A Records (IP):";
                foreach ($aRecords as $ip) {
                    $rawLines[] = "  " . $ip;
                }
            }
            $rawLines[] = "";
            $rawLines[] = "Note: Full WHOIS data (registrar, dates) is only available for domains";
            $rawLines[] = "registered in your Namecheap account. Privacy protection may also hide data.";

            return [
                'domain'        => $domain,
                'registrar'     => !empty($nameservers) ? 'Registered (External Registrar)' : 'Unknown',
                'creation_date' => 'N/A (Private)',
                'expiry_date'   => 'N/A (Private)',
                'status'        => !empty($nameservers) ? 'Registered' : 'Unknown',
                'nameservers'   => !empty($nameservers) ? $nameservers : [],
                'raw_text'      => implode("\n", $rawLines),
            ];
        }
    }

    /**
     * Get domain suggestions based on a keyword
     */
    public function getSuggestions(string $keyword): array
    {
        try {
            $suggestions = $this->namecheap->getSuggestions($keyword);
            Log::debug("Suggestions for {$keyword}:", $suggestions);
            return $suggestions;
        } catch (\Exception $e) {
            Log::error('Suggestions error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Mock pricing based on TLD
     */
    private function getDefaultPrice(string $domain): float
    {
        if (str_ends_with($domain, '.com')) return 12.99;
        if (str_ends_with($domain, '.net')) return 14.99;
        if (str_ends_with($domain, '.io')) return 49.99;
        if (str_ends_with($domain, '.shop')) return 8.99;
        return 15.00;
    }
}
