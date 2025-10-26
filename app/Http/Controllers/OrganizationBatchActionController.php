<?php

namespace App\Http\Controllers;

use App\Jobs\CrawlSitemapJob;
use App\Jobs\DetectWebsiteRedesignJob;
use App\Models\Organization;
use Illuminate\Http\Request;

class OrganizationBatchActionController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|string|in:count_pages,detect_redesign,archive',
            'organization_ids' => 'required|array|min:1',
            'organization_ids.*' => 'integer|exists:organizations,id',
        ]);

        $ids = collect($validated['organization_ids'])
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json([
                'message' => 'No organizations were provided for the batch action.',
                'queued' => 0,
                'queued_ids' => [],
                'skipped' => [],
            ]);
        }

        $organizations = Organization::withTrashed()->whereIn('id', $ids)->get()->keyBy('id');

        $queued = [];
        $skipped = [];

        foreach ($ids as $id) {
            $organization = $organizations->get((int) $id);
            if (!$organization) {
                $skipped[] = ['id' => (int) $id, 'reason' => 'missing'];
                continue;
            }

            if ($organization->trashed()) {
                $skipped[] = ['id' => $organization->id, 'reason' => 'already_archived'];
                continue;
            }

            if ($validated['action'] === 'archive') {
                $organization->delete();
                $queued[] = $organization->id;
                continue;
            }

            if (!$organization->website) {
                $skipped[] = ['id' => $organization->id, 'reason' => 'missing_website'];
                continue;
            }

            if ($validated['action'] === 'count_pages') {
                CrawlSitemapJob::dispatch($organization->id);
            } else {
                DetectWebsiteRedesignJob::dispatch($organization->id);
            }

            $queued[] = $organization->id;
        }

        $messages = [
            'count_pages' => 'Page counting jobs queued.',
            'detect_redesign' => 'Website redesign detection queued.',
            'archive' => 'Organizations archived.',
        ];

        return response()->json([
            'message' => $messages[$validated['action']],
            'queued' => count($queued),
            'queued_ids' => $queued,
            'skipped' => $skipped,
        ]);
    }
}
