<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\CheckExpiredPlansJob;

class CheckExpiredPlans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plans:check-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for expired user subscription plans and update roles to free';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expired plans...');

        // Despachar el job
        CheckExpiredPlansJob::dispatch();

        $this->info('Expired plans check job dispatched successfully.');

        return Command::SUCCESS;
    }
}
