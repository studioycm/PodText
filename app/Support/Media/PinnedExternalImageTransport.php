<?php

namespace App\Support\Media;

use App\Enums\ExternalImageFailureReason;
use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PinnedExternalImageTransport
{
    public function __construct(
        private readonly CuratorImageUploadPolicy $policy,
    ) {}

    public function get(string $url, string $host, string $address, float $remainingSeconds): Response
    {
        if ($remainingSeconds <= 0) {
            throw new ExternalImageUnavailableException(
                ExternalImageFailureReason::TimedOut,
                'The external image request has no remaining time.',
            );
        }

        if (
            ! defined('CURLOPT_RESOLVE')
            || ! defined('CURLOPT_PROXY')
            || ! defined('CURLOPT_NOPROXY')
        ) {
            throw new ExternalImageUnavailableException(
                ExternalImageFailureReason::TemporarilyUnavailable,
                'A pinned cURL transport is required for external images.',
            );
        }

        $connectedAddress = null;
        $pinnedAddress = str_contains($address, ':') ? "[{$address}]" : $address;
        $response = Http::withUserAgent('PodText image-library fetcher/1.0')
            ->accept(implode(', ', $this->policy->globalMimeTypes()))
            ->withHeaders(['Accept-Encoding' => 'identity'])
            ->timeout($remainingSeconds)
            ->connectTimeout(min(5.0, $remainingSeconds))
            ->withOptions([
                'allow_redirects' => false,
                'decode_content' => false,
                'http_errors' => false,
                'curl' => [
                    CURLOPT_RESOLVE => ["{$host}:443:{$pinnedAddress}"],
                    CURLOPT_PROXY => '',
                    CURLOPT_NOPROXY => '*',
                ],
                'progress' => function (int $downloadTotal, int $downloadedBytes): void {
                    if (
                        $downloadTotal > $this->policy->maxKilobytes() * 1024
                        || $downloadedBytes > $this->policy->maxKilobytes() * 1024
                    ) {
                        throw new ExternalImageRejectedException(
                            ExternalImageFailureReason::InvalidResponse,
                            'The external image exceeds the response-size limit.',
                        );
                    }
                },
                'on_stats' => function (TransferStats $stats) use (&$connectedAddress): void {
                    $primaryIp = $stats->getHandlerStat('primary_ip');
                    $connectedAddress = is_string($primaryIp) && $primaryIp !== '' ? $primaryIp : null;
                },
            ])
            ->get($url);

        if (! is_string($connectedAddress)) {
            throw new ExternalImageUnavailableException(
                ExternalImageFailureReason::TemporarilyUnavailable,
                'The external image transport did not prove its connected address.',
            );
        }

        if (! $this->sameAddress($address, $connectedAddress)) {
            throw new ExternalImageUnavailableException(
                ExternalImageFailureReason::TemporarilyUnavailable,
                'The external image connection did not use the pinned address.',
            );
        }

        return $response;
    }

    private function sameAddress(string $expected, string $actual): bool
    {
        $expectedBytes = @inet_pton(trim($expected, '[]'));
        $actualBytes = @inet_pton(trim($actual, '[]'));

        return is_string($expectedBytes)
            && is_string($actualBytes)
            && hash_equals($expectedBytes, $actualBytes);
    }
}
