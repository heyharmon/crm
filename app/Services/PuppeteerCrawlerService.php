<?php

namespace App\Services;

use App\Models\ApifyRun;
use App\Models\Organization;
use App\Models\Page;
use Illuminate\Support\Facades\Log;

class PuppeteerCrawlerService extends BaseApifyService
{
    private const ACTOR_ID = 'heyharmon~puppeteer-crawler-task';

    public function startScraping(array $params, int $userId): ApifyRun
    {
        $organization = Organization::findOrFail($params['organization_id']);

        if (!$organization->website) {
            throw new \Exception('Organization does not have a website to scrape');
        }

        $input = [
            'startUrls' => [
                ['url' => $organization->website]
            ],
            'pseudoUrls' => [
                ['purl' => $organization->website . '/[.*?]']
            ],
            'maxPagesPerCrawl' => $params['max_pages'] ?? 500,
            'maxCrawlingDepth' => $params['max_depth'] ?? 2,
            'linkSelector' => 'a[href]',
            'proxyConfiguration' => [
                'useApifyProxy' => true
            ]
        ];

        $runData = $this->runActor(self::ACTOR_ID, $input);

        return ApifyRun::create([
            'apify_run_id' => $runData['id'],
            'user_id' => $userId,
            'status' => $runData['status'],
            'actor_id' => self::ACTOR_ID,
            'input_data' => array_merge($input, ['organization_id' => $params['organization_id']]),
            'started_at' => now(),
        ]);
    }

    public function updateRunStatus(ApifyRun $apifyRun): ApifyRun
    {
        $runData = $this->getRunStatus($apifyRun->apify_run_id);

        $apifyRun->update([
            'status' => $runData['status'],
            'finished_at' => $runData['finishedAt'] ? now()->parse($runData['finishedAt']) : null,
        ]);

        return $apifyRun;
    }

    public function getRunResults(ApifyRun $apifyRun): array
    {
        return $this->getRunDataset($apifyRun->apify_run_id);
    }

    public function processResults(ApifyRun $apifyRun, array $data): array
    {
        $organizationId = $apifyRun->input_data['organization_id'] ?? null;

        if (!$organizationId) {
            throw new \Exception('Organization ID not found in run input data');
        }

        $createdPages = [];

        foreach ($data as $item) {
            if (empty($item['url']) || empty($item['title'])) {
                continue;
            }

            $page = Page::updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'url' => $item['url'],
                ],
                [
                    'title' => $item['title'],
                ]
            );

            $createdPages[] = $page;
        }

        Log::info('Processed web scraper results', [
            'organization_id' => $organizationId,
            'pages_created' => count($createdPages),
        ]);

        return [
            'pages_created' => count($createdPages),
            'organization_id' => $organizationId,
        ];
    }
}
