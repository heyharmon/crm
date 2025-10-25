<?php

namespace App\Http\Controllers;

use App\Jobs\CrawlSitemapJob;
use Illuminate\Http\Request;

class WebsitePageScraperController extends Controller
{
    public function startScraping(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
        ]);

        CrawlSitemapJob::dispatch($validated['organization_id']);

        return response()->json([
            'message' => 'Sitemap crawl started successfully. You will be notified when it completes.',
        ]);
    }

}
