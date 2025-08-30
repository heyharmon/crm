<?php

namespace App\Services;

use App\Models\ApifyRun;
use App\Models\Organization;
use App\Models\Page;
use Illuminate\Support\Facades\Log;

class FirecrawlMapService extends BaseFirecrawlService
{
    public function startScraping(array $params, int $userId): ApifyRun
    {
        $organization = Organization::findOrFail($params['organization_id']);

        if (!$organization->website) {
            throw new \Exception('Organization does not have a website to scrape');
        }

        $response = $this->makeRequest('post', '/map', [
            'url' => $organization->website,
        ]);

        $runData = $response->json();
        $runId = $runData['id'] ?? $runData['jobId'] ?? null;

        if (!$runId) {
            throw new \Exception('Firecrawl run ID not returned');
        }

        return ApifyRun::create([
            'apify_run_id' => $runId,
            'user_id' => $userId,
            'status' => 'RUNNING',
            'actor_id' => 'firecrawl-map',
            'input_data' => [
                'organization_id' => $params['organization_id'],
                'url' => $organization->website,
            ],
            'started_at' => now(),
        ]);
    }

    public function updateRunStatus(ApifyRun $apifyRun): ApifyRun
    {
        $response = $this->makeRequest('get', "/map/{$apifyRun->apify_run_id}");
        $data = $response->json();
        $status = strtolower($data['status'] ?? 'running');

        if (in_array($status, ['finished', 'completed', 'success'])) {
            $mappedStatus = 'SUCCEEDED';
        } elseif (in_array($status, ['failed', 'error'])) {
            $mappedStatus = 'FAILED';
        } else {
            $mappedStatus = 'RUNNING';
        }

        $apifyRun->update([
            'status' => $mappedStatus,
            'finished_at' => $mappedStatus === 'RUNNING' ? null : now(),
        ]);

        return $apifyRun;
    }

    public function getRunResults(ApifyRun $apifyRun): array
    {
        $response = $this->makeRequest('get', "/map/{$apifyRun->apify_run_id}");
        $data = $response->json();
        return $data['data']['urls'] ?? $data['data'] ?? [];
    }

    public function processResults(ApifyRun $apifyRun, array $urls): array
    {
        $organizationId = $apifyRun->input_data['organization_id'] ?? null;

        if (!$organizationId) {
            throw new \Exception('Organization ID not found in run input data');
        }

        $createdPages = [];

        foreach ($urls as $item) {
            $url = is_array($item) ? ($item['url'] ?? null) : $item;
            if (empty($url)) {
                continue;
            }

            $page = Page::updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'url' => $url,
                ],
                [
                    'title' => null,
                ]
            );

            $createdPages[] = $page;
        }

        Log::info('Processed firecrawl results', [
            'organization_id' => $organizationId,
            'pages_created' => count($createdPages),
        ]);

        return [
            'pages_created' => count($createdPages),
            'organization_id' => $organizationId,
        ];
    }
}
