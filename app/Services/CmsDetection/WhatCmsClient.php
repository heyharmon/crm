<?php

namespace App\Services\CmsDetection;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatCmsClient
{
    private const ENDPOINT = 'https://whatcms.org/API/Tech';
    private const TIMEOUT_SECONDS = 20;

    public function detectCms(string $url): ?string
    {
        $apiKey = config('services.whatcms.key');
        if (!$apiKey) {
            Log::warning('WhatCMS API key is not configured.');
            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->acceptJson()
                ->get(self::ENDPOINT, [
                    'key' => $apiKey,
                    'url' => $url,
                ]);
        } catch (Throwable $exception) {
            Log::error('WhatCMS request failed', [
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);
            return null;
        }

        if (!$response->successful()) {
            Log::warning('WhatCMS request returned non-success status', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $payload = $response->json();
        if (!is_array($payload)) {
            Log::warning('WhatCMS response payload was not an array', ['url' => $url]);
            return null;
        }

        $resultCode = Arr::get($payload, 'result.code');
        if ($resultCode !== 200) {
            Log::info('WhatCMS request did not succeed', [
                'url' => $url,
                'code' => $resultCode,
                'message' => Arr::get($payload, 'result.msg'),
            ]);
            return null;
        }

        $technologies = Arr::get($payload, 'results');
        if (!is_array($technologies)) {
            return null;
        }

        foreach ($technologies as $technology) {
            if (!is_array($technology)) {
                continue;
            }

            $name = $technology['name'] ?? null;
            $categories = $technology['categories'] ?? [];

            if (!$name || !is_array($categories)) {
                continue;
            }

            foreach ($categories as $category) {
                if (is_string($category) && strcasecmp($category, 'CMS') === 0) {
                    return $name;
                }
            }
        }

        return null;
    }
}
