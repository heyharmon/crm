<?php

namespace App\Http\Controllers;

use App\Jobs\StartWebScrapingJob;
use App\Models\ApifyRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebScraperController extends Controller
{
    public function startScraping(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'max_pages' => 'sometimes|integer|min:1|max:200',
            'max_depth' => 'sometimes|integer|min:1|max:5',
        ]);

        StartWebScrapingJob::dispatch($validated, Auth::id());

        return response()->json([
            'message' => 'Web scraping started successfully. You will be notified when it completes.',
        ]);
    }

    public function getScrapingRuns()
    {
        $runs = ApifyRun::where('user_id', Auth::id())
            ->where('actor_id', 'firecrawl-map')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($runs);
    }

    public function getScrapingRun(ApifyRun $apifyRun)
    {
        if ($apifyRun->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($apifyRun);
    }
}
