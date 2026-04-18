<?php

namespace App\Support;

/**
 * Guards against Server-Side Request Forgery (SSRF) attacks.
 *
 * Validates that a URL does not point to a private network, loopback address,
 * cloud metadata endpoint, or reserved IP range before the application
 * makes an outbound HTTP request on behalf of a user-supplied URL.
 */
class SsrfGuard
{
    /** Hostnames that are always blocked regardless of DNS resolution. */
    private const BLOCKED_HOSTS = [
        'localhost',
        '169.254.169.254',           // AWS / Azure / GCP instance metadata service
        'metadata.google.internal',   // GCP metadata
        'metadata.internal',          // GCP (alt)
        '[::1]',                      // IPv6 loopback bracket form
    ];

    /**
     * Validate that the URL is safe to fetch.
     *
     * @throws \InvalidArgumentException if the URL is considered unsafe.
     */
    public static function validate(string $url): void
    {
        $parsed = parse_url($url);

        // Scheme must be http or https — block file://, ftp://, etc.
        if (! in_array(strtolower($parsed['scheme'] ?? ''), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Only http and https URLs are allowed.');
        }

        // Strip IPv6 brackets so "::1" and "[::1]" are handled the same way.
        $host = strtolower(trim($parsed['host'] ?? '', '[]'));

        if ($host === '') {
            throw new \InvalidArgumentException('Could not determine host from URL.');
        }

        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            throw new \InvalidArgumentException("Requests to '{$host}' are not permitted.");
        }

        // If the host is already a raw IP address, check it directly.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            self::assertPublicIp($host);

            return;
        }

        // Resolve the hostname and verify the resulting IP is public.
        // gethostbyname() returns the original string unchanged on failure,
        // so we only check when it actually resolved.
        $resolved = gethostbyname($host);
        if ($resolved !== $host && filter_var($resolved, FILTER_VALIDATE_IP)) {
            self::assertPublicIp($resolved);
        }
    }

    /**
     * Assert that the IP is not private, loopback, or reserved.
     *
     * @throws \InvalidArgumentException
     */
    private static function assertPublicIp(string $ip): void
    {
        $isPublic = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );

        if (! $isPublic) {
            throw new \InvalidArgumentException(
                'Requests to private or reserved IP addresses are not allowed.',
            );
        }
    }
}
