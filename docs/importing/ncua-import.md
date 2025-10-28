# NCUA Organization Data Import

This import pipeline updates existing organizations with authoritative financial data sourced from the NCUA quarterly CSV export. It mirrors the HubSpot import UX while focusing exclusively on refreshing NCUA-specific fields; no new organization records are created.

## Workflow Summary

1. **Upload** – An authenticated user selects an NCUA CSV from the Organizations → Import page (`Upload NCUA CSV` button).
2. **API Endpoint** – The file posts to `POST /api/organizations/import/ncua`, handled by `NCUAImportController@store`.
3. **Service Processing** – `NCUAImportService` parses the CSV, normalizes each row, and attempts to match organizations by root domain.
4. **Updates Only** – When a match is found, the service updates the NCUA metric columns and persists changes if any values differ.
5. **Response** – The client receives a JSON summary (`rows_processed`, `updated`, `skipped`, `errors`) which the UI renders as an import report.

## Domain Matching Rules

- Matching uses `App\Support\WebsiteUrl::rootDomain`.
- Schemes, subdomains, ports, paths, and query strings are ignored (`https://www.example.com/path` matches `example.com`).
- Rows missing a valid website domain are skipped and noted in the response errors array.
- Records without a local organization match are skipped; no new organizations are created.

## Updated Columns

The following `organizations` columns are overwritten when values are supplied in the CSV:

| Database Column        | Description                                         |
|------------------------|-----------------------------------------------------|
| `charter_number`       | Institution charter identifier (numeric).          |
| `is_low_income`        | Boolean flag derived from “Low-income designation”. |
| `members`              | Total member count.                                 |
| `assets`               | Total assets in dollars.                            |
| `loans`                | Total loans in dollars.                             |
| `deposits`             | Total deposits in dollars.                          |
| `roaa`                 | Return on average assets (percentage).              |
| `net_worth_ratio`      | Net worth ratio (percentage).                       |
| `loan_to_share_ratio`  | Loan-to-share ratio (percentage).                   |
| `deposit_growth`       | Deposit growth (percentage).                        |
| `loan_growth`          | Loan growth (percentage).                           |
| `asset_growth`         | Asset growth (percentage).                          |
| `member_growth`        | Member growth (percentage).                         |
| `net_worth_growth`     | Net worth growth (percentage).                      |

### CSV Header Expectations

The importer builds a header map on the first row. Provide the exact headings listed below (line breaks in the official extract should be preserved). Any other columns are ignored.

| CSV Header                                                         | Target Column         | Expected Data                                              |
|--------------------------------------------------------------------|-----------------------|------------------------------------------------------------|
| `Charter number`                                                   | `charter_number`      | Digits only; commas stripped (`"12,345"` → `12345`).       |
| `Website`                                                          | (matching only)       | Full URL or bare domain (`http://www.foo.com`).            |
| `Low-income designation`                                           | `is_low_income`       | `Yes`/`No` (case-insensitive).                             |
| `Members`                                                          | `members`             | Whole number (commas allowed).                             |
| `Total assets`                                                     | `assets`              | Whole number currency (commas allowed).                    |
| `Total loans`                                                      | `loans`               | Whole number currency (commas allowed).                    |
| `Total deposits`                                                   | `deposits`            | Whole number currency (commas allowed).                    |
| `Return on average assets`                                         | `roaa`                | Decimal percentage (e.g., `0.13`, `2.874`).                |
| `Net worth ratio (excludes CECL transition provision)`             | `net_worth_ratio`     | Decimal percentage (e.g., `8.78`).                         |
| `Loan-to-share ratio`                                              | `loan_to_share_ratio` | Decimal percentage (e.g., `95.00`).                        |
| `Total deposits,\n4 quarter growth \n(%)`                          | `deposit_growth`      | Decimal percentage; positive or negative.                  |
| `Total loans, \n4 quarter growth \n(%)`                            | `loan_growth`         | Decimal percentage; positive or negative.                  |
| `Total assets, \n4 quarter growth \n(%)`                           | `asset_growth`        | Decimal percentage; positive or negative.                  |
| `Members,\n4 quarter growth \n(%)`                                 | `member_growth`       | Decimal percentage; positive or negative.                  |
| `Net worth, \n4 quarter growth (excludes CECL transition provision)\n(%)` | `net_worth_growth`   | Decimal percentage; positive or negative.                  |

> **Note:** Mulitline headers include literal line breaks in the official CSV. Ensure these remain intact when exporting so the importer can recognize them.

## Validation & Error Handling

- The controller validates that the upload is a CSV/TXT file ≤ 5 MB.
- Missing required headers (`Website`) trigger a 422 JSON response with an error message.
- Parsing issues (e.g., non-numeric currency) are logged and counted as skipped rows with descriptive reasons.
- The service normalizes numbers by stripping commas and retains two decimal places for percentage metrics.

## Frontend Integration

- The Organizations → Import screen now includes an “Update Organizations with NCUA Data” card.
- Upload progress, summary counts, and skipped errors mirror the HubSpot importer experience.
- Import statistics from the JSON response drive the on-screen summary (Processed / Updated / Skipped).

## Usage Checklist

1. Export the latest NCUA CSV and ensure headers remain unaltered.
2. In the CRM, open **Organizations → Import** and upload the file via `Upload NCUA CSV`.
3. Review the summary report for updated counts and any skipped rows.
4. Optionally re-run the upload as new data becomes available; existing metrics will be overwritten with the latest values.
