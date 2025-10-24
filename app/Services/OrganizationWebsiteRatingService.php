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

        $organization->forceFill([
            'website_rating_average' => $average,
            'website_rating_count' => $count,
            'website_rating_summary' => $summary,
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
        $option = WebsiteRatingOption::orderByRaw('ABS(score - ?) asc', [$average])
            ->orderByDesc('score')
            ->first();

        return $option?->slug;
    }
}

