<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationWebsiteRating;
use App\Models\WebsiteRatingOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $query = Organization::query()
            ->with(['category'])
            // Add the pages_count subselect
            ->withCount('pages')
            // Join for filtering/sorting by category without clobbering selects
            ->leftJoin('organization_categories', 'organization_categories.id', '=', 'organizations.organization_category_id')
            // Append base columns; keep the withCount-added select intact
            ->addSelect('organizations.*');

        $userId = Auth::id();
        if ($userId) {
            $query->addSelect([
                'my_website_rating_option_id' => OrganizationWebsiteRating::select('website_rating_option_id')
                    ->whereColumn('organization_id', 'organizations.id')
                    ->where('user_id', $userId)
                    ->limit(1),
            ]);
        }
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('organizations.name', 'LIKE', "%{$search}%")
                    ->orWhere('organization_categories.name', 'LIKE', "%{$search}%")
                    ->orWhere('organizations.city', 'LIKE', "%{$search}%")
                    ->orWhere('organizations.state', 'LIKE', "%{$search}%");
            });
        }
        if ($request->filled('city')) {
            $query->byLocation($request->get('city'));
        }
        if ($request->filled('state')) {
            $query->byLocation(null, $request->get('state'));
        }
        if ($request->filled('country')) {
            $country = $request->get('country');
            $query->where('organizations.country', 'LIKE', "%{$country}%");
        }

        if ($userId && $request->filled('my_website_rating')) {
            $myRatingFilter = $request->get('my_website_rating');
            if ($myRatingFilter === 'none') {
                $query->whereDoesntHave('websiteRatings', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                });
            } elseif ($myRatingFilter === 'any') {
                $query->whereHas('websiteRatings', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                });
            }
        }
        if ($request->filled('category_ids')) {
            $categoryIds = $request->input('category_ids');
            if (is_array($categoryIds) && !empty($categoryIds)) {
                $query->where(function ($q) use ($categoryIds) {
                    // Check if null is in the array (for "No Category")
                    if (in_array(null, $categoryIds, true)) {
                        $q->whereNull('organizations.organization_category_id');
                        // Remove null from array for the whereIn check
                        $categoryIds = array_filter($categoryIds, fn($id) => $id !== null);
                    }
                    // If there are still category IDs after filtering out null
                    if (!empty($categoryIds)) {
                        $q->orWhereIn('organizations.organization_category_id', $categoryIds);
                    }
                });
            }
        }
        if ($request->filled('type')) {
            $query->where('organizations.type', 'LIKE', "%{$request->get('type')}%");
        }
        if ($request->filled('website')) {
            $websiteFilter = $request->get('website');
            if ($websiteFilter === 'present') {
                $query->whereNotNull('organizations.website')
                    ->where('organizations.website', '!=', '');
            } elseif ($websiteFilter === 'missing') {
                $query->where(function ($q) {
                    $q->whereNull('organizations.website')
                        ->orWhere('organizations.website', '=', '');
                });
            }
        }
        if ($request->filled('last_redesign')) {
            $redesignFilter = $request->get('last_redesign');
            if ($redesignFilter === 'has_date') {
                $query->whereNotNull('organizations.last_major_redesign_at');
            } elseif ($redesignFilter === 'no_date') {
                $query->whereNull('organizations.last_major_redesign_at');
            }
        }
        if ($request->filled('last_redesign_actual')) {
            $redesignActualFilter = $request->get('last_redesign_actual');
            if ($redesignActualFilter === 'has_date') {
                $query->whereNotNull('organizations.last_major_redesign_at_actual');
            } elseif ($redesignActualFilter === 'no_date') {
                $query->whereNull('organizations.last_major_redesign_at_actual');
            }
        }
        if ($request->filled('cms')) {
            $cms = $request->get('cms');
            $query->where('organizations.cms', 'LIKE', '%' . $cms . '%');
        }
        if ($request->filled('pages_min')) {
            $pagesMin = (int) $request->get('pages_min');
            $query->whereRaw('(SELECT COUNT(*) FROM pages WHERE pages.organization_id = organizations.id) >= ?', [$pagesMin]);
        }
        if ($request->filled('pages_max')) {
            $pagesMax = (int) $request->get('pages_max');
            $query->whereRaw('(SELECT COUNT(*) FROM pages WHERE pages.organization_id = organizations.id) <= ?', [$pagesMax]);
        }
        if ($request->filled('assets_min')) {
            $query->where('organizations.assets', '>=', $request->get('assets_min'));
        }
        if ($request->filled('assets_max')) {
            $query->where('organizations.assets', '<=', $request->get('assets_max'));
        }
        if ($request->filled('asset_growth_min')) {
            $query->where('organizations.asset_growth', '>=', $request->get('asset_growth_min'));
        }
        if ($request->filled('asset_growth_max')) {
            $query->where('organizations.asset_growth', '<=', $request->get('asset_growth_max'));
        }
        if ($request->filled('last_redesign_year_min')) {
            $yearMin = $request->get('last_redesign_year_min');
            $query->whereYear('organizations.last_major_redesign_at', '>=', $yearMin);
        }
        if ($request->filled('last_redesign_year_max')) {
            $yearMax = $request->get('last_redesign_year_max');
            $query->whereYear('organizations.last_major_redesign_at', '<=', $yearMax);
        }
        if ($request->filled('website_rating')) {
            $rating = $request->get('website_rating');
            if ($rating === 'none') {
                $query->whereNull('organizations.website_rating_summary');
            } else {
                $query->where('organizations.website_rating_summary', $rating);
            }
        }

        $websiteStatus = $request->input('website_status');
        if ($websiteStatus) {
            $statuses = is_array($websiteStatus) ? $websiteStatus : [$websiteStatus];
            $allowedStatuses = [
                Organization::WEBSITE_STATUS_UP,
                Organization::WEBSITE_STATUS_DOWN,
                Organization::WEBSITE_STATUS_REDIRECTED,
                Organization::WEBSITE_STATUS_UNKNOWN,
            ];
            $statuses = array_values(array_intersect($statuses, $allowedStatuses));
            if (!empty($statuses)) {
                $query->whereIn('organizations.website_status', $statuses);
            }
        }

        $randomize = $request->boolean('random');
        $allowedSorts = ['name', 'type', 'city', 'state', 'country', 'category', 'cms', 'pages_count', 'score', 'reviews', 'website_rating', 'website_rating_average', 'website_rating_weighted', 'created_at'];

        if ($randomize) {
            $query->inRandomOrder();
        } else {
            // Support multi-sort via sort[]="field:direction"
            $sorts = $request->input('sort', []);
            if (!is_array($sorts)) {
                $sorts = [$sorts];
            }

            if (count($sorts) > 0) {
                foreach ($sorts as $s) {
                    if (!is_string($s)) continue;
                    [$field, $dir] = array_pad(explode(':', $s), 2, 'desc');
                    $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';
                    if (in_array($field, $allowedSorts)) {
                        if ($field === 'category') {
                            $query->orderBy('organization_categories.name', $dir);
                        } elseif ($field === 'type') {
                            $query->orderBy('organizations.type', $dir);
                        } elseif ($field === 'website_rating') {
                            $query->orderBy('organizations.website_rating_average', $dir);
                        } elseif ($field === 'cms') {
                            $query->orderBy('organizations.cms', $dir);
                        } elseif ($field === 'website_rating_weighted') {
                            $query->orderBy('organizations.website_rating_weighted', $dir);
                        } elseif ($field === 'pages_count') {
                            $query->orderBy('pages_count', $dir);
                        } else {
                            $query->orderBy('organizations.' . $field, $dir);
                        }
                    }
                }
            } else {
                // Fallback to single sort params (no default sort)
                $sortBy = $request->get('sort_by');
                $sortDirection = $request->get('sort_direction', 'asc');
                if ($sortBy && in_array($sortBy, $allowedSorts)) {
                    if ($sortBy === 'category') {
                        $query->orderBy('organization_categories.name', $sortDirection);
                    } elseif ($sortBy === 'type') {
                        $query->orderBy('organizations.type', $sortDirection);
                    } elseif ($sortBy === 'website_rating') {
                        $query->orderBy('organizations.website_rating_average', $sortDirection);
                    } elseif ($sortBy === 'cms') {
                        $query->orderBy('organizations.cms', $sortDirection);
                    } elseif ($sortBy === 'website_rating_weighted') {
                        $query->orderBy('organizations.website_rating_weighted', $sortDirection);
                    } elseif ($sortBy === 'pages_count') {
                        $query->orderBy('pages_count', $sortDirection);
                    } else {
                        $query->orderBy('organizations.' . $sortBy, $sortDirection);
                    }
                }
            }
        }

        $perPage = (int) $request->input('per_page', 100);
        $perPage = max(1, min($perPage, 200));

        $organizations = $query->paginate($perPage);
        return response()->json($organizations);
    }

    public function show(Organization $organization)
    {
        $organization->load([
            'category',
            'websiteRedesigns' => function ($query) {
                $query->latest('after_captured_at')->limit(20);
            },
        ])->loadCount('pages');
        if (Auth::check()) {
            $myRating = $organization->websiteRatings()
                ->where('user_id', Auth::id())
                ->with('option')
                ->first();

            $organization->setAttribute('my_website_rating_option_id', $myRating?->website_rating_option_id);
            $organization->setAttribute('my_website_rating_option_slug', $myRating?->option?->slug);
            $organization->setAttribute('my_website_rating_option_name', $myRating?->option?->name);
        }

        return response()->json($organization);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:255',
            'banner' => 'nullable|url|max:500',
            'score' => 'nullable|numeric|min:0|max:5',
            'reviews' => 'nullable|integer|min:0',
            'street' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'website' => 'nullable|url|max:500',
            'cms' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'organization_category_id' => 'nullable|exists:organization_categories,id',
            'notes' => 'nullable|string|max:2000',
            'last_major_redesign_at_actual' => 'nullable|date',
        ]);
        $organization = Organization::create($validated);
        $organization->load('category');
        return response()->json($organization, 201);
    }

    public function update(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:255',
            'banner' => 'nullable|url|max:500',
            'score' => 'nullable|numeric|min:0|max:5',
            'reviews' => 'nullable|integer|min:0',
            'street' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'website' => 'nullable|url|max:500',
            'cms' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'organization_category_id' => 'nullable|exists:organization_categories,id',
            'notes' => 'nullable|string|max:2000',
            'last_major_redesign_at' => 'nullable|date',
            'last_major_redesign_at_actual' => 'nullable|date',
        ]);
        $organization->update($validated);
        $organization->load('category');
        return response()->json($organization);
    }

    public function destroy(Organization $organization)
    {
        $organization->delete();
        return response()->json(['message' => 'Organization deleted successfully']);
    }

    public function restore($id)
    {
        $organization = Organization::withTrashed()->findOrFail($id);
        $organization->restore();
        return response()->json($organization);
    }
}
