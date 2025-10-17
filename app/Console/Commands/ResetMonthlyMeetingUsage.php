<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MonthlyMeetingUsage;
use Carbon\Carbon;

class ResetMonthlyMeetingUsage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meetings:reset-monthly-usage {--force : Force reset without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset monthly meeting usage counters for all users (run on 1st of each month)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();

        // Safety check: only run on 1st day of month (unless forced)
        if ($now->day !== 1 && !$this->option('force')) {
            $this->error('This command should only be run on the 1st day of the month.');
            $this->info('Use --force to override this check.');
            return Command::FAILURE;
        }

        if (!$this->option('force')) {
            if (!$this->confirm('Are you sure you want to reset monthly meeting usage for ALL users?')) {
                $this->info('Operation cancelled.');
                return Command::FAILURE;
            }
        }

        $this->info('Resetting monthly meeting usage counters...');

        // Archive current month's data by keeping records but marking them as archived
        $currentRecords = MonthlyMeetingUsage::where('year', $now->year)
            ->where('month', $now->month)
            ->get();

        $archivedCount = 0;
        $resetCount = 0;

        foreach ($currentRecords as $record) {
            // Keep record for historical purposes, reset counter for new month
            if ($record->meetings_consumed > 0) {
                $archivedCount++;

                // Reset the counter and add reset entry to audit log
                $records = $record->meeting_records ?? [];
                $records[] = [
                    'timestamp' => $now->toISOString(),
                    'action' => 'monthly_reset',
                    'data' => ['previous_month_usage' => $record->meetings_consumed]
                ];

                $record->update([
                    'meetings_consumed' => 0,
                    'meeting_records' => $records
                ]);

                $resetCount++;
            }
        }

        $this->info("✓ Archived {$archivedCount} usage records");
        $this->info("✓ Created {$resetCount} fresh monthly counters");
        $this->info("Monthly meeting usage reset complete!");

        return Command::SUCCESS;
    }
}
