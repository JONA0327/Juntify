<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrganizationActivity;

class ClearOrganizationActivities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activities:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove organization activities created before the current month';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        OrganizationActivity::where('created_at', '<', now()->startOfMonth())->delete();

        $this->info('Old organization activities cleared.');

        return self::SUCCESS;
    }
}
