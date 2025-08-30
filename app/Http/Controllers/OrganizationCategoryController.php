<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function destroy(Request $request, OrganizationCategory $organizationCategory)
    {
        $validated = $request->validate([
            'action' => 'sometimes|in:disassociate,reassign,destroy',
            'reassign_to_id' => 'nullable|integer|exists:organization_categories,id',
        ]);

        $action = $validated['action'] ?? 'disassociate';

        DB::transaction(function () use ($action, $validated, $organizationCategory) {
            if ($action === 'reassign') {
                $toId = $validated['reassign_to_id'] ?? null;
                if (!$toId || (int) $toId === (int) $organizationCategory->id) {
                    abort(422, 'A different target category is required for reassignment.');
                }
                Organization::where('organization_category_id', $organizationCategory->id)
                    ->update(['organization_category_id' => $toId]);
            } elseif ($action === 'destroy') {
                Organization::where('organization_category_id', $organizationCategory->id)->delete();
            } else { // disassociate
                Organization::where('organization_category_id', $organizationCategory->id)
                    ->update(['organization_category_id' => null]);
            }

            $organizationCategory->delete();
        });

        return response()->json(['message' => 'Category deleted']);
    }
}
