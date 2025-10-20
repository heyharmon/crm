<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Illuminate\Console\Command;

class DeduplicateOrganizationsByWebsiteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'organizations:dedupe-website {--dry-run : List duplicates without deleting them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove duplicate organizations by website, keeping the record with the highest review count.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $domains = [];

        Organization::query()
            ->select(['id', 'name', 'website', 'reviews'])
            ->whereNotNull('website')
            ->whereRaw('TRIM(website) <> ?', [''])
            ->chunkById(1000, function ($organizations) use (&$domains) {
                foreach ($organizations as $organization) {
                    $normalized = $this->normalizeDomain($organization->website);

                    if (! $normalized) {
                        continue;
                    }

                    $data = [
                        'id' => $organization->id,
                        'name' => $organization->name,
                        'website' => $organization->website,
                        'reviews' => (int) ($organization->reviews ?? 0),
                    ];

                    if (! isset($domains[$normalized])) {
                        $domains[$normalized] = [
                            'keep' => $data,
                            'duplicates' => [],
                        ];

                        continue;
                    }

                    $keep = &$domains[$normalized]['keep'];

                    if (
                        $data['reviews'] > $keep['reviews']
                        || ($data['reviews'] === $keep['reviews'] && $data['id'] < $keep['id'])
                    ) {
                        $domains[$normalized]['duplicates'][] = $keep;
                        $keep = $data;
                    } else {
                        $domains[$normalized]['duplicates'][] = $data;
                    }
                }
            });

        $duplicateGroups = array_filter($domains, static fn ($group) => ! empty($group['duplicates']));

        if (empty($duplicateGroups)) {
            $this->info('No duplicate organizations were found.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('Running in dry-run mode. No deletions will be performed.');
        }

        $totalRemoved = 0;

        foreach ($duplicateGroups as $normalized => $group) {
            $keep = $group['keep'];
            $duplicates = $group['duplicates'];

            $this->info(sprintf(
                'Keeping organization #%d (%s) with %d reviews for website "%s" (normalized: "%s").',
                $keep['id'],
                $keep['name'],
                $keep['reviews'],
                $keep['website'],
                $normalized
            ));

            foreach ($duplicates as $duplicate) {
                $this->line(sprintf(
                    '- Removing duplicate organization #%d (%s) with %d reviews.',
                    $duplicate['id'],
                    $duplicate['name'],
                    $duplicate['reviews']
                ));
            }

            if (! $dryRun) {
                $ids = array_column($duplicates, 'id');

                foreach (array_chunk($ids, 500) as $chunk) {
                    Organization::whereIn('id', $chunk)->delete();
                }
            }

            $totalRemoved += count($duplicates);
        }

        if ($dryRun) {
            $this->info(sprintf('Dry run complete. %d duplicate organization(s) would be removed.', $totalRemoved));
        } else {
            $this->info(sprintf('Removed %d duplicate organization(s).', $totalRemoved));
        }

        return self::SUCCESS;
    }

    /**
     * Normalize a website down to its root host for duplicate comparison.
     */
    protected function normalizeDomain(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! str_contains($value, '://')) {
            $value = 'http://'.$value;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! $host) {
            return null;
        }

        $host = strtolower($host);

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host !== '' ? $host : null;
    }
}
