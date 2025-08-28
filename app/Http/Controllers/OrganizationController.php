<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $query = Organization::query()->withCount('pages');
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('category', 'LIKE', "%{$search}%")
                    ->orWhere('city', 'LIKE', "%{$search}%")
                    ->orWhere('state', 'LIKE', "%{$search}%");
            });
        }
        if ($request->filled('city')) {
            $query->byLocation($request->get('city'));
        }
        if ($request->filled('state')) {
            $query->byLocation(null, $request->get('state'));
        }
        if ($request->filled('category')) {
            $query->byCategory($request->get('category'));
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
                    $query->orderBy($field, $dir);
                }
            }
        } else {
            // Fallback to single sort params (no default sort)
            $sortBy = $request->get('sort_by');
            $sortDirection = $request->get('sort_direction', 'asc');
            if ($sortBy && in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortDirection);
            }
        }
        $organizations = $query->paginate(20);
        return response()->json($organizations);
    }

    public function show(Organization $organization)
    {
        $organization->loadCount('pages');
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
            'category' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ]);
        $organization = Organization::create($validated);
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
            'category' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ]);
        $organization->update($validated);
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
