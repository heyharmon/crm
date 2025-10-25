# Website Ratings

## Rating Scale
- 5 – Excellent
- 4 – Good
- 3 – Okay
- 2 – Poor
- 1 – Bad

Ratings are stored individually per (organization, user) pair. Each rating references a reusable `WebsiteRatingOption` record, which administrators can manage from **Website Rating Options** in the app.

## Aggregates per Organization
Every organization stores three derived fields:
- `website_rating_average`: simple mean of all submitted scores.
- `website_rating_count`: number of submitted ratings.
- `website_rating_weighted`: Bayesian weighted average.

The weighted average dampens volatility for small sample sizes:
```
Weighted = (v / (v + m)) * R + (m / (v + m)) * C
```
- `R` – organization’s simple average.
- `v` – number of ratings for the organization.
- `m` – minimum ratings before fully trusting `R` (configurable via `WEBSITE_RATING_MIN_VOTES`, default 3).
- `C` – global average across all organizations.

When no ratings exist for another organization, `Weighted` falls back to the global average (or `null` if no data exists system-wide).

## API/Frontend Notes
- `/website-rating-options` returns the active 5-point scale; UI uses these for labeling buttons and filters.
- Listing endpoints expose `website_rating_average`, `website_rating_weighted`, `website_rating_summary`, and `website_rating_count` for sorting and display.
- Submitting or clearing a rating immediately returns updated aggregates so the UI can stay in sync.

## Configuration
`config/ratings.php` exposes a single setting:
```php
return [
    'minimum_votes' => env('WEBSITE_RATING_MIN_VOTES', 3),
];
```
Adjust `WEBSITE_RATING_MIN_VOTES` in `.env` to increase or decrease how much early ratings are pulled toward the site-wide average.
