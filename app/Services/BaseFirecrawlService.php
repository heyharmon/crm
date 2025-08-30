<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BaseFirecrawlService
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.firecrawl.api_key');
        $this->baseUrl = rtrim(config('services.firecrawl.base_url', 'https://api.firecrawl.dev/v1'), '/');

        if (!$this->apiKey) {
            throw new \Exception('Firecrawl API key not configured');
        }
    }

    protected function makeRequest(string $method, string $endpoint, array $data = []): Response
    {
        $url = $this->baseUrl . $endpoint;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->$method($url, $data);

        if (!$response->successful()) {
            Log::error('Firecrawl API request failed', [
                'method' => $method,
                'url' => $url,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            throw new \Exception('Firecrawl API request failed: ' . $response->body());
        }

        return $response;
    }
}
