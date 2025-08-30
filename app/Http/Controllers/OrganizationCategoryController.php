<?php

namespace App\Http\Controllers;

use App\Models\OrganizationCategory;
use Illuminate\Http\Request;

class OrganizationCategoryController extends Controller
{
    public function index()
    {
        return OrganizationCategory::orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:organization_categories,name',
        ]);

        $category = OrganizationCategory::create($validated);
        return response()->json($category, 201);
    }

    public function update(Request $request, OrganizationCategory $organizationCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:organization_categories,name,' . $organizationCategory->id,
        ]);

        $organizationCategory->update($validated);
        return response()->json($organizationCategory);
    }

    public function destroy(OrganizationCategory $organizationCategory)
    {
        $organizationCategory->delete();
        return response()->json(['message' => 'Category deleted']);
    }
}
