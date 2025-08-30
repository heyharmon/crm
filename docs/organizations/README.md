# Organizations Domain

## Purpose
Stores businesses or clients in the CRM and provides browsing, creation and editing interfaces.

## Backend
- **OrganizationController**: supports listing with filters, viewing details, creating, updating, deleting and restoring organizations.
- **OrganizationCategoryController**: CRUD for reusable organization categories.
- **Organization model**: represents an organization with address, contact and rating data; related `Page` records hold scraped website pages.
- **OrganizationCategory model**: labels organizations and is created automatically during imports.
- **OrganizationImportService**: maps external scraper results into organization records and syncs categories.

## Frontend
- **Pages**: `resources/js/pages/organizations/Index.vue` and `Browse.vue` list organizations; `Show.vue` displays details; `Create.vue` and `Edit.vue` handle forms; `Import.vue` triggers scraper-based imports.
- **Organization Categories**: `resources/js/pages/organization-categories/Index.vue` manages category records.
- **Components**: `resources/js/components/OrganizationForm.vue` and `OrganizationFilters.vue` share form and filter UI.
- **Store**: `resources/js/stores/organizationStore.js` manages organization data, filters and pagination.
