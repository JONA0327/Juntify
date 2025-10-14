<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Renovar tokens de Google cada 30 minutos
        $schedule->command('google:refresh-tokens')->everyThirtyMinutes();

        // Verificar planes vencidos cada hora
        $schedule->command('plans:update-expired')->hourly();

        // $schedule->command('inspire')->hourly();
        $schedule->command('activities:cleanup')->cron('0 0 1 * *');
        $schedule->command('plans:expire')->dailyAt('02:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
