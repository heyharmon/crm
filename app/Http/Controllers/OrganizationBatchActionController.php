<?php

namespace App\Http\Controllers;

use App\Jobs\CheckOrganizationWebsiteStatus;
use App\Jobs\CrawlSitemapJob;
use App\Jobs\DetectWebsiteRedesignJob;
use App\Jobs\DetectOrganizationCmsJob;
use App\Models\Organization;
use Illuminate\Http\Request;

class OrganizationBatchActionController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|string|in:count_pages,detect_redesign,detect_cms,check_website_status,archive',
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

            $requiresWebsite = in_array($validated['action'], [
                'count_pages',
                'detect_redesign',
                'detect_cms',
                'check_website_status',
            ]);

            if ($requiresWebsite && !$organization->website) {
                $skipped[] = ['id' => $organization->id, 'reason' => 'missing_website'];
                continue;
            }

            match ($validated['action']) {
                'count_pages' => CrawlSitemapJob::dispatch($organization->id),
                'detect_redesign' => DetectWebsiteRedesignJob::dispatch($organization->id),
                'detect_cms' => DetectOrganizationCmsJob::dispatch($organization->id),
                'check_website_status' => CheckOrganizationWebsiteStatus::dispatch($organization->id),
                default => null,
            };

            $queued[] = $organization->id;
        }

        $messages = [
            'count_pages' => 'Page counting jobs queued.',
            'detect_redesign' => 'Website redesign detection queued.',
            'detect_cms' => 'CMS detection queued.',
            'check_website_status' => 'Website status checks queued.',
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
