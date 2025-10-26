# Organizations Domain

## Purpose
Stores businesses or clients in the CRM and provides browsing, creation and editing interfaces.

## Backend
- **OrganizationController**: supports listing with filters, viewing details, creating, updating, deleting and restoring organizations. Website ratings expose both the simple average (`website_rating_average`) and Bayesian weighted score (`website_rating_weighted`), along with the current user’s selection.
- **OrganizationCategoryController**: CRUD for reusable organization categories.
- **WebsiteRatingOptionController**: CRUD for reusable rating options (e.g., Good/Okay/Bad) with reassignment tools when deleting.
- **WebsiteRatingController**: handles per-user rating submissions and clearing ratings.
- **Organization model**: represents an organization with address, contact, website status, and aggregate rating data; related `Page` records hold scraped website pages.
- **WebsiteRatingOption & OrganizationWebsiteRating models**: manage rating metadata and individual user ratings.
- **GoogleMapsOrganizationImportService**: maps Google Maps scraper results into organization records and syncs categories.
- **HubspotOrganizationImportService**: parses HubSpot CSV exports, matches by website domain, and fills in missing organization data without overwriting existing values.
- **CheckOrganizationWebsiteStatus job**: dispatched whenever an organization is created (including HubSpot CSV and Google Maps imports) to normalize the website URL, ping the host, and store the result (`up`, `down`, `redirected`, `unknown`). Unreachable websites (missing URL, timeouts, or certificate issues) are all recorded as `down`.

## Frontend
- **Pages**: `resources/js/pages/organizations/Index.vue` and `Browse.vue` list organizations; `Show.vue` displays details; `Create.vue` and `Edit.vue` handle forms; `Import.vue` triggers scraper-based imports.
- **Organization Categories**: `resources/js/pages/organization-categories/Index.vue` manages category records.
- **Website Rating Options**: `resources/js/pages/websites/WebsiteRatingOptions.vue` manages rating option records.
- **Website Ratings**: `docs/website-ratings/README.md` describes the 5-point scale and Bayesian weighting.
- **Components**: `resources/js/components/organizations/OrganizationForm.vue` and `resources/js/components/organizations/OrganizationFilters.vue` share form and filter UI.
- **Store**: `resources/js/stores/organizationStore.js` manages organization data, filters and pagination.
