<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\OrganizationWebsiteRatingService;
use Illuminate\Console\Command;

class RecalculateOrganizationRatings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ratings:recalculate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate website rating aggregates for all organizations';

    /**
     * Execute the console command.
     */
    public function handle(OrganizationWebsiteRatingService $ratingService)
    {
        $this->info('Recalculating website ratings for all organizations...');

        $organizationIds = Organization::pluck('id');
        $total = $organizationIds->count();

        if ($total === 0) {
            $this->warn('No organizations found.');
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $organizationIds->chunk(100)->each(function ($chunk) use ($ratingService, $bar) {
            $ratingService->refreshAggregatesForOrganizations($chunk);
            $bar->advance($chunk->count());
        });

        $bar->finish();
        $this->newLine();
        $this->info("Successfully recalculated ratings for {$total} organizations.");

        return Command::SUCCESS;
    }
}
