<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationWebsiteRating;
use App\Models\Page;
use App\Models\WebsiteRatingOption;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $userId = Auth::id();
        $now = Carbon::now();

        $totalOrganizations = Organization::count();
        $organizationsWithoutRatings = Organization::where(function ($query) {
            $query->whereNull('website_rating_count')
                ->orWhere('website_rating_count', 0);
        })->count();

        $totalPages = Page::count();
        $averagePagesPerOrganization = $totalOrganizations > 0 ? round($totalPages / $totalOrganizations, 1) : 0;

        $userRatedWebsites = $userId ? OrganizationWebsiteRating::where('user_id', $userId)->count() : 0;

        $ratingOptions = WebsiteRatingOption::query()
            ->withCount('ratings')
            ->orderByDesc('score')
            ->get(['id', 'name', 'slug', 'score']);
        $totalRatings = $ratingOptions->sum('ratings_count');
        $ratingDistribution = $ratingOptions->map(function ($option) use ($totalRatings) {
            $count = $option->ratings_count;

            return [
                'id' => $option->id,
                'name' => $option->name,
                'slug' => $option->slug,
                'score' => $option->score,
                'count' => $count,
                'percentage' => $totalRatings > 0 ? round(($count / $totalRatings) * 100, 1) : 0,
            ];
        });

        $redesignDates = Organization::whereNotNull('last_major_redesign_at')
            ->pluck('last_major_redesign_at')
            ->filter()
            ->map(fn($date) => Carbon::parse($date));
        $daysSinceRedesign = $redesignDates
            ->map(fn(Carbon $date) => $now->diffInDays($date))
            ->sort()
            ->values();
        $medianDays = $this->calculateMedian($daysSinceRedesign);

        $redesignCounts = [];
        foreach (range(1, 5) as $years) {
            $threshold = $now->copy()->subYears($years);
            $redesignCounts["within_{$years}_years"] = $redesignDates
                ->filter(fn(Carbon $date) => $date->greaterThanOrEqualTo($threshold))
                ->count();
        }

        $cmsDistribution = Organization::whereNotNull('cms')
            ->where('cms', '!=', '')
            ->selectRaw('cms, COUNT(*) as count')
            ->groupBy('cms')
            ->orderByDesc('count')
            ->get();
        $totalWithCms = $cmsDistribution->sum('count');
        $cmsStats = $cmsDistribution->map(function ($item) use ($totalWithCms) {
            return [
                'name' => $item->cms,
                'count' => $item->count,
                'percentage' => $totalWithCms > 0 ? round(($item->count / $totalWithCms) * 100, 1) : 0,
            ];
        });

        return response()->json([
            'totals' => [
                'organizations' => $totalOrganizations,
                'organizations_without_ratings' => $organizationsWithoutRatings,
                'pages_tracked' => $totalPages,
                'average_pages_per_organization' => $averagePagesPerOrganization,
            ],
            'ratings' => [
                'total_ratings' => $totalRatings,
                'distribution' => $ratingDistribution,
                'user_rated_websites' => $userRatedWebsites,
            ],
            'redesigns' => [
                'median_days_since_last_redesign' => $medianDays,
                'median_duration_human' => $medianDays !== null
                    ? $now->copy()->subDays((int) round($medianDays))->diffForHumans($now, [
                        'parts' => 2,
                        'short' => false,
                        'syntax' => Carbon::DIFF_ABSOLUTE,
                    ])
                    : null,
                'counts_by_years' => $redesignCounts,
            ],
            'cms' => [
                'total_with_cms' => $totalWithCms,
                'distribution' => $cmsStats,
            ],
        ]);
    }

    private function calculateMedian($values): ?float
    {
        $count = $values->count();
        if ($count === 0) {
            return null;
        }

        if ($count % 2 === 1) {
            return (float) $values->get(intdiv($count, 2));
        }

        $lower = $values->get(($count / 2) - 1);
        $upper = $values->get($count / 2);

        return ($lower + $upper) / 2;
    }
}
