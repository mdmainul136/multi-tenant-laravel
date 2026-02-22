<?php

namespace App\Services;

class DnsVerificationService
{
    /**
     * Verify if a domain is pointing to our server.
     */
    public function verify(string $domain): array
    {
        $domain = strtolower($domain);
        $errors = [];
        $expectedCname = config('tenant.cname_target', 'cname.zosair.com');
        $expectedIp = config('tenant.server_ip', '127.0.0.1');

        try {
            $records = dns_get_record($domain, DNS_CNAME | DNS_A);
            
            if (empty($records)) {
                return [
                    'success' => false,
                    'message' => 'No DNS records found for this domain.',
                    'errors' => ['No records found']
                ];
            }

            $currentCname = null;
            $currentIps = [];

            foreach ($records as $record) {
                if ($record['type'] === 'CNAME') {
                    $currentCname = $record['target'];
                } elseif ($record['type'] === 'A') {
                    $currentIps[] = $record['ip'];
                }
            }

            // Check CNAME first
            if ($currentCname && str_ends_with($currentCname, $expectedCname)) {
                return [
                    'success' => true,
                    'message' => 'Domain verified via CNAME.',
                    'method' => 'CNAME'
                ];
            }

            // Check A record
            if (in_array($expectedIp, $currentIps)) {
                return [
                    'success' => true,
                    'message' => 'Domain verified via A record.',
                    'method' => 'A'
                ];
            }

            return [
                'success' => false,
                'message' => 'DNS records do not match our required configuration.',
                'found' => [
                    'cname' => $currentCname,
                    'ips' => $currentIps
                ],
                'expected' => [
                    'cname' => $expectedCname,
                    'ip' => $expectedIp
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error checking DNS records: ' . $e->getMessage()
            ];
        }
    }
}
