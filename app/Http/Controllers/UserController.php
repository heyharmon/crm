<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OrganizationWebsiteRatingService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('created_at', 'desc')->get();

        return response()->json($users);
    }

    public function show(User $user)
    {
        $user->load(['websiteRatings.organization', 'websiteRatings.option']);

        return response()->json($user);
    }

    public function destroy(User $user, OrganizationWebsiteRatingService $ratingService)
    {
        // Get all organization IDs that this user has rated
        $organizationIds = $user->websiteRatings()->pluck('organization_id')->unique();

        // Delete the user (cascade will delete their ratings)
        $user->delete();

        // Recalculate aggregates for all affected organizations
        $ratingService->refreshAggregatesForOrganizations($organizationIds);

        return response()->json(null, 204);
    }
}
