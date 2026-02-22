<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\NamecheapService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class DomainService
{
    protected NamecheapService $namecheap;

    public function __construct(NamecheapService $namecheap)
    {
        $this->namecheap = $namecheap;
    }
    /**
     * Get all domains for a tenant
     */
    public function getTenantDomains(string $tenantId)
    {
        return TenantDomain::where('tenant_id', $tenantId)->get();
    }

    /**
     * Add a new custom domain for a tenant.
     * Returns dns_instructions so the frontend can show what to add immediately.
     */
    public function addDomain(string $tenantId, string $domain)
    {
        // Check if domain is already taken
        if (TenantDomain::where('domain', $domain)->exists()) {
            throw new \Exception('Domain is already in use');
        }

        $platformIp  = config('services.platform.ip', env('PLATFORM_IP', '127.0.0.1'));
        $baseDomain  = parse_url(config('app.url'), PHP_URL_HOST) ?? 'yourdomain.com';
        $token       = \Illuminate\Support\Str::random(40);
        $isSubdomain = count(explode('.', $domain)) > 2;

        $record = TenantDomain::create([
            'tenant_id'          => $tenantId,
            'domain'             => $domain,
            'verification_token' => $token,
            'status'             => 'pending',
            'is_verified'        => false,
            'is_primary'         => false,
        ]);

        // Build the DNS instructions to return immediately
        $txtRecord = "platform-verify={$token}";
        $dnsInstructions = [
            'ip'      => $platformIp,
            'pending' => [
                [
                    'type'  => 'TXT',
                    'name'  => $domain,   // or @ for root
                    'value' => $txtRecord,
                    'ttl'   => 300,
                    'note'  => 'Required for ownership verification',
                ],
                $isSubdomain
                    ? [
                        'type'  => 'CNAME',
                        'name'  => $domain,
                        'value' => $baseDomain,
                        'ttl'   => 300,
                        'note'  => 'Points subdomain to platform',
                      ]
                    : [
                        'type'  => 'A',
                        'name'  => $domain,
                        'value' => $platformIp,
                        'ttl'   => 300,
                        'note'  => 'Points root domain to platform IP',
                      ],
            ],
        ];

        // Attach dns_instructions to the returned record as an attribute
        $record->setAttribute('dns_instructions', $dnsInstructions);

        return $record;
    }

    /**
     * Verify DNS records using multiple public resolvers for accuracy.
     * Strategy:
     *   - Query Google (8.8.8.8) + Cloudflare (1.1.1.1) + system DNS
     *   - TXT ownership verified  → domain is marked 'verified'
     *   - A/CNAME is reported but NOT required to pass (routing middleware handles it)
     */
    public function verifyDomain(int $domainId)
    {
        $domain     = TenantDomain::findOrFail($domainId);
        $platformIp = config('services.platform.ip', env('PLATFORM_IP', '127.0.0.1'));
        $baseDomain = parse_url(config('app.url'), PHP_URL_HOST) ?? '';
        $isSubdomain = count(explode('.', $domain->domain)) > 2;

        $expectedTxt   = "platform-verify={$domain->verification_token}";
        $resolvers     = ['8.8.8.8', '1.1.1.1']; // Google, Cloudflare

        $txtFound      = [];
        $aFound        = [];
        $cnameFound    = [];

        // ── Query each public resolver directly via UDP (bypasses local DNS cache) ──
        foreach ($resolvers as $resolver) {
            // TXT
            $txt = $this->queryDns($domain->domain, 'TXT', $resolver);
            foreach ($txt as $v) $txtFound[] = $v;

            // A / CNAME
            if ($isSubdomain) {
                $cname = $this->queryDns($domain->domain, 'CNAME', $resolver);
                foreach ($cname as $v) $cnameFound[] = $v;
            } else {
                $a = $this->queryDns($domain->domain, 'A', $resolver);
                foreach ($a as $v) $aFound[] = $v;
            }
        }

        // Also check via PHP built-in (system resolver) as extra cross-check
        try {
            $sysTxt = dns_get_record($domain->domain, DNS_TXT);
            foreach ($sysTxt as $r) { if (!empty($r['txt'])) $txtFound[] = $r['txt']; }

            if ($isSubdomain) {
                $syCname = dns_get_record($domain->domain, DNS_CNAME);
                foreach ($syCname as $r) { if (!empty($r['target'])) $cnameFound[] = $r['target']; }
            } else {
                $sysA = dns_get_record($domain->domain, DNS_A);
                foreach ($sysA as $r) { if (!empty($r['ip'])) $aFound[] = $r['ip']; }
            }
        } catch (\Exception $e) {
            Log::warning("System DNS check failed for {$domain->domain}: " . $e->getMessage());
        }

        // De-duplicate
        $txtFound   = array_unique($txtFound);
        $aFound     = array_unique($aFound);
        $cnameFound = array_unique($cnameFound);

        // ── Evaluate ──
        $txtVerified   = in_array($expectedTxt, $txtFound, true);
        $aVerified     = in_array($platformIp, $aFound, true);
        $cnameVerified = !empty(array_filter($cnameFound, fn($c) => str_contains(rtrim($c, '.'), $baseDomain)));
        $pointingOk    = $isSubdomain ? $cnameVerified : $aVerified;

        $diagnostics = [
            'txt'   => ['expected' => $expectedTxt,  'found' => $txtFound,   'verified' => $txtVerified],
            'a'     => ['expected' => $platformIp,   'found' => $aFound,     'verified' => $aVerified],
            'cname' => ['expected' => $baseDomain,   'found' => $cnameFound, 'verified' => $cnameVerified],
            'resolvers_checked' => $resolvers,
            'domain_type' => $isSubdomain ? 'subdomain' : 'root',
        ];

        // ── TXT verified = ownership proven = mark verified ──
        if ($txtVerified) {
            $domain->update([
                'status'      => 'verified',
                'is_verified' => true,
                'verified_at' => now(),
            ]);

            // Update tenant's main domain if none set or this is primary
            $tenant = \App\Models\Tenant::where('tenant_id', $domain->tenant_id)->first();
            if ($tenant && (!$tenant->domain || $domain->is_primary)) {
                $tenant->update(['domain' => $domain->domain]);
            }

            $message = $pointingOk
                ? '✅ Domain fully verified! Ownership confirmed and DNS pointing is correct.'
                : '✅ Ownership verified! Note: DNS pointing (A/CNAME to platform) is not set yet — the domain will work once you add it.';

            return [
                'success'     => true,
                'message'     => $message,
                'pointing_ok' => $pointingOk,
                'diagnostics' => $diagnostics,
            ];
        }

        // ── TXT not found yet ──
        $nextStep = "Add this TXT record to your DNS:\n  Name: {$domain->domain}\n  Value: {$expectedTxt}\n  TTL: 300\n\nThen click Verify again. DNS may take 1–30 minutes to propagate.";

        return [
            'success'     => false,
            'message'     => '❌ TXT verification record not found yet.',
            'next_step'   => $nextStep,
            'pointing_ok' => $pointingOk,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * Query a specific DNS resolver directly via UDP socket.
     * Much faster than dns_get_record() which uses the system resolver (may be cached).
     *
     * @param string $hostname  Domain to query
     * @param string $type      TXT | A | CNAME
     * @param string $server    Resolver IP (e.g. 8.8.8.8)
     * @return array            Flat array of values found
     */
    private function queryDns(string $hostname, string $type, string $server): array
    {
        try {
            $typeMap = ['A' => 1, 'CNAME' => 5, 'TXT' => 16];
            $qtype   = $typeMap[$type] ?? 16;

            // Build minimal DNS query packet
            $id     = random_int(1, 65534);
            $header = pack('nnnnnn', $id, 0x0100, 1, 0, 0, 0);
            $qname  = '';
            foreach (explode('.', rtrim($hostname, '.')) as $part) {
                $qname .= chr(strlen($part)) . $part;
            }
            $qname   .= "\x00";
            $question = $qname . pack('nn', $qtype, 1); // qtype, IN class
            $packet   = $header . $question;

            $sock = @fsockopen('udp://' . $server, 53, $errno, $errstr, 3);
            if (!$sock) return [];

            stream_set_timeout($sock, 3);
            fwrite($sock, $packet);
            $response = fread($sock, 512);
            fclose($sock);

            if (!$response || strlen($response) < 12) return [];

            // Parse answer count from header
            $ancount = (ord($response[6]) << 8) | ord($response[7]);
            if ($ancount === 0) return [];

            // Skip header (12) + question section
            $offset = 12;
            // Skip question QNAME
            while ($offset < strlen($response)) {
                $len = ord($response[$offset]);
                if ($len === 0) { $offset++; break; }
                $offset += $len + 1;
            }
            $offset += 4; // QTYPE + QCLASS

            $results = [];
            for ($i = 0; $i < $ancount && $offset < strlen($response); $i++) {
                // Skip NAME (may be compressed pointer)
                if ((ord($response[$offset]) & 0xC0) === 0xC0) {
                    $offset += 2;
                } else {
                    while ($offset < strlen($response) && ord($response[$offset]) !== 0) {
                        $offset += ord($response[$offset]) + 1;
                    }
                    $offset++;
                }
                if ($offset + 10 > strlen($response)) break;
                $rrtype  = (ord($response[$offset]) << 8) | ord($response[$offset + 1]);
                $rdlен  = (ord($response[$offset + 8]) << 8) | ord($response[$offset + 9]);
                $offset += 10;

                $rdata = substr($response, $offset, $rdlен);
                $offset += $rdlен;

                if ($rrtype === 1 && strlen($rdata) === 4) {
                    // A record
                    $results[] = implode('.', array_map('ord', str_split($rdata)));
                } elseif ($rrtype === 5) {
                    // CNAME - decode domain name
                    $results[] = $this->decodeDnsName($response, $offset - $rdlен);
                } elseif ($rrtype === 16) {
                    // TXT - first byte is length, rest is text
                    $txt = '';
                    $pos = 0;
                    while ($pos < strlen($rdata)) {
                        $l    = ord($rdata[$pos++]);
                        $txt .= substr($rdata, $pos, $l);
                        $pos += $l;
                    }
                    $results[] = $txt;
                }
            }

            return $results;
        } catch (\Exception $e) {
            Log::debug("UDP DNS query failed [resolver={$server} type={$type} host={$hostname}]: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Decode a DNS compressed name from a raw response buffer.
     */
    private function decodeDnsName(string $response, int $offset): string
    {
        $name   = '';
        $jumped = false;
        $maxLen = strlen($response);

        while ($offset < $maxLen) {
            $len = ord($response[$offset]);
            if ($len === 0) break;

            // Pointer (compression)
            if (($len & 0xC0) === 0xC0) {
                $ptr    = (($len & 0x3F) << 8) | ord($response[$offset + 1]);
                $offset = $ptr;
                $jumped = true;
                continue;
            }

            $name  .= ($name ? '.' : '') . substr($response, $offset + 1, $len);
            $offset += $len + 1;
        }

        return rtrim($name, '.') . '.';
    }

    /**
     * Set a domain as primary
     */
    public function setPrimary(int $domainId)
    {
        $domain = TenantDomain::findOrFail($domainId);
        
        if (!$domain->is_verified) {
            throw new \Exception('Only verified domains can be set as primary');
        }

        // Unset other primary domains for this tenant
        TenantDomain::where('tenant_id', $domain->tenant_id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);

        $domain->update(['is_primary' => true]);

        // Update tenants table cache
        Tenant::where('tenant_id', $domain->tenant_id)
            ->update(['domain' => $domain->domain]);

        return $domain;
    }

    /**
     * Get Nameservers and DNS records from Namecheap
     */
    public function getNameservers(int $domainId)
    {
        $domain = TenantDomain::findOrFail($domainId);
        return $this->namecheap->getWhois($domain->domain);
    }

    /**
     * Get DNS Host Records
     */
    public function getDNSHosts(int $domainId)
    {
        $domain = TenantDomain::findOrFail($domainId);
        $parts = explode('.', $domain->domain);
        $tld = array_pop($parts);
        $sld = implode('.', $parts);

        return $this->namecheap->getDNSHostRecords($sld, $tld);
    }

    /**
     * Update DNS Host Records
     */
    public function updateDNSHosts(int $domainId, array $hosts)
    {
        $domain = TenantDomain::findOrFail($domainId);
        $parts = explode('.', $domain->domain);
        $tld = array_pop($parts);
        $sld = implode('.', $parts);

        return $this->namecheap->setDNSHostRecords($sld, $tld, $hosts);
    }

    /**
     * Renew a domain
     */
    public function renewDomain(int $domainId, int $years = 1)
    {
        $domain = TenantDomain::findOrFail($domainId);
        return $this->namecheap->renewDomain($domain->domain, $years);
    }

    /**
     * Delete a domain
     */
    public function deleteDomain(int $domainId)
    {
        $domain = TenantDomain::findOrFail($domainId);
        
        if ($domain->is_primary) {
            throw new \Exception('Cannot delete the primary domain');
        }

        return $domain->delete();
    }
}
