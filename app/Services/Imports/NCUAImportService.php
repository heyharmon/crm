<?php

namespace App\Services\Imports;

use App\Models\Organization;
use App\Support\WebsiteUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class NCUAImportService
{
    private const COLUMN_MAP = [
        'charter number' => 'charter_number',
        'website' => 'website',
        'low-income designation' => 'is_low_income',
        'members' => 'members',
        'total assets' => 'assets',
        'total loans' => 'loans',
        'total deposits' => 'deposits',
        'return on average assets' => 'roaa',
        'net worth ratio (excludes cecl transition provision)' => 'net_worth_ratio',
        'loan-to-share ratio' => 'loan_to_share_ratio',
        'total deposits, 4 quarter growth (%)' => 'deposit_growth',
        'total loans, 4 quarter growth (%)' => 'loan_growth',
        'total assets, 4 quarter growth (%)' => 'asset_growth',
        'members, 4 quarter growth (%)' => 'member_growth',
        'net worth, 4 quarter growth (excludes cecl transition provision) (%)' => 'net_worth_growth',
    ];

    private array $stats = [
        'rows_processed' => 0,
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
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];
        $this->domainIndex = [];
    }

    private function seedDomainIndex(): void
    {
        Organization::query()
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
            throw new \InvalidArgumentException('The NCUA CSV must include a "Website" column.');
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
            $this->recordSkip($rowNumber, 'Missing or invalid Website');
            return;
        }

        $organization = $this->findOrganizationByDomain($rootDomain);
        if (!$organization) {
            $this->recordSkip($rowNumber, 'No matching organization found');
            return;
        }

        try {
            $updates = $this->mapPayloadToUpdates($payload);
        } catch (\Throwable $exception) {
            Log::warning('Failed to map NCUA row to organization update', [
                'error' => $exception->getMessage(),
                'row' => $rowNumber,
                'payload' => $payload,
            ]);
            $this->recordSkip($rowNumber, 'Unable to parse numeric values');
            return;
        }

        if (empty($updates)) {
            $this->recordSkip($rowNumber, 'No updateable fields found');
            return;
        }

        $organization->fill($updates);

        if ($organization->isDirty(array_keys($updates))) {
            try {
                $organization->save();
                $this->stats['updated']++;
                $domain = WebsiteUrl::rootDomain($organization->website);
                if ($domain) {
                    $this->domainIndex[$domain] = $organization;
                }
            } catch (\Throwable $exception) {
                Log::warning('Failed to save organization during NCUA import', [
                    'error' => $exception->getMessage(),
                    'organization_id' => $organization->id,
                    'row' => $rowNumber,
                ]);
                $this->recordSkip($rowNumber, 'Unable to update organization record');
            }
            return;
        }

        $this->stats['skipped']++;
    }

    private function findOrganizationByDomain(string $domain): ?Organization
    {
        return $this->domainIndex[$domain] ?? null;
    }

    private function mapPayloadToUpdates(array $payload): array
    {
        $updates = [];

        if (isset($payload['charter_number'])) {
            $updates['charter_number'] = $this->parseInteger($payload['charter_number']);
        }

        if (array_key_exists('is_low_income', $payload)) {
            $updates['is_low_income'] = $this->parseBoolean($payload['is_low_income']);
        }

        if (isset($payload['members'])) {
            $updates['members'] = $this->parseInteger($payload['members']);
        }

        if (isset($payload['assets'])) {
            $updates['assets'] = $this->parseInteger($payload['assets']);
        }

        if (isset($payload['loans'])) {
            $updates['loans'] = $this->parseInteger($payload['loans']);
        }

        if (isset($payload['deposits'])) {
            $updates['deposits'] = $this->parseInteger($payload['deposits']);
        }

        if (isset($payload['roaa'])) {
            $updates['roaa'] = $this->parseDecimal($payload['roaa']);
        }

        if (isset($payload['net_worth_ratio'])) {
            $updates['net_worth_ratio'] = $this->parseDecimal($payload['net_worth_ratio']);
        }

        if (isset($payload['loan_to_share_ratio'])) {
            $updates['loan_to_share_ratio'] = $this->parseDecimal($payload['loan_to_share_ratio']);
        }

        if (isset($payload['deposit_growth'])) {
            $updates['deposit_growth'] = $this->parseDecimal($payload['deposit_growth']);
        }

        if (isset($payload['loan_growth'])) {
            $updates['loan_growth'] = $this->parseDecimal($payload['loan_growth']);
        }

        if (isset($payload['asset_growth'])) {
            $updates['asset_growth'] = $this->parseDecimal($payload['asset_growth']);
        }

        if (isset($payload['member_growth'])) {
            $updates['member_growth'] = $this->parseDecimal($payload['member_growth']);
        }

        if (isset($payload['net_worth_growth'])) {
            $updates['net_worth_growth'] = $this->parseDecimal($payload['net_worth_growth']);
        }

        return array_filter(
            $updates,
            static fn ($value) => $value !== null
        );
    }

    private function parseInteger(string $value): ?int
    {
        $normalized = str_replace(',', '', $value);
        $normalized = preg_replace('/[^\d\.\-]/', '', $normalized);
        if ($normalized === '' || $normalized === '-' || $normalized === null) {
            return null;
        }

        $number = (float) $normalized;

        return (int) round($number);
    }

    private function parseDecimal(string $value): ?string
    {
        $normalized = str_replace(',', '', $value);
        $normalized = preg_replace('/[^\d\.\-]/', '', $normalized);
        if ($normalized === '' || $normalized === '-' || $normalized === null) {
            return null;
        }

        $number = (float) $normalized;

        return number_format(round($number, 2), 2, '.', '');
    }

    private function parseBoolean(string $value): ?bool
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, ['yes', 'y', 'true', '1'], true)) {
            return true;
        }

        if (in_array($normalized, ['no', 'n', 'false', '0'], true)) {
            return false;
        }

        return null;
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
