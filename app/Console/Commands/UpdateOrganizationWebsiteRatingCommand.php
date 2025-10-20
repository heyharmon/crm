<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use SplFileObject;

class UpdateOrganizationWebsiteRatingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'organizations:update-website-rating {csv_path : Absolute or relative path to the CSV file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update organization website ratings using an external CSV file.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $csvPath = $this->argument('csv_path');
        if (! file_exists($csvPath)) {
            $this->error("CSV file not found at path: {$csvPath}");

            return self::FAILURE;
        }

        if (! is_readable($csvPath)) {
            $this->error("CSV file is not readable: {$csvPath}");

            return self::FAILURE;
        }

        $targets = $this->loadTargetsFromCsv($csvPath);

        if ($targets->isEmpty()) {
            $this->info('No matching websites with a "terrible" rating were found in the CSV.');

            return self::SUCCESS;
        }

        [$updatedCount, $skippedCount] = $this->updateOrganizations($targets);

        $this->info("Finished updating organizations. Updated: {$updatedCount}, Skipped (already bad): {$skippedCount}");

        return self::SUCCESS;
    }

    /**
     * Load websites that have a "terrible" rating from the CSV file.
     */
    protected function loadTargetsFromCsv(string $csvPath): Collection
    {
        $file = new SplFileObject($csvPath, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $targets = collect();

        foreach ($file as $rowNumber => $row) {
            if ($row === [null] || $row === false) {
                continue;
            }

            $website = trim((string) Arr::get($row, 0, ''));
            $rating = strtolower(trim((string) Arr::get($row, 1, '')));

            if ($rowNumber === 0 && $this->looksLikeHeader($website, $rating)) {
                continue;
            }

            if ($website === '' || $rating !== 'terrible') {
                continue;
            }

            $normalized = $this->normalizeDomain($website);

            if ($normalized) {
                $targets->put($normalized, 'bad');
            }
        }

        return $targets;
    }

    /**
     * Update organizations that match the provided website domains.
     */
    protected function updateOrganizations(Collection $targets): array
    {
        $updated = 0;
        $skipped = 0;

        Organization::withTrashed()
            ->whereNotNull('website')
            ->select(['id', 'website', 'website_rating'])
            ->chunkById(200, function ($organizations) use (&$updated, &$skipped, $targets) {
                foreach ($organizations as $organization) {
                    $domain = $this->normalizeDomain($organization->website);

                    if (! $domain || ! $targets->has($domain)) {
                        continue;
                    }

                    if ($organization->website_rating === $targets->get($domain)) {
                        $skipped++;
                        continue;
                    }

                    $organization->website_rating = $targets->get($domain);
                    $organization->save();

                    $updated++;
                }
            });

        return [$updated, $skipped];
    }

    /**
     * Determine if the first row looks like a header row.
     */
    protected function looksLikeHeader(string $website, string $rating): bool
    {
        $website = strtolower($website);

        return in_array($website, ['website', 'url', 'domain'], true) || $rating === 'rating';
    }

    /**
     * Normalize a URL string to its root domain.
     */
    protected function normalizeDomain(string $value): ?string
    {
        $value = trim($value);

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
