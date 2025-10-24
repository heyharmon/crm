<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateOrganizationWebsiteRatingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'organizations:update-website-rating {csv_path : Absolute or relative path to the CSV file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update organization website ratings using an external CSV file.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->error('This command is deprecated because website ratings are now tracked per-user. Please migrate any scripts to the new rating service.');

        return self::FAILURE;
    }
}
