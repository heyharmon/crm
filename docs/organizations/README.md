# Organizations Domain

## Purpose
Stores businesses or clients in the CRM and provides browsing, creation and editing interfaces.

## Backend
- **OrganizationController**: supports listing with filters, viewing details, creating, updating, deleting and restoring organizations. Website ratings are exposed as an aggregate (`website_rating_average`, `website_rating_summary`, `website_rating_count`) plus the current user’s selection.
- **OrganizationCategoryController**: CRUD for reusable organization categories.
- **WebsiteRatingOptionController**: CRUD for reusable rating options (e.g., Good/Okay/Bad) with reassignment tools when deleting.
- **OrganizationWebsiteRatingController**: handles per-user rating submissions and clearing ratings.
- **Organization model**: represents an organization with address, contact, and aggregate rating data; related `Page` records hold scraped website pages.
- **WebsiteRatingOption & OrganizationWebsiteRating models**: manage rating metadata and individual user ratings.
- **OrganizationImportService**: maps external scraper results into organization records and syncs categories.

## Frontend
- **Pages**: `resources/js/pages/organizations/Index.vue` and `Browse.vue` list organizations; `Show.vue` displays details; `Create.vue` and `Edit.vue` handle forms; `Import.vue` triggers scraper-based imports.
- **Organization Categories**: `resources/js/pages/organization-categories/Index.vue` manages category records.
- **Website Rating Options**: `resources/js/pages/website-rating-options/WebsiteRatingOptionsIndex.vue` manages rating option records.
- **Components**: `resources/js/components/OrganizationForm.vue` and `OrganizationFilters.vue` share form and filter UI.
- **Store**: `resources/js/stores/organizationStore.js` manages organization data, filters and pagination.
