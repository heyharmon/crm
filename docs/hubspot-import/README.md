# HubSpot Organization Import

The HubSpot import supplements existing CRM organizations with data from a HubSpot company export. Unlike the NCUA import, this pipeline can create new organizations when a matching domain is not found, but it never overwrites populated fields on existing records.

## Workflow Summary

1. **Upload from UI** – Users navigate to *Organizations → Import* and select `Upload HubSpot CSV`.
2. **Endpoint** – The file posts to `POST /api/organizations/import/hubspot`, handled by `OrganizationHubspotImportController@store`.
3. **Service Processing** – `HubspotOrganizationImportService` reads the CSV, normalizes header labels, and matches rows by website root domain.
4. **Conditional Create/Update**  
   - If the domain matches an existing organization, only missing attributes are filled (e.g., empty phone or street).  
   - If no match exists and the row contains a company name, a new organization is created with the supplied details.
5. **Response & Reporting** – The API returns a JSON summary (`rows_processed`, `imported`, `updated`, `skipped`, `errors`) which the frontend renders as an import report.

## Domain Matching Rules

- Matching uses `App\Support\WebsiteUrl::rootDomain`, ignoring schemes, subdomains, ports, and paths.
- Rows missing a resolvable `Website URL` are skipped with an error entry.
- When multiple rows share a domain, the first successful match seeds the index; subsequent rows update the in-memory cache.

## Field Fill Strategy

- Existing non-empty attributes are preserved.
- Only the following fields are candidates for updates when an existing organization is found:
  - `name`
  - `street`
  - `city`
  - `state`
  - `country`
  - `phone`
- Newly created records receive all mapped values, including the normalized website URL.

## Expected CSV Headers

The importer performs case-insensitive, whitespace-normalized matching. Any of the following headings can provide the mapped field; no other columns are consumed.

| Accepted Header Labels (lowercase after trimming spaces) | Target Field | Expected Data                                                     |
|----------------------------------------------------------|--------------|-------------------------------------------------------------------|
| `company name`                                           | `name`       | Organization name (string).                                       |
| `website url` / `website`                                | `website`    | Full URL or bare domain; used for matching and normalization.     |
| `phone number` / `phone`                                 | `phone`      | Phone number string; formatting preserved.                        |
| `city`                                                   | `city`       | City (string).                                                    |
| `state/region` / `state`                                 | `state`      | State or region (string).                                        |
| `street address` / `address`                             | `street`     | Street address (string).                                          |
| `country/region` / `country`                             | `country`    | Country (string).                                                 |

> **Note:** The first column may include a UTF-8 BOM; the service strips it automatically.

## Validation & Error Handling

- Uploads must be CSV/TXT files ≤ 5 MB; validation errors return a 422 response.
- Missing required headers (`company name` and `website url`) trigger an immediate 422 with a descriptive message.
- Rows without names (for new records) or usable websites are skipped and logged in the `errors` array.
- Any failure to create a new organization (e.g., database constraint) is logged and recorded as a skipped row.

## Frontend UX

- The HubSpot card on *Organizations → Import* mirrors the NCUA experience, showing upload progress, summary counts, and error details.
- `rows_processed`, `imported`, `updated`, and `skipped` values from the API populate the summary grid.

## Usage Checklist

1. Export a company list from HubSpot as CSV, keeping default headers intact.
2. Visit **Organizations → Import** and upload using `Upload HubSpot CSV`.
3. Review the summary to confirm imported and updated counts.
4. Address any skipped rows by correcting source data and re-uploading if needed.
