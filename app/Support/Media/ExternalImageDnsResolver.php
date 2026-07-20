<?php

namespace App\Support\Media;

class ExternalImageDnsResolver
{
    /** @return array<int, string> */
    public function addresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if (! is_array($records)) {
            return [];
        }

        return collect($records)
            ->flatMap(fn (array $record): array => array_values(array_filter([
                $record['ip'] ?? null,
                $record['ipv6'] ?? null,
            ], is_string(...))))
            ->unique()
            ->values()
            ->all();
    }
}
