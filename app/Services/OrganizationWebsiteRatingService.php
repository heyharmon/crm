<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationWebsiteRating;
use App\Models\User;
use App\Models\WebsiteRatingOption;
use Illuminate\Support\Collection;

class OrganizationWebsiteRatingService
{
    public function upsertRating(Organization $organization, User $user, WebsiteRatingOption $option): OrganizationWebsiteRating
    {
        $rating = OrganizationWebsiteRating::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ],
            [
                'website_rating_option_id' => $option->id,
                'score' => $option->score,
            ]
        );

        $this->refreshOrganizationAggregates($organization);

        return $rating->fresh(['option']);
    }

    public function deleteRating(Organization $organization, User $user): void
    {
        $rating = OrganizationWebsiteRating::where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $rating) {
            return;
        }

        $rating->delete();

        $this->refreshOrganizationAggregates($organization);
    }

    public function refreshOrganizationAggregates(Organization $organization): void
    {
        $aggregates = $organization->websiteRatings()
            ->selectRaw('AVG(score) as avg_score, COUNT(*) as total')
            ->first();

        $average = $aggregates && $aggregates->total > 0 ? (float) $aggregates->avg_score : null;
        $count = $aggregates ? (int) $aggregates->total : 0;

        $summary = null;
        if ($average !== null && $count > 0) {
            $summary = $this->resolveSummaryForAverage($average);
        }

        $weighted = $this->calculateWeightedAverage($average, $count);

        $organization->forceFill([
            'website_rating_average' => $average,
            'website_rating_count' => $count,
            'website_rating_summary' => $summary,
            'website_rating_weighted' => $weighted,
        ])->save();
    }

    public function refreshAggregatesForOrganizations(iterable $organizationIds): void
    {
        $ids = Collection::make($organizationIds)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        Organization::whereIn('id', $ids)->get()->each(function (Organization $organization) {
            $this->refreshOrganizationAggregates($organization);
        });
    }

    protected function resolveSummaryForAverage(float $average): ?string
    {
        $option = WebsiteRatingOption::orderByRaw('ABS(CAST(score AS SIGNED) - ?) asc', [$average])
            ->orderByDesc('score')
            ->first();

        return $option?->slug;
    }

    protected function calculateWeightedAverage(?float $average, int $count): ?float
    {
        if ($average === null) {
            return null;
        }

        $global = $this->globalRatingStats();
        if (!$global || $global['count'] === 0) {
            return $average;
        }

        $m = config('ratings.minimum_votes', 3);
        $C = $global['average'];
        $v = $count;
        if ($v === 0) {
            return $C;
        }

        return (($v / ($v + $m)) * $average) + (($m / ($v + $m)) * $C);
    }

    protected function globalRatingStats(): ?array
    {
        static $stats = null;
        if ($stats !== null) {
            return $stats;
        }

        $result = OrganizationWebsiteRating::selectRaw('AVG(score) as avg_score, COUNT(*) as total')->first();
        if (!$result || !$result->total) {
            $stats = ['average' => null, 'count' => 0];
            return $stats;
        }

        $stats = [
            'average' => (float) $result->avg_score,
            'count' => (int) $result->total,
        ];

        return $stats;
    }
}
