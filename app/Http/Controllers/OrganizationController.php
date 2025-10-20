<?php

namespace App\Http\Controllers;

use App\Models\Organization;
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
        if ($request->filled('category')) {
            $query->where('organization_categories.name', 'LIKE', "%{$request->get('category')}%");
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
        if ($request->filled('website_rating')) {
            $rating = $request->get('website_rating');
            if (in_array($rating, ['good', 'okay', 'bad'])) {
                $query->where('organizations.website_rating', $rating);
            } elseif ($rating === 'none') {
                $query->where(function ($q) {
                    $q->whereNull('organizations.website_rating')
                        ->orWhere('organizations.website_rating', '=', '');
                });
            }
        }
        $allowedSorts = ['name', 'city', 'state', 'category', 'score', 'reviews', 'website_rating', 'created_at'];

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
                } else {
                    $query->orderBy('organizations.' . $sortBy, $sortDirection);
                }
            }
        }
        $organizations = $query->paginate(20);
        return response()->json($organizations);
    }

    public function show(Organization $organization)
    {
        $organization->load(['category'])->loadCount('pages');
        return response()->json($organization);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'banner' => 'nullable|url|max:500',
            'score' => 'nullable|numeric|min:0|max:5',
            'reviews' => 'nullable|integer|min:0',
            'street' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country_code' => 'nullable|string|size:2',
            'website' => 'nullable|url|max:500',
            'website_rating' => 'nullable|in:good,okay,bad',
            'phone' => 'nullable|string|max:50',
            'organization_category_id' => 'nullable|exists:organization_categories,id',
            'notes' => 'nullable|string|max:2000',
        ]);
        $organization = Organization::create($validated);
        $organization->load('category');
        return response()->json($organization, 201);
    }

    public function update(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'banner' => 'nullable|url|max:500',
            'score' => 'nullable|numeric|min:0|max:5',
            'reviews' => 'nullable|integer|min:0',
            'street' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country_code' => 'nullable|string|size:2',
            'website' => 'nullable|url|max:500',
            'website_rating' => 'nullable|in:good,okay,bad',
            'phone' => 'nullable|string|max:50',
            'organization_category_id' => 'nullable|exists:organization_categories,id',
            'notes' => 'nullable|string|max:2000',
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
