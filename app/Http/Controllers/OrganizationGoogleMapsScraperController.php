<?php

namespace App\Http\Controllers;

use App\Jobs\Apify\StartApifyActorJob;
use App\Services\Apify\ApifyGoogleMapsScraperService;
use App\Services\Apify\Processors\OrganizationGoogleMapsResultsProcessor;
use App\Models\ApifyRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrganizationGoogleMapsScraperController extends Controller
{
    public function startImport(Request $request)
    {
        $validated = $request->validate([
            'search_term' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'max_places' => 'sometimes|integer|min:1|max:1000',
            'min_rating' => 'sometimes|numeric|min:0|max:5',
            'skip_closed' => 'sometimes|boolean',
        ]);
        StartApifyActorJob::dispatch(
            $validated,
            Auth::id(),
            ApifyGoogleMapsScraperService::class,
            OrganizationGoogleMapsResultsProcessor::class
        );
        return response()->json([
            'message' => 'Google Maps import started successfully. You will be notified when it completes.',
        ]);
    }

    public function getImports()
    {
        $imports = ApifyRun::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        return response()->json($imports);
    }

    public function getImport(ApifyRun $apifyRun)
    {
        if ($apifyRun->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        return response()->json($apifyRun);
    }
}
