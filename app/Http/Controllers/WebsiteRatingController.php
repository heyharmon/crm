<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationWebsiteRating;
use App\Models\WebsiteRatingOption;
use App\Services\OrganizationWebsiteRatingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebsiteRatingController extends Controller
{
    public function __construct(
        protected OrganizationWebsiteRatingService $ratingService
    ) {}

    public function index(Request $request)
    {
        $query = OrganizationWebsiteRating::with(['organization', 'option'])
            ->where('user_id', Auth::id())
            ->whereHas('organization', function ($q) {
                $q->whereNotNull('website');
            });

        if ($request->has('rating_option_ids')) {
            $ratingOptionIds = is_array($request->rating_option_ids)
                ? $request->rating_option_ids
                : explode(',', $request->rating_option_ids);
            $query->whereIn('website_rating_option_id', $ratingOptionIds);
        }

        $ratings = $query->latest()->get();

        return response()->json($ratings->map(function ($rating) {
            return [
                'id' => $rating->id,
                'organization_id' => $rating->organization_id,
                'organization_name' => $rating->organization->name,
                'organization_website' => $rating->organization->website,
                'organization_assets' => $rating->organization->assets,
                'organization_last_major_redesign_at' => $rating->organization->last_major_redesign_at,
                'website_rating_option_id' => $rating->website_rating_option_id,
                'website_rating_option_name' => $rating->option?->name,
                'website_rating_option_slug' => $rating->option?->slug,
                'score' => $rating->score,
                'created_at' => $rating->created_at,
                'updated_at' => $rating->updated_at,
            ];
        }));
    }

    public function store(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'website_rating_option_id' => 'required|exists:website_rating_options,id',
        ]);

        $option = WebsiteRatingOption::findOrFail($validated['website_rating_option_id']);

        $rating = $this->ratingService->upsertRating($organization, Auth::user(), $option);

        $organization->refresh();

        return response()->json([
            'organization_id' => $organization->id,
            'website_rating_average' => $organization->website_rating_average,
            'website_rating_summary' => $organization->website_rating_summary,
            'website_rating_count' => $organization->website_rating_count,
            'website_rating_weighted' => $organization->website_rating_weighted,
            'my_website_rating_option_id' => $rating->website_rating_option_id,
            'my_website_rating_option_slug' => $rating->option?->slug,
            'my_website_rating_option_name' => $rating->option?->name,
        ], 201);
    }

    public function destroy(Organization $organization)
    {
        $this->ratingService->deleteRating($organization, Auth::user());

        $organization->refresh();

        return response()->json([
            'organization_id' => $organization->id,
            'website_rating_average' => $organization->website_rating_average,
            'website_rating_summary' => $organization->website_rating_summary,
            'website_rating_count' => $organization->website_rating_count,
            'website_rating_weighted' => $organization->website_rating_weighted,
            'my_website_rating_option_id' => null,
            'my_website_rating_option_slug' => null,
            'my_website_rating_option_name' => null,
        ]);
    }
}
