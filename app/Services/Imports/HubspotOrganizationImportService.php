<?php

namespace App\Services\Imports;

use App\Models\Organization;
use App\Support\WebsiteUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class HubspotOrganizationImportService
{
    private const COLUMN_MAP = [
        'company name' => 'name',
        'website url' => 'website',
        'website' => 'website',
        'phone number' => 'phone',
        'phone' => 'phone',
        'city' => 'city',
        'state/region' => 'state',
        'state' => 'state',
        'street address' => 'street',
        'address' => 'street',
        'country/region' => 'country',
        'country' => 'country',
    ];

    private array $stats = [
        'rows_processed' => 0,
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => [],
    ];

    /**
     * @var array<string, Organization>
     */
    private array $domainIndex = [];

    public function import(UploadedFile $file): array
    {
        $this->resetState();
        $this->seedDomainIndex();

        $delimiter = $this->detectDelimiter($file->getRealPath());
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new \RuntimeException('Unable to read the uploaded file.');
        }

        try {
            $headerMap = null;
            $rowNumber = 0;

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNumber++;

                if ($rowNumber === 1) {
                    $headerMap = $this->buildHeaderMap($row);
                    continue;
                }

                if ($this->isEmptyRow($row) || !$headerMap) {
                    continue;
                }

                $this->stats['rows_processed']++;
                $payload = $this->mapRowToPayload($headerMap, $row);
                $this->handleRow($payload, $rowNumber);
            }
        } finally {
            fclose($handle);
        }

        return $this->stats;
    }

    private function resetState(): void
    {
        $this->stats = [
            'rows_processed' => 0,
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];
        $this->domainIndex = [];
    }

    private function seedDomainIndex(): void
    {
        Organization::query()
            ->select(['id', 'name', 'website', 'phone', 'street', 'city', 'state', 'country'])
            ->whereNotNull('website')
            ->whereRaw('TRIM(website) <> ?', [''])
            ->orderBy('id')
            ->chunkById(500, function ($organizations) {
                foreach ($organizations as $organization) {
                    $domain = WebsiteUrl::rootDomain($organization->website);
                    if ($domain && !isset($this->domainIndex[$domain])) {
                        $this->domainIndex[$domain] = $organization;
                    }
                }
            });
    }

    private function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return ',';
        }

        $firstLine = fgets($handle) ?: '';
        fclose($handle);

        if (str_contains($firstLine, "\t") && !str_contains($firstLine, ',')) {
            return "\t";
        }

        $commaCount = substr_count($firstLine, ',');
        $tabCount = substr_count($firstLine, "\t");

        return $tabCount > $commaCount ? "\t" : ',';
    }

    private function buildHeaderMap(array $rawHeader): array
    {
        $headerMap = [];

        foreach ($rawHeader as $index => $label) {
            if ($index === 0) {
                $label = $this->stripBom($label);
            }

            $normalized = $this->normalizeHeaderLabel($label);
            if ($normalized && isset(self::COLUMN_MAP[$normalized])) {
                $headerMap[$index] = self::COLUMN_MAP[$normalized];
            }
        }

        if (!in_array('website', $headerMap, true)) {
            throw new \InvalidArgumentException('The HubSpot CSV must include a "Website URL" column.');
        }

        if (!in_array('name', $headerMap, true)) {
            throw new \InvalidArgumentException('The HubSpot CSV must include a "Company name" column.');
        }

        return $headerMap;
    }

    private function normalizeHeaderLabel(?string $label): ?string
    {
        $clean = trim((string) $label);
        if ($clean === '') {
            return null;
        }

        $clean = preg_replace('/\s+/', ' ', $clean);

        return strtolower($clean);
    }

    private function stripBom(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_replace('/^\xEF\xBB\xBF/', '', $value);
    }

    /**
     * @param array<int, string> $headerMap
     */
    private function mapRowToPayload(array $headerMap, array $row): array
    {
        $payload = [];

        foreach ($headerMap as $index => $field) {
            if (!array_key_exists($index, $row)) {
                continue;
            }

            $value = trim((string) $row[$index]);
            if ($value === '') {
                continue;
            }

            $payload[$field] = $value;
        }

        return $payload;
    }

    private function handleRow(array $payload, int $rowNumber): void
    {
        $website = $payload['website'] ?? null;
        $rootDomain = WebsiteUrl::rootDomain($website);
        if (!$rootDomain) {
            $this->recordSkip($rowNumber, 'Missing or invalid Website URL');
            return;
        }

        $payload['website'] = WebsiteUrl::normalize($website);
        $organization = $this->findOrganizationByDomain($rootDomain);

        if ($organization) {
            $updated = $this->fillMissingFields($organization, $payload);
            if ($updated) {
                $this->stats['updated']++;
                $this->domainIndex[$rootDomain] = $organization;
            } else {
                $this->stats['skipped']++;
            }
            return;
        }

        if (empty($payload['name'])) {
            $this->recordSkip($rowNumber, 'Missing Company name');
            return;
        }

        try {
            $newOrganization = Organization::create([
                'name' => $payload['name'],
                'website' => $payload['website'],
                'phone' => $payload['phone'] ?? null,
                'street' => $payload['street'] ?? null,
                'city' => $payload['city'] ?? null,
                'state' => $payload['state'] ?? null,
                'country' => $payload['country'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to create organization during HubSpot import', [
                'error' => $exception->getMessage(),
                'row' => $rowNumber,
                'payload' => $payload,
            ]);
            $this->recordSkip($rowNumber, 'Unable to create organization record');
            return;
        }

        $this->stats['imported']++;
        $this->domainIndex[$rootDomain] = $newOrganization;
    }

    private function findOrganizationByDomain(string $domain): ?Organization
    {
        if (isset($this->domainIndex[$domain])) {
            return $this->domainIndex[$domain];
        }

        return null;
    }

    private function fillMissingFields(Organization $organization, array $payload): bool
    {
        $fields = ['name', 'street', 'city', 'state', 'country', 'phone'];
        $dirty = false;

        foreach ($fields as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            if (!$this->hasValue($payload[$field])) {
                continue;
            }
            if ($this->hasValue($organization->{$field})) {
                continue;
            }
            $organization->{$field} = $payload[$field];
            $dirty = true;
        }

        if ($dirty) {
            $organization->save();
        }

        return $dirty;
    }

    private function hasValue($value): bool
    {
        if ($value === null) {
            return false;
        }

        return trim((string) $value) !== '';
    }

    private function recordSkip(int $rowNumber, string $reason): void
    {
        $this->stats['skipped']++;
        $this->stats['errors'][] = [
            'row' => $rowNumber,
            'reason' => $reason,
        ];
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }
}
