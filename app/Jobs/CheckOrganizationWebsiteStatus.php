<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Support\WebsiteUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckOrganizationWebsiteStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(public readonly int $organizationId)
    {
    }

    public function handle(): void
    {
        $organization = Organization::find($this->organizationId);
        if (!$organization) {
            return;
        }

        $normalizedUrl = $this->normalizeWebsite($organization->website);
        if (!$normalizedUrl) {
            $organization->updateQuietly([
                'website_status' => Organization::WEBSITE_STATUS_DOWN,
            ]);
            return;
        }

        $status = $this->determineWebsiteStatus($normalizedUrl);
        $organization->updateQuietly([
            'website_status' => $status,
        ]);
    }

    private function normalizeWebsite(?string $website): ?string
    {
        $normalized = WebsiteUrl::normalize($website);
        if (!$normalized) {
            return null;
        }

        return $normalized;
    }

    private function determineWebsiteStatus(string $url): string
    {
        $originalHost = $this->hostFromUrl($url);
        $currentUrl = $url;
        $redirected = false;
        $lastErrorStatus = null;
        $attempts = 0;

        while ($attempts < 3) {
            $response = $this->sendRequest('head', $currentUrl, false, $lastErrorStatus);
            if ($response && $response->successful()) {
                return $redirected ? Organization::WEBSITE_STATUS_REDIRECTED : Organization::WEBSITE_STATUS_UP;
            }

            if ($response && $this->isRedirect($response)) {
                $nextUrl = $this->resolveRedirectUrl($currentUrl, $response->header('Location'));
                if (!$nextUrl) {
                    break;
                }

                $nextHost = $this->hostFromUrl($nextUrl);
                if ($nextHost && $originalHost && !$this->hostsMatch($nextHost, $originalHost)) {
                    $redirected = true;
                }

                $currentUrl = $nextUrl;
                $attempts++;
                continue;
            }

            $attempts++;
            break;
        }

        $response = $this->sendRequest('get', $currentUrl, true, $lastErrorStatus);
        if ($response && $response->successful()) {
            return $redirected ? Organization::WEBSITE_STATUS_REDIRECTED : Organization::WEBSITE_STATUS_UP;
        }

        if ($lastErrorStatus) {
            return $lastErrorStatus;
        }

        return $redirected ? Organization::WEBSITE_STATUS_REDIRECTED : Organization::WEBSITE_STATUS_DOWN;
    }

    private function sendRequest(string $method, string $url, bool $followRedirects, ?string &$lastErrorStatus): ?Response
    {
        try {
            return Http::timeout(5)
                ->withHeaders([
                    'User-Agent' => 'CRM Website Status Bot',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->withOptions(['allow_redirects' => $followRedirects])
                ->{$method}($url);
        } catch (Throwable $exception) {
            Log::debug('Website status HTTP request failed', [
                'method' => $method,
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            $lastErrorStatus = $lastErrorStatus ?? $this->mapErrorStatus($exception);

            return null;
        }
    }

    private function isRedirect(Response $response): bool
    {
        return $response->status() >= 300 && $response->status() < 400;
    }

    private function resolveRedirectUrl(string $currentUrl, ?string $location): ?string
    {
        if (!$location) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $location)) {
            return $location;
        }

        if (str_starts_with($location, '//')) {
            $scheme = parse_url($currentUrl, PHP_URL_SCHEME) ?: 'https';
            return $scheme . ':' . $location;
        }

        $parts = parse_url($currentUrl);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $base = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $base .= ':' . $parts['port'];
        }

        if (str_starts_with($location, '/')) {
            return $base . $location;
        }

        $path = $parts['path'] ?? '/';
        $directory = rtrim(dirname($path), '/');
        if ($directory === '') {
            $directory = '/';
        }

        return rtrim($base, '/') . $directory . '/' . ltrim($location, '/');
    }

    private function hostFromUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return null;
        }

        return strtolower(preg_replace('/^www\./', '', $host));
    }

    private function hostsMatch(?string $a, ?string $b): bool
    {
        if (!$a || !$b) {
            return false;
        }

        return $a === $b;
    }

    private function mapErrorStatus(Throwable $exception): ?string
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'ssl') || str_contains($message, 'certificate')) {
            return Organization::WEBSITE_STATUS_DOWN;
        }

        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return Organization::WEBSITE_STATUS_DOWN;
        }

        return null;
    }
}
