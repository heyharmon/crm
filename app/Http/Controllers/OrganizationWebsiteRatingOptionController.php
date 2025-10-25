<?php

namespace App\Http\Controllers;

use App\Models\OrganizationWebsiteRating;
use App\Models\WebsiteRatingOption;
use App\Services\OrganizationWebsiteRatingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationWebsiteRatingOptionController extends Controller
{
    public function __construct(
        protected OrganizationWebsiteRatingService $ratingService
    ) {
    }

    public function index()
    {
        return WebsiteRatingOption::withCount('ratings')
            ->orderByDesc('score')
            ->orderBy('name')
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:website_rating_options,name',
            'score' => 'required|integer|min:1|max:5',
            'description' => 'nullable|string|max:255',
        ]);

        $validated['slug'] = $this->generateUniqueSlug($validated['name']);

        $option = WebsiteRatingOption::create($validated);

        return response()->json($option, 201);
    }

    public function update(Request $request, WebsiteRatingOption $websiteRatingOption)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100|unique:website_rating_options,name,' . $websiteRatingOption->id,
            'score' => 'sometimes|required|integer|min:1|max:5',
            'description' => 'nullable|string|max:255',
        ]);

        if (array_key_exists('name', $validated) && $validated['name'] !== $websiteRatingOption->name) {
            $validated['slug'] = $this->generateUniqueSlug($validated['name'], $websiteRatingOption->id);
        }

        $websiteRatingOption->update($validated);

        if (array_key_exists('score', $validated)) {
            $organizationIds = OrganizationWebsiteRating::where('website_rating_option_id', $websiteRatingOption->id)
                ->pluck('organization_id');

            OrganizationWebsiteRating::where('website_rating_option_id', $websiteRatingOption->id)
                ->update(['score' => $websiteRatingOption->score]);

            $this->ratingService->refreshAggregatesForOrganizations($organizationIds);
        }

        return response()->json($websiteRatingOption);
    }

    public function destroy(Request $request, WebsiteRatingOption $websiteRatingOption)
    {
        $validated = $request->validate([
            'action' => 'sometimes|in:delete_ratings,reassign',
            'reassign_to_id' => 'nullable|integer|exists:website_rating_options,id',
        ]);

        $action = $validated['action'] ?? 'delete_ratings';

        DB::transaction(function () use ($action, $validated, $websiteRatingOption) {
            $organizationIds = OrganizationWebsiteRating::where('website_rating_option_id', $websiteRatingOption->id)
                ->pluck('organization_id')
                ->all();

            if ($action === 'reassign') {
                $toId = $validated['reassign_to_id'] ?? null;
                if (! $toId || (int) $toId === (int) $websiteRatingOption->id) {
                    abort(422, 'A different target option is required for reassignment.');
                }

                $targetOption = WebsiteRatingOption::findOrFail($toId);

                OrganizationWebsiteRating::where('website_rating_option_id', $websiteRatingOption->id)
                    ->update([
                        'website_rating_option_id' => $targetOption->id,
                        'score' => $targetOption->score,
                    ]);
            } else {
                OrganizationWebsiteRating::where('website_rating_option_id', $websiteRatingOption->id)->delete();
            }

            $websiteRatingOption->delete();

            $this->ratingService->refreshAggregatesForOrganizations($organizationIds);
        });

        return response()->json(['message' => 'Rating option deleted']);
    }

    protected function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'rating-option';
        }
        $slug = $base;
        $counter = 1;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    protected function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $query = WebsiteRatingOption::where('slug', $slug);
        if ($ignoreId) {
            $query->where('id', '<>', $ignoreId);
        }
        return $query->exists();
    }
}
