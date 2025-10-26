# Dashboard

## Purpose
Provides a high-level snapshot of CRM coverage so teams can quickly understand how many organizations exist, where rating gaps remain, and how recently websites have been redesigned.

## Backend
- **`GET /api/dashboard`** (handled by `DashboardController`): aggregates totals for organizations, unrated counts, tracked website pages, per-user rating contributions, rating option distributions, and redesign timing buckets (median days since last redesign plus 1–5 year intervals).

## Frontend
- **`resources/js/pages/Dashboard.vue`**: fetches the dashboard metrics on mount, handles manual refreshes, and presents the data using the app’s card layout.
  - **Summary Cards** show total organizations, unrated organizations, websites rated by the current user, and aggregate page counts.
  - **Rating Distribution** visualizes how many ratings fall into each 5-point option.
  - **Redesign Activity** highlights the median time since redesigns and counts of sites redesigned in the past 1–5 years.

## Navigation
- Dashboard is routed at `/` (`name: 'dashboard'`) and linked from the main navigation bar. Logged-in users land here after authentication.
