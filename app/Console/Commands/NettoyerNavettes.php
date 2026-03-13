<?php
// app/Console/Commands/NettoyerNavettes.php

namespace App\Console\Commands;

use App\Models\Navette;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NettoyerNavettes extends Command
{
    protected $signature = 'navettes:nettoyer {--jours=30 : Nombre de jours avant suppression}';
    protected $description = 'Nettoie les anciennes navettes';

    public function handle()
    {
        $jours = $this->option('jours');
        $dateLimite = Carbon::now()->subDays($jours);

        $navettes = Navette::where('status', 'terminee')
            ->where('created_at', '<', $dateLimite)
            ->get();

        $count = $navettes->count();

        if ($count === 0) {
            $this->info("Aucune navette à nettoyer");
            return Command::SUCCESS;
        }

        $this->info("Suppression de $count navettes...");

        foreach ($navettes as $navette) {
            $navette->colis()->detach();
            $navette->delete();
        }

        $this->info("Nettoyage terminé");

        return Command::SUCCESS;
    }
}
