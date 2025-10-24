<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\WebsiteRatingOption;
use App\Services\OrganizationWebsiteRatingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrganizationWebsiteRatingController extends Controller
{
    public function __construct(
        protected OrganizationWebsiteRatingService $ratingService
    ) {
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
            'my_website_rating_option_id' => null,
            'my_website_rating_option_slug' => null,
            'my_website_rating_option_name' => null,
        ]);
    }
}
