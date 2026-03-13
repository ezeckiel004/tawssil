<?php
// app/Console/Commands/CalculerGainsMensuels.php

namespace App\Console\Commands;

use App\Services\GainCalculatorService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CalculerGainsMensuels extends Command
{
    protected $signature = 'gains:calculer-mensuels {--mois= : Mois au format YYYY-MM}';
    protected $description = 'Calcule les gains pour le mois précédent';

    protected $gainCalculator;

    public function __construct(GainCalculatorService $gainCalculator)
    {
        parent::__construct();
        $this->gainCalculator = $gainCalculator;
    }

    public function handle()
    {
        if ($this->option('mois')) {
            $date = Carbon::createFromFormat('Y-m', $this->option('mois'));
        } else {
            $date = Carbon::now()->subMonth();
        }

        $debut = $date->copy()->startOfMonth();
        $fin = $date->copy()->endOfMonth();

        $this->info("Calcul des gains pour {$debut->format('Y-m')}...");

        $resultats = $this->gainCalculator->enregistrerGains($debut, $fin);

        $this->info("Terminé : {$resultats['crees']} gains créés, {$resultats['ignores']} ignorés");

        if (!empty($resultats['erreurs'])) {
            $this->error(count($resultats['erreurs']) . ' erreurs rencontrées');
            foreach ($resultats['erreurs'] as $erreur) {
                $this->line(" - Livraison {$erreur['livraison_id']}: {$erreur['message']}");
            }
        }

        return Command::SUCCESS;
    }
}
