<?php

namespace App\Jobs;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PreloadOrganizationScreenshot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(public readonly int $organizationId)
    {
    }

    public function handle(): void
    {
        $organization = Organization::find($this->organizationId);
        if (!$organization || !$organization->website) {
            return;
        }

        $screenshotUrl = $this->buildScreenshotUrl($organization->website);
        if (!$screenshotUrl) {
            return;
        }

        try {
            Http::timeout(20)
                ->withHeaders([
                    'User-Agent' => 'CRM Screenshot Preloader',
                    'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                ])
                ->get($screenshotUrl);
        } catch (Throwable $exception) {
            Log::info('Failed preloading organization screenshot', [
                'organization_id' => $organization->id,
                'website' => $organization->website,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function buildScreenshotUrl(string $website): ?string
    {
        $baseUrl = rtrim((string) config('services.apiflash.base_url'), '/');
        $accessKey = config('services.apiflash.key');

        if (!$baseUrl || !$accessKey) {
            Log::warning('Skipping screenshot preload due to missing Apiflash configuration');
            return null;
        }

        $formattedWebsite = $this->ensureScheme($website);
        if (!$formattedWebsite) {
            return null;
        }

        $params = http_build_query([
            'access_key' => $accessKey,
            'wait_until' => 'network_idle',
            'no_cookie_banners' => 'true',
            'url' => $formattedWebsite,
        ]);

        return $baseUrl . '?' . $params;
    }

    private function ensureScheme(?string $website): ?string
    {
        $value = trim((string) $website);
        if ($value === '') {
            return null;
        }

        if (!preg_match('/^https?:\/\//i', $value)) {
            $value = 'https://' . ltrim($value, '/');
        }

        return $value;
    }
}
