<?php

namespace App\Support;

class WebsiteUrl
{
    /**
     * Known multi-segment public suffixes that require keeping three labels
     * to identify the registrable domain (e.g., example.co.uk).
     *
     * @var array<int, string>
     */
    private const MULTI_SEGMENT_SUFFIXES = [
        'co.uk',
        'ac.uk',
        'gov.uk',
        'org.uk',
        'com.au',
        'net.au',
        'org.au',
        'com.br',
        'com.mx',
        'com.cn',
        'com.hk',
        'com.sg',
        'com.my',
        'com.tr',
        'com.sa',
        'com.eg',
        'com.co',
        'com.ar',
        'co.jp',
        'ne.jp',
        'or.jp',
    ];

    /**
     * Normalize a website down to its origin (scheme + host [+ port]),
     * trimming trailing slashes and stripping any path/query fragments.
     */
    public static function normalize(?string $url): ?string
    {
        $parts = self::parseUrl($url);
        if (!$parts) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host']);
        $origin = $scheme . '://' . $host;

        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return rtrim($origin, '/');
    }

    /**
     * Extract the registrable root domain for equality comparisons.
     */
    public static function rootDomain(?string $url): ?string
    {
        $parts = self::parseUrl($url);
        if (!$parts || empty($parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        $host = preg_replace('/^www\./', '', $host);
        $segments = array_values(array_filter(explode('.', $host)));

        if (count($segments) === 0) {
            return null;
        }

        if (count($segments) <= 2) {
            return implode('.', $segments);
        }

        $lastTwo = implode('.', array_slice($segments, -2));
        if (in_array($lastTwo, self::MULTI_SEGMENT_SUFFIXES, true) && count($segments) >= 3) {
            return implode('.', array_slice($segments, -3));
        }

        return implode('.', array_slice($segments, -2));
    }

    private static function parseUrl(?string $url): ?array
    {
        $value = trim((string) $url);
        if ($value === '') {
            return null;
        }

        $hasScheme = (bool) preg_match('/^\w+:\/\//i', $value);
        $candidate = $hasScheme ? $value : 'https://' . $value;

        $parts = parse_url($candidate);
        if ($parts === false || empty($parts['host'])) {
            return null;
        }

        if (!isset($parts['scheme'])) {
            $parts['scheme'] = 'https';
        }

        return $parts;
    }
}
