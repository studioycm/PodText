<?php

namespace App\Support\Media;

use App\Enums\ExternalImageFailureReason;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Throwable;

class SafeExternalImageFetcher
{
    private const MAX_REDIRECTS = 3;

    /**
     * Fail closed for every IANA IPv4 special-purpose block, including ranges
     * that PHP's FILTER_FLAG_NO_RES_RANGE does not currently reject.
     *
     * @var array<int, string>
     */
    private const SPECIAL_USE_IPV4_NETWORKS = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.31.196.0/24',
        '192.52.193.0/24',
        '192.88.99.0/24',
        '192.168.0.0/16',
        '192.175.48.0/24',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
    ];

    /**
     * Only IPv6 global-unicast addresses are candidates, and every current
     * IANA special-purpose subrange is denied even when marked reachable.
     *
     * @var array<int, string>
     */
    private const SPECIAL_USE_IPV6_NETWORKS = [
        '::/128',
        '::1/128',
        '::ffff:0:0/96',
        '64:ff9b::/96',
        '64:ff9b:1::/48',
        '100::/64',
        '100:0:0:1::/64',
        '2001::/23',
        '2001:db8::/32',
        '2002::/16',
        '2620:4f:8000::/48',
        '3fff::/20',
        '5f00::/16',
        'fc00::/7',
        'fe80::/10',
        'ff00::/8',
    ];

    public function __construct(
        private readonly ExternalImageDnsResolver $dnsResolver,
        private readonly PinnedExternalImageTransport $transport,
        private readonly CuratorImageUploadPolicy $policy,
    ) {}

    public function fetch(string $url): string
    {
        $deadline = hrtime(true) + (int) round($this->timeoutSeconds() * 1_000_000_000);

        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            $this->remainingSeconds($deadline);
            [$host, $addresses] = $this->guardedEndpoint($url, $deadline);
            $response = $this->request(
                $url,
                $host,
                $addresses[0],
                $deadline,
            );
            $this->remainingSeconds($deadline);
            $this->assertDnsStable($host, $addresses, $deadline);

            if ($this->isRedirect($response)) {
                if ($redirects === self::MAX_REDIRECTS) {
                    throw new ExternalImageRejectedException(
                        ExternalImageFailureReason::InvalidResponse,
                        'The external image exceeded the redirect limit.',
                    );
                }

                $url = $this->redirectUrl($url, $response->header('Location'));

                continue;
            }

            if ($response->failed()) {
                if ($response->status() === 429 || $response->serverError()) {
                    throw new ExternalImageUnavailableException(
                        ExternalImageFailureReason::TemporarilyUnavailable,
                        "External image request failed transiently with HTTP {$response->status()}.",
                    );
                }

                throw new ExternalImageRejectedException(
                    $response->notFound()
                        ? ExternalImageFailureReason::NotFound
                        : ExternalImageFailureReason::InvalidResponse,
                    "External image request was rejected with HTTP {$response->status()}.",
                );
            }

            $contents = $response->body();

            if ($contents === '' || strlen($contents) > $this->policy->maxKilobytes() * 1024) {
                throw new ExternalImageRejectedException(
                    ExternalImageFailureReason::InvalidResponse,
                    'The external image is empty or exceeds the response-size limit.',
                );
            }

            return $contents;
        }

        throw new ExternalImageUnavailableException(
            ExternalImageFailureReason::TemporarilyUnavailable,
            'The external image could not be fetched safely.',
        );
    }

    /** @return array{string, array<int, string>} */
    private function guardedEndpoint(string $url, int $deadline): array
    {
        if (
            filter_var($url, FILTER_VALIDATE_URL) === false
            || str_contains($url, '\\')
            || preg_match('/[\x00-\x20\x7F]/', $url) === 1
        ) {
            throw new ExternalImageRejectedException(
                ExternalImageFailureReason::Blocked,
                'The external image URL is not canonical.',
            );
        }

        $parts = parse_url($url);
        $scheme = is_array($parts) ? mb_strtolower((string) ($parts['scheme'] ?? '')) : '';
        $host = is_array($parts) ? mb_strtolower((string) ($parts['host'] ?? '')) : '';

        if (
            $scheme !== 'https'
            || $host === ''
            || preg_match('/^[a-z0-9.-]+$/', $host) !== 1
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
        ) {
            throw new ExternalImageRejectedException(
                ExternalImageFailureReason::Blocked,
                'External images require a plain HTTPS URL on port 443.',
            );
        }

        try {
            $addresses = $this->dnsResolver->addresses($host);
        } catch (Throwable $exception) {
            $this->remainingSeconds($deadline);

            throw new ExternalImageUnavailableException(
                ExternalImageFailureReason::TemporarilyUnavailable,
                'The external image host could not be resolved.',
                $exception,
            );
        }

        if ($addresses === [] || collect($addresses)->contains(fn (string $address): bool => ! $this->isPublicAddress($address))) {
            throw new ExternalImageRejectedException(
                ExternalImageFailureReason::Blocked,
                'The external image host does not resolve exclusively to public addresses.',
            );
        }

        sort($addresses);

        return [$host, array_values(array_unique($addresses))];
    }

    private function request(string $url, string $host, string $address, int $deadline): Response
    {
        try {
            $response = $this->transport->get(
                $url,
                $host,
                $address,
                $this->remainingSeconds($deadline),
            );
        } catch (ExternalImageRejectedException|ExternalImageUnavailableException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->remainingSeconds($deadline);

            throw new ExternalImageUnavailableException(
                ExternalImageFailureReason::TemporarilyUnavailable,
                'The external image request failed.',
                $exception,
            );
        }

        $contentEncoding = trim((string) $response->header('Content-Encoding'));

        if ($contentEncoding !== '' && mb_strtolower($contentEncoding) !== 'identity') {
            throw new ExternalImageRejectedException(
                ExternalImageFailureReason::InvalidResponse,
                'Encoded external image responses are not accepted.',
            );
        }

        $contentLength = trim((string) $response->header('Content-Length'));

        if ($contentLength !== '' && (! ctype_digit($contentLength) || (int) $contentLength > $this->policy->maxKilobytes() * 1024)) {
            throw new ExternalImageRejectedException(
                ExternalImageFailureReason::InvalidResponse,
                'The external image response length is invalid or too large.',
            );
        }

        return $response;
    }

    /** @param array<int, string> $before */
    private function assertDnsStable(string $host, array $before, int $deadline): void
    {
        $this->remainingSeconds($deadline);

        try {
            $after = $this->dnsResolver->addresses($host);
        } catch (Throwable $exception) {
            $this->remainingSeconds($deadline);

            throw new ExternalImageUnavailableException(
                ExternalImageFailureReason::TemporarilyUnavailable,
                'The external image host could not be revalidated.',
                $exception,
            );
        }

        $this->remainingSeconds($deadline);
        sort($after);

        if ($after !== $before || collect($after)->contains(fn (string $address): bool => ! $this->isPublicAddress($address))) {
            throw new ExternalImageRejectedException(
                ExternalImageFailureReason::Blocked,
                'The external image host changed DNS addresses during the request.',
            );
        }
    }

    private function isPublicAddress(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
            return false;
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return ! $this->isInAnyNetwork($address, self::SPECIAL_USE_IPV4_NETWORKS);
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return false;
        }

        return $this->isInNetwork($address, '2000::/3')
            && ! $this->isInAnyNetwork($address, self::SPECIAL_USE_IPV6_NETWORKS);
    }

    /** @param array<int, string> $networks */
    private function isInAnyNetwork(string $address, array $networks): bool
    {
        foreach ($networks as $network) {
            if ($this->isInNetwork($address, $network)) {
                return true;
            }
        }

        return false;
    }

    private function isInNetwork(string $address, string $network): bool
    {
        [$networkAddress, $prefixLength] = explode('/', $network, 2);
        $addressBytes = inet_pton($address);
        $networkBytes = inet_pton($networkAddress);

        if (! is_string($addressBytes) || ! is_string($networkBytes) || strlen($addressBytes) !== strlen($networkBytes)) {
            return false;
        }

        $prefix = (int) $prefixLength;
        $wholeBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if (substr($addressBytes, 0, $wholeBytes) !== substr($networkBytes, 0, $wholeBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($addressBytes[$wholeBytes]) & $mask) === (ord($networkBytes[$wholeBytes]) & $mask);
    }

    private function isRedirect(Response $response): bool
    {
        return in_array($response->status(), [301, 302, 303, 307, 308], true);
    }

    private function redirectUrl(string $baseUrl, ?string $location): string
    {
        if (! is_string($location) || trim($location) === '') {
            throw new ExternalImageRejectedException(
                ExternalImageFailureReason::InvalidResponse,
                'The external image redirect is missing a location.',
            );
        }

        $location = trim($location);

        if (str_contains($location, '\\') || preg_match('/[\x00-\x20\x7F]/', $location) === 1) {
            throw new ExternalImageRejectedException(
                ExternalImageFailureReason::InvalidResponse,
                'The external image redirect location is not canonical.',
            );
        }

        try {
            return (string) UriResolver::resolve(new Uri($baseUrl), new Uri($location));
        } catch (Throwable $exception) {
            throw new ExternalImageRejectedException(
                ExternalImageFailureReason::InvalidResponse,
                'The external image redirect location is invalid.',
                $exception,
            );
        }
    }

    private function timeoutSeconds(): float
    {
        return max(
            0.001,
            min(30.0, (float) config('media.acquisition.external_image_timeout_seconds', 20)),
        );
    }

    private function remainingSeconds(int $deadline): float
    {
        $remaining = ($deadline - hrtime(true)) / 1_000_000_000;

        if ($remaining <= 0) {
            throw new ExternalImageUnavailableException(
                ExternalImageFailureReason::TimedOut,
                'The external image request exceeded its total deadline.',
            );
        }

        return $remaining;
    }
}
