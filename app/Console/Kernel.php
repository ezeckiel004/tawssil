<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        // Calcul des gains tous les 1er du mois à 02:00
        $schedule->command('gains:calculer-mensuels')
            ->monthlyOn(1, '02:00')
            ->withoutOverlapping()
            ->sendOutputTo(storage_path('logs/gains.log'));

        // Nettoyage des navettes tous les dimanches à 03:00
        $schedule->command('navettes:nettoyer --jours=90')
            ->weeklyOn(7, '03:00')
            ->withoutOverlapping()
            ->sendOutputTo(storage_path('logs/navettes.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
