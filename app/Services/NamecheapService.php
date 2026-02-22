<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NamecheapService
{
    protected string $apiKey;
    protected string $username;
    protected string $apiUser;
    protected string $clientIp;
    protected string $baseUrl;

    public function __construct()
    {
        $config = config('services.namecheap');
        $this->apiKey = $config['api_key'] ?? '';
        $this->username = $config['username'] ?? '';
        $this->apiUser = $config['api_user'] ?? '';
        $this->clientIp = $config['client_ip'] ?? '127.0.0.1';
        
        $sandbox = $config['sandbox'] ?? true;
        $this->baseUrl = $sandbox 
            ? 'https://api.sandbox.namecheap.com/xml.response'
            : 'https://api.namecheap.com/xml.response';
    }

    /**
     * Common method to call Namecheap API
     */
    protected function call(string $command, array $params = [])
    {
        $defaultParams = [
            'ApiUser' => $this->apiUser,
            'ApiKey' => $this->apiKey,
            'UserName' => $this->username,
            'ClientIp' => $this->clientIp,
            'Command' => $command,
        ];

        $response = Http::timeout(60)->get($this->baseUrl, array_merge($defaultParams, $params));

        if ($response->failed()) {
            Log::error("Namecheap API Error ({$command}): " . $response->body());
            throw new \Exception("Namecheap API Request Failed");
        }

        $xml = simplexml_load_string($response->body());
        
        if ($xml->Errors->Error) {
            $error = (string) $xml->Errors->Error;
            Log::error("Namecheap API Error ({$command}): " . $error);
            throw new \Exception("Namecheap Error: " . $error);
        }

        return $xml;
    }

    /**
     * Check domain availability
     */
    public function checkAvailability(string $domain)
    {
        $xml = $this->call('namecheap.domains.check', [
            'DomainList' => $domain
        ]);

        $result = $xml->CommandResponse->DomainCheckResult ?? null;
        
        if (!$result) {
            throw new \Exception("Invalid response from Namecheap: DomainCheckResult missing.");
        }

        return [
            'domain' => (string) $result['Domain'],
            'available' => (string) $result['Available'] === 'true',
            'is_premium' => (string) $result['IsPremiumName'] === 'true',
            'price' => (float) ($result['PremiumRegistrationPrice'] ?? 0),
        ];
    }

    /**
     * Get domain suggestions with real pricing
     */
    public function getSuggestions(string $domain)
    {
        $isSandbox = config('services.namecheap.sandbox') ?? true;
        $tlds = $isSandbox ? ['com', 'net', 'org'] : ['com', 'net', 'org', 'io', 'shop', 'app', 'xyz'];
        $sld = explode('.', $domain)[0];
        $domainList = array_map(fn($tld) => "{$sld}.{$tld}", $tlds);

        $xml = $this->call('namecheap.domains.check', [
            'DomainList' => implode(',', $domainList)
        ]);

        // Fetch real prices for all TLDs in one batch
        $prices = $this->getPricing($tlds);

        $suggestions = [];
        $checkResults = $xml->CommandResponse->DomainCheckResult ?? [];
        
        foreach ($checkResults as $result) {
            if ((string) $result['Available'] === 'true') {
                $tld = strtolower(substr((string) $result['Domain'], strrpos((string) $result['Domain'], '.') + 1));
                $isPremium = (string) $result['IsPremiumName'] === 'true';
                $price = $isPremium
                    ? (float) ($result['PremiumRegistrationPrice'] ?? 0)
                    : ($prices[$tld] ?? 15.00);

                $suggestions[] = [
                    'domain'     => (string) $result['Domain'],
                    'available'  => true,
                    'is_premium' => $isPremium,
                    'price'      => $price,
                ];
            }
        }

        return array_slice($suggestions, 0, 5);
    }

    /**
     * Get real registration pricing per TLD from Namecheap.
     * Results are cached for 1 hour.
     *
     * @param  array $tlds  e.g. ['com', 'net', 'org']
     * @return array        e.g. ['com' => 8.88, 'net' => 10.98]
     */
    public function getPricing(array $tlds = []): array
    {
        $cacheKey = 'namecheap_pricing_' . md5(implode(',', $tlds));

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 3600, function () use ($tlds) {
            try {
                $params = [
                    'ProductType'     => 'DOMAIN',
                    'ProductCategory' => 'REGISTER',
                    'ActionName'      => 'REGISTER',
                ];
                if (!empty($tlds)) {
                    $params['ProductName'] = implode(',', array_map('strtoupper', $tlds));
                }

                $xml = $this->call('namecheap.users.getPricing', $params);

                $prices = [];
                $productTypes = $xml->CommandResponse->UserGetPricingResult->ProductType ?? null;
                if (!$productTypes) return [];

                foreach ($productTypes as $productType) {
                    foreach ($productType->ProductCategory as $category) {
                        foreach ($category->Product as $product) {
                            $tld   = strtolower((string) $product['Name']);
                            $price = null;
                            foreach ($product->Price as $p) {
                                if ((string) $p['Duration'] === '1' && (string) $p['DurationType'] === 'YEAR') {
                                    $price = (float) $p['YourPrice'];
                                    break;
                                }
                            }
                            if ($price !== null) {
                                $prices[$tld] = $price;
                            }
                        }
                    }
                }

                return $prices;
            } catch (\Exception $e) {
                Log::warning('Namecheap getPricing failed: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Register a domain
     */
    public function registerDomain(string $domain, int $years = 1, array $contactInfo = [])
    {
        // Namecheap requires extensive contact info
        $params = array_merge([
            'DomainName' => $domain,
            'Years'      => $years,
        ], $this->formatContactParams($contactInfo));

        $xml = $this->call('namecheap.domains.create', $params);

        return $xml->CommandResponse->DomainCreateResult;
    }

    /**
     * Renew a domain via Namecheap API
     *
     * @return array  ['domain' => ..., 'years' => ..., 'expiry_date' => ..., 'order_id' => ...]
     */
    public function renewDomain(string $domain, int $years = 1): array
    {
        $xml = $this->call('namecheap.domains.renew', [
            'DomainName' => $domain,
            'Years'      => $years,
        ]);

        $result = $xml->CommandResponse->DomainRenewResult;

        // Namecheap returns ExpiredDate in the response
        $expiryRaw = (string) ($result['ExpiredDate'] ?? '');
        $expiry    = null;
        if ($expiryRaw) {
            try {
                $expiry = (new \DateTime($expiryRaw))->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                $expiry = null;
            }
        }

        return [
            'domain'      => (string) ($result['DomainName'] ?? $domain),
            'years'       => $years,
            'expiry_date' => $expiry,
            'order_id'    => (string) ($result['OrderID'] ?? ''),
            'renewed'     => (string) ($result['Renewed'] ?? 'false') === 'true',
        ];
    }

    /**
     * Get DNS Host Records
     */
    public function getDNSHostRecords(string $sld, string $tld)
    {
        $xml = $this->call('namecheap.domains.dns.getHosts', [
            'SLD' => $sld,
            'TLD' => $tld
        ]);

        $hosts = [];
        foreach ($xml->CommandResponse->DomainDNSGetHostsResult->host as $host) {
            $hosts[] = [
                'id' => (string) $host['HostId'],
                'name' => (string) $host['Name'],
                'type' => (string) $host['Type'],
                'address' => (string) $host['Address'],
                'mx_pref' => (string) $host['MXPref'],
                'ttl' => (string) $host['TTL'],
            ];
        }

        return $hosts;
    }

    /**
     * Set DNS Host Records
     */
    public function setDNSHostRecords(string $sld, string $tld, array $hosts)
    {
        $params = [
            'SLD' => $sld,
            'TLD' => $tld
        ];

        foreach ($hosts as $index => $host) {
            $i = $index + 1;
            $params["Hostname{$i}"] = $host['name'];
            $params["RecordType{$i}"] = $host['type'];
            $params["Address{$i}"] = $host['address'];
            if (isset($host['mx_pref'])) $params["MXPref{$i}"] = $host['mx_pref'];
            if (isset($host['ttl'])) $params["TTL{$i}"] = $host['ttl'];
        }

        $xml = $this->call('namecheap.domains.dns.setHosts', $params);

        return (string) $xml->CommandResponse->DomainDNSSetHostsResult['IsSuccess'] === 'true';
    }



    /**
     * Get WHOIS data via RDAP (HTTP-based, no port 43 needed).
     * Falls back to socket WHOIS, then DNS-only if both fail.
     */
    public function getWhois(string $domain): array
    {
        $domain = strtolower(trim($domain));

        // --- 1. Public DNS nameservers (always reliable) ---
        $nameservers = [];
        try {
            $nsRecords = dns_get_record($domain, DNS_NS);
            if ($nsRecords) {
                $nameservers = array_map(fn($r) => $r['target'], $nsRecords);
            }
        } catch (\Exception $e) {
            Log::warning("NS lookup failed for {$domain}: " . $e->getMessage());
        }

        // --- 2. Try RDAP (structured JSON over HTTPS, port 80/443) ---
        try {
            $rdapUrl = "https://rdap.org/domain/{$domain}";
            $response = Http::timeout(10)->get($rdapUrl);

            // 404 = domain not registered / available
            if ($response->status() === 404) {
                return [
                    'is_registered' => false,
                    'nameservers'   => $nameservers,
                    'status'        => 'available',
                    'registrar'     => null,
                    'created'       => null,
                    'expires'       => null,
                    'updated'       => null,
                    'raw'           => "Domain: {$domain}\nStatus: Available (Not Registered)\n\nThis domain is available for registration.",
                ];
            }

            if ($response->successful()) {
                $data = $response->json();

                $created  = '';
                $expires  = '';
                $updated  = '';
                $status   = 'registered';
                $registrar = '';
                $rdapNs   = [];

                // Events: registration, expiration, last changed
                foreach ($data['events'] ?? [] as $event) {
                    $action = $event['eventAction'] ?? '';
                    $date   = $event['eventDate'] ?? '';
                    if ($action === 'registration')   $created = $this->formatWhoisDate($date);
                    if ($action === 'expiration')      $expires = $this->formatWhoisDate($date);
                    if ($action === 'last changed')    $updated = $this->formatWhoisDate($date);
                }

                // Status
                if (!empty($data['status'])) {
                    $status = implode(', ', (array) $data['status']);
                }

                // Registrar from entities
                foreach ($data['entities'] ?? [] as $entity) {
                    $roles = $entity['roles'] ?? [];
                    if (in_array('registrar', $roles)) {
                        $registrar = $entity['vcardArray'][1][1][3] ?? // fn field
                            ($entity['handle'] ?? '');
                        // vCard: [[version,...],[fn, {}, text, "Name"],[...]]
                        foreach ($entity['vcardArray'][1] ?? [] as $vcard) {
                            if (($vcard[0] ?? '') === 'fn') {
                                $registrar = $vcard[3] ?? $registrar;
                                break;
                            }
                        }
                    }
                }

                // Nameservers from RDAP
                foreach ($data['nameservers'] ?? [] as $ns) {
                    $ldhName = $ns['ldhName'] ?? '';
                    if ($ldhName) $rdapNs[] = strtolower($ldhName);
                }

                $allNs = array_unique(array_merge($rdapNs, $nameservers));

                // Build a readable raw text from RDAP data
                $rawLines = [
                    "Domain:      {$domain}",
                    "Handle:      " . ($data['handle'] ?? ''),
                    "Registrar:   {$registrar}",
                    "Status:      {$status}",
                    "Created:     {$created}",
                    "Expires:     {$expires}",
                    "Updated:     {$updated}",
                    "",
                    "Name Servers:",
                ];
                foreach ($allNs as $ns) {
                    $rawLines[] = "  {$ns}";
                }
                $rawLines[] = "";
                $rawLines[] = "Source: RDAP (rdap.org)";

                return [
                    'nameservers' => $allNs,
                    'status'      => $status,
                    'registrar'   => $registrar,
                    'created'     => $created,
                    'expires'     => $expires,
                    'updated'     => $updated,
                    'raw'         => implode("\n", $rawLines),
                ];
            }
        } catch (\Exception $e) {
            Log::warning("RDAP lookup failed for {$domain}: " . $e->getMessage());
        }

        // --- 3. Fallback: socket WHOIS on port 43 ---
        $tld = strtolower(substr($domain, strrpos($domain, '.') + 1));
        $whoisServers = [
            'com'    => 'whois.verisign-grs.com',
            'net'    => 'whois.verisign-grs.com',
            'org'    => 'whois.pir.org',
            'io'     => 'whois.nic.io',
            'co'     => 'whois.nic.co',
            'info'   => 'whois.afilias.net',
            'biz'    => 'whois.biz',
            'app'    => 'whois.nic.google',
            'dev'    => 'whois.nic.google',
            'xyz'    => 'whois.nic.xyz',
            'shop'   => 'whois.nic.shop',
        ];
        $whoisHost = $whoisServers[$tld] ?? "whois.nic.{$tld}";

        $rawText   = '';
        $created   = '';
        $expires   = '';
        $status    = 'Registered';
        $registrar = '';

        try {
            $socket = @fsockopen($whoisHost, 43, $errno, $errstr, 10);
            if ($socket) {
                fwrite($socket, $domain . "\r\n");
                while (!feof($socket)) {
                    $rawText .= fgets($socket, 4096);
                }
                fclose($socket);

                foreach (explode("\n", $rawText) as $line) {
                    $line = trim($line);
                    if (preg_match('/^Creation Date:\s*(.+)/i', $line, $m))         $created   = $this->formatWhoisDate(trim($m[1]));
                    if (preg_match('/^Created On:\s*(.+)/i', $line, $m))            $created   = $created ?: $this->formatWhoisDate(trim($m[1]));
                    if (preg_match('/^Registry Expiry Date:\s*(.+)/i', $line, $m))  $expires   = $this->formatWhoisDate(trim($m[1]));
                    if (preg_match('/^Expiry Date:\s*(.+)/i', $line, $m))           $expires   = $expires ?: $this->formatWhoisDate(trim($m[1]));
                    if (preg_match('/^Expiration Date:\s*(.+)/i', $line, $m))       $expires   = $expires ?: $this->formatWhoisDate(trim($m[1]));
                    if (preg_match('/^Registrar:\s*(.+)/i', $line, $m))             $registrar = trim($m[1]);
                    if (preg_match('/^Domain Status:\s*(\w+)/i', $line, $m))        $status    = trim($m[1]);
                }
            }
        } catch (\Exception $e) {
            Log::warning("WHOIS socket query failed for {$domain}: " . $e->getMessage());
        }

        return [
            'nameservers' => $nameservers,
            'status'      => $status ?: 'Registered',
            'registrar'   => $registrar,
            'created'     => $created,
            'expires'     => $expires,
            'updated'     => '',
            'raw'         => $rawText ?: implode("\n", [
                "Domain: {$domain}",
                "Nameservers: " . implode(', ', $nameservers),
                "Note: Full WHOIS details unavailable (privacy protection or port 43 blocked).",
            ]),
        ];
    }

    /**
     * Format a WHOIS date string to a readable format
     */
    private function formatWhoisDate(string $date): string
    {
        if (empty($date)) return '';
        try {
            // Strip timezone suffix like 'T00:00:00Z' or just parse as-is
            $dt = new \DateTime($date);
            return $dt->format('d M Y');
        } catch (\Exception $e) {
            return $date; // Return as-is if unparseable
        }
    }



    protected function formatContactParams(array $info)
    {
        $types = ['Registrant', 'Admin', 'Tech', 'AuxBilling'];
        $params = [];

        foreach ($types as $type) {
            $params["{$type}FirstName"] = $info['first_name'] ?? 'John';
            $params["{$type}LastName"] = $info['last_name'] ?? 'Doe';
            $params["{$type}Address1"] = $info['address'] ?? '123 Main St';
            $params["{$type}City"] = $info['city'] ?? 'New York';
            $params["{$type}StateProvince"] = $info['state'] ?? 'NY';
            $params["{$type}PostalCode"] = $info['zip'] ?? '10001';
            $params["{$type}Country"] = $this->normalizeCountry($info['country'] ?? 'US');
            $params["{$type}Phone"] = $this->normalizePhoneNumber($info['phone'] ?? '+1.5555555555', $info['country'] ?? 'US');
            $params["{$type}EmailAddress"] = $info['email'] ?? 'admin@zosair.com';
        }

        return $params;
    }

    /**
     * Normalize phone number to Namecheap format: +[CountryCode].[Number]
     * e.g. +880.1711111111
     */
    private function normalizePhoneNumber(string $phone, string $country): string
    {
        // Remove all non-numeric characters except + and .
        $clean = preg_replace('/[^0-9+.]/', '', $phone);

        // Namecheap format: +NNN.NNNNNNNNNN
        // If it already contains a dot and starts with +, assuming it's already correct
        if (str_contains($clean, '.') && str_starts_with($clean, '+')) {
            return $clean;
        }

        // If it starts with + but no dot, try to find a logical split or just add a dot after 3 digits
        if (str_starts_with($clean, '+')) {
            // Very simple heuristic: +[1-3 digits].[rest]
            // Better to use a mapping
            return preg_replace('/^\+(\d{1,3})(\d+)$/', '+$1.$2', $clean);
        }

        // Mapping for common countries in the system
        $countryCodes = [
            'bangladesh' => '880',
            'bd'         => '880',
            'united states' => '1',
            'usa'        => '1',
            'us'         => '1',
            'united kingdom' => '44',
            'uk'         => '44',
            'gb'         => '44',
            'india'      => '91',
            'in'         => '91',
            'pakistan'   => '92',
            'pk'         => '92',
        ];

        $code = $countryCodes[strtolower($country)] ?? '1'; // Default to US if unknown

        // Remove leading zero if present in the local number
        $local = ltrim($clean, '0');

        return "+{$code}.{$local}";
    }

    /**
     * Map country name or code to 2-letter ISO code
     */
    private function normalizeCountry(string $country): string
    {
        $mapping = [
            'bangladesh'     => 'BD',
            'united states'  => 'US',
            'usa'            => 'US',
            'united kingdom' => 'GB',
            'uk'             => 'GB',
            'india'          => 'IN',
            'pakistan'       => 'PK',
            'canada'         => 'CA',
            'australia'      => 'AU',
        ];

        $lower = strtolower($country);
        if (isset($mapping[$lower])) {
            return $mapping[$lower];
        }

        // If it's already 2 letters, assume it's an ISO code
        if (strlen($country) === 2) {
            return strtoupper($country);
        }

        return 'US'; // Default fallback
    }
}
