<?php
// app/Http/Controllers/Admin/ComptabiliteController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Colis;
use App\Models\Livraison;
use App\Models\Navette;
use App\Models\DemandeLivraison;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Exports\BilanGlobalExport;
use App\Exports\BilanGestionnaireExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class ComptabiliteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Bilan GLOBAL - Toute la plateforme
     */
    public function bilanGlobal(Request $request): JsonResponse
    {
        try {
            $periode = $request->get('periode', 'annee');

            // Récupérer les dates selon la période
            $dates = $this->getPeriodDates($periode, $request);
            $dateDebut = $dates[0];
            $dateFin = $dates[1];

            $stats = [
                'periode' => [
                    'debut' => $dateDebut ? $dateDebut->format('Y-m-d') : 'Toutes',
                    'fin' => $dateFin ? $dateFin->format('Y-m-d') : 'Toutes',
                    'libelle' => $this->getPeriodLibelle($periode, $dateDebut, $dateFin)
                ],
                'colis' => $this->getStatsColis(null, $dateDebut, $dateFin),
                'livraisons' => $this->getStatsLivraisons(null, $dateDebut, $dateFin),
                'navettes' => $this->getStatsNavettes(null, $dateDebut, $dateFin),
                'finances' => $this->getBilanFinancier(null, $dateDebut, $dateFin),
                'top_wilayas' => $this->getTopWilayas($dateDebut, $dateFin),
                'evolution' => $this->getEvolutionGlobale($dateDebut, $dateFin)
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur bilanGlobal: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul du bilan global: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bilan pour une wilaya spécifique
     */
    public function bilanGestionnaire(Request $request, $wilayaId = null): JsonResponse
    {
        try {
            $user = $request->user();

            // Déterminer la wilaya à afficher
            if ($user->role === 'admin' && $wilayaId) {
                $wilaya = $wilayaId;
            } else {
                $gestionnaire = $user->gestionnaire;
                if (!$gestionnaire) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Profil gestionnaire introuvable'
                    ], 403);
                }
                $wilaya = $gestionnaire->wilaya_id;
            }

            $periode = $request->get('periode', 'annee');

            // Récupérer les dates selon la période
            $dates = $this->getPeriodDates($periode, $request);
            $dateDebut = $dates[0];
            $dateFin = $dates[1];

            $stats = [
                'gestionnaire' => [
                    'wilaya_id' => $wilaya,
                    'wilaya_nom' => $this->getWilayaName($wilaya)
                ],
                'periode' => [
                    'debut' => $dateDebut ? $dateDebut->format('Y-m-d') : 'Toutes',
                    'fin' => $dateFin ? $dateFin->format('Y-m-d') : 'Toutes',
                    'libelle' => $this->getPeriodLibelle($periode, $dateDebut, $dateFin)
                ],
                'colis' => $this->getStatsColis($wilaya, $dateDebut, $dateFin),
                'livraisons' => $this->getStatsLivraisons($wilaya, $dateDebut, $dateFin),
                'navettes' => $this->getStatsNavettes($wilaya, $dateDebut, $dateFin),
                'finances' => $this->getBilanFinancier($wilaya, $dateDebut, $dateFin),
                'evolution' => $this->getEvolutionWilaya($wilaya, $dateDebut, $dateFin)
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur bilanGestionnaire: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul du bilan'
            ], 500);
        }
    }

    // ==================== MÉTHODES STATISTIQUES ====================

    /**
     * Statistiques des colis
     */
    private function getStatsColis($wilayaId = null, $dateDebut = null, $dateFin = null): array
    {
        $query = Colis::query();

        if ($dateDebut && $dateFin) {
            $query->whereBetween('created_at', [$dateDebut, $dateFin]);
        }

        if ($wilayaId) {
            $query->whereHas('demandeLivraisons', function ($q) use ($wilayaId) {
                $q->where('wilaya_depot', $wilayaId);
            });
        }

        $colis = $query->get();

        return [
            'total' => $colis->count(),
            'valeur_totale' => $colis->sum('colis_prix') ?? 0,
            'valeur_moyenne' => $colis->avg('colis_prix') ?? 0,
            'poids_total' => $colis->sum('poids') ?? 0,
            'poids_moyen' => $colis->avg('poids') ?? 0,
            'avec_prix' => $colis->whereNotNull('colis_prix')->count(),
            'sans_prix' => $colis->whereNull('colis_prix')->count()
        ];
    }

    /**
     * Statistiques des livraisons
     */
    private function getStatsLivraisons($wilayaId = null, $dateDebut = null, $dateFin = null): array
    {
        $query = Livraison::with('demandeLivraison');

        if ($dateDebut && $dateFin) {
            $query->whereBetween('created_at', [$dateDebut, $dateFin]);
        }

        if ($wilayaId) {
            $query->whereHas('demandeLivraison', function ($q) use ($wilayaId) {
                $q->where(function ($sub) use ($wilayaId) {
                    $sub->where('wilaya', $wilayaId)
                        ->orWhere('wilaya_depot', $wilayaId);
                });
            });
        }

        $livraisons = $query->get();

        // Récupérer les prix depuis demandeLivraison
        $prixTotal = 0;
        foreach ($livraisons as $livraison) {
            if ($livraison->demandeLivraison) {
                $prixTotal += $livraison->demandeLivraison->prix ?? 0;
            }
        }

        return [
            'total' => $livraisons->count(),
            'terminees' => $livraisons->where('status', 'livre')->count(),
            'en_cours' => $livraisons->whereNotIn('status', ['livre', 'annule'])->count(),
            'en_attente' => $livraisons->where('status', 'en_attente')->count(),
            'annulees' => $livraisons->where('status', 'annule')->count(),
            'prix_total' => $prixTotal,
            'prix_moyen' => $livraisons->count() > 0 ? $prixTotal / $livraisons->count() : 0,
            'par_statut' => [
                'en_attente' => $livraisons->where('status', 'en_attente')->count(),
                'prise_en_charge_ramassage' => $livraisons->where('status', 'prise_en_charge_ramassage')->count(),
                'ramasse' => $livraisons->where('status', 'ramasse')->count(),
                'en_transit' => $livraisons->where('status', 'en_transit')->count(),
                'prise_en_charge_livraison' => $livraisons->where('status', 'prise_en_charge_livraison')->count(),
                'livre' => $livraisons->where('status', 'livre')->count(),
                'annule' => $livraisons->where('status', 'annule')->count()
            ]
        ];
    }

    /**
     * Statistiques des navettes
     */
    private function getStatsNavettes($wilayaId = null, $dateDebut = null, $dateFin = null): array
    {
        $query = Navette::query();

        if ($dateDebut && $dateFin) {
            $query->whereBetween('created_at', [$dateDebut, $dateFin]);
        }

        if ($wilayaId) {
            $query->where(function ($q) use ($wilayaId) {
                $q->where('wilaya_depart_id', $wilayaId)
                    ->orWhere('wilaya_arrivee_id', $wilayaId)
                    ->orWhere('wilaya_transit_id', $wilayaId);
            });
        }

        $navettes = $query->get();

        $revenusNavettes = 0;
        $colisTransportes = 0;

        foreach ($navettes as $navette) {
            $revenusNavettes += $navette->prix_base + ($navette->nb_colis * $navette->prix_par_colis);
            $colisTransportes += $navette->nb_colis;
        }

        return [
            'total' => $navettes->count(),
            'planifiees' => $navettes->where('status', 'planifiee')->count(),
            'en_cours' => $navettes->where('status', 'en_cours')->count(),
            'terminees' => $navettes->where('status', 'terminee')->count(),
            'annulees' => $navettes->where('status', 'annulee')->count(),
            'revenus' => $revenusNavettes,
            'colis_transportes' => $colisTransportes,
            'distance_totale' => $navettes->sum('distance_km') ?? 0,
            'distance_moyenne' => $navettes->avg('distance_km') ?? 0,
            'taux_remplissage_moyen' => $navettes->avg('taux_remplissage') ?? 0
        ];
    }

    /**
     * Bilan financier
     */
    private function getBilanFinancier($wilayaId = null, $dateDebut = null, $dateFin = null): array
    {
        // Valeur des colis
        $colisQuery = Colis::query();
        if ($dateDebut && $dateFin) {
            $colisQuery->whereBetween('created_at', [$dateDebut, $dateFin]);
        }
        if ($wilayaId) {
            $colisQuery->whereHas('demandeLivraisons', function ($q) use ($wilayaId) {
                $q->where('wilaya_depot', $wilayaId);
            });
        }
        $valeurColis = $colisQuery->sum('colis_prix') ?? 0;

        // Revenus livraisons
        $livraisonsQuery = Livraison::where('status', 'livre')->with('demandeLivraison');
        if ($dateDebut && $dateFin) {
            $livraisonsQuery->whereBetween('created_at', [$dateDebut, $dateFin]);
        }
        if ($wilayaId) {
            $livraisonsQuery->whereHas('demandeLivraison', function ($q) use ($wilayaId) {
                $q->where(function ($sub) use ($wilayaId) {
                    $sub->where('wilaya', $wilayaId)
                        ->orWhere('wilaya_depot', $wilayaId);
                });
            });
        }

        $livraisons = $livraisonsQuery->get();
        $revenusLivraisons = 0;
        foreach ($livraisons as $livraison) {
            if ($livraison->demandeLivraison) {
                $revenusLivraisons += $livraison->demandeLivraison->prix ?? 0;
            }
        }

        // Revenus navettes
        $navettesQuery = Navette::where('status', 'terminee');
        if ($dateDebut && $dateFin) {
            $navettesQuery->whereBetween('created_at', [$dateDebut, $dateFin]);
        }
        if ($wilayaId) {
            $navettesQuery->where(function ($q) use ($wilayaId) {
                $q->where('wilaya_depart_id', $wilayaId)
                    ->orWhere('wilaya_arrivee_id', $wilayaId);
            });
        }

        $revenusNavettes = 0;
        foreach ($navettesQuery->get() as $navette) {
            $revenusNavettes += $navette->prix_base + ($navette->nb_colis * $navette->prix_par_colis);
        }

        $total = $valeurColis + $revenusLivraisons + $revenusNavettes;

        return [
            'valeur_colis' => $valeurColis,
            'revenus_livraisons' => $revenusLivraisons,
            'revenus_navettes' => $revenusNavettes,
            'chiffre_affaires_total' => $total,
            'repartition' => [
                'colis' => [
                    'montant' => $valeurColis,
                    'pourcentage' => $total > 0 ? round(($valeurColis / $total) * 100, 2) : 0
                ],
                'livraisons' => [
                    'montant' => $revenusLivraisons,
                    'pourcentage' => $total > 0 ? round(($revenusLivraisons / $total) * 100, 2) : 0
                ],
                'navettes' => [
                    'montant' => $revenusNavettes,
                    'pourcentage' => $total > 0 ? round(($revenusNavettes / $total) * 100, 2) : 0
                ]
            ]
        ];
    }

    /**
     * Top wilayas (CORRIGÉ pour gérer les valeurs NULL)
     */
    private function getTopWilayas($dateDebut = null, $dateFin = null): array
    {
        $queryDeparts = DemandeLivraison::select('wilaya_depot', DB::raw('count(*) as total'))
            ->whereNotNull('wilaya_depot'); // ← IGNORER les NULL

        $queryArrivees = DemandeLivraison::select('wilaya', DB::raw('count(*) as total'))
            ->whereNotNull('wilaya'); // ← IGNORER les NULL

        if ($dateDebut && $dateFin) {
            $queryDeparts->whereBetween('created_at', [$dateDebut, $dateFin]);
            $queryArrivees->whereBetween('created_at', [$dateDebut, $dateFin]);
        }

        $topDeparts = $queryDeparts
            ->groupBy('wilaya_depot')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'wilaya' => $item->wilaya_depot,
                    'nom' => $this->getWilayaName($item->wilaya_depot),
                    'total' => $item->total
                ];
            });

        $topArrivees = $queryArrivees
            ->groupBy('wilaya')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'wilaya' => $item->wilaya,
                    'nom' => $this->getWilayaName($item->wilaya),
                    'total' => $item->total
                ];
            });

        return [
            'departs' => $topDeparts,
            'arrivees' => $topArrivees
        ];
    }

    /**
     * Évolution globale
     */
    private function getEvolutionGlobale($dateDebut = null, $dateFin = null): array
    {
        if (!$dateDebut || !$dateFin) {
            // Si pas de filtre, comparer avec l'année précédente
            $dateFin = Carbon::now();
            $dateDebut = Carbon::now()->subYear();
            $dateDebutPrec = Carbon::now()->subYears(2);
            $dateFinPrec = Carbon::now()->subYear()->subDay();
        } else {
            $duree = $dateDebut->diffInDays($dateFin);
            $dateDebutPrec = $dateDebut->copy()->subDays($duree + 1);
            $dateFinPrec = $dateDebut->copy()->subDay();
        }

        $periodeActuelle = $this->getBilanFinancier(null, $dateDebut, $dateFin);
        $periodePrecedente = $this->getBilanFinancier(null, $dateDebutPrec, $dateFinPrec);

        $evolutionCA = $periodePrecedente['chiffre_affaires_total'] > 0
            ? round((($periodeActuelle['chiffre_affaires_total'] - $periodePrecedente['chiffre_affaires_total']) / $periodePrecedente['chiffre_affaires_total']) * 100, 2)
            : 0;

        return [
            'chiffre_affaires' => [
                'actuel' => $periodeActuelle['chiffre_affaires_total'],
                'precedent' => $periodePrecedente['chiffre_affaires_total'],
                'evolution' => $evolutionCA,
                'tendance' => $evolutionCA >= 0 ? 'hausse' : 'baisse'
            ]
        ];
    }

    /**
     * Évolution pour une wilaya
     */
    private function getEvolutionWilaya($wilayaId, $dateDebut = null, $dateFin = null): array
    {
        if (!$dateDebut || !$dateFin) {
            // Si pas de filtre, comparer avec l'année précédente
            $dateFin = Carbon::now();
            $dateDebut = Carbon::now()->subYear();
            $dateDebutPrec = Carbon::now()->subYears(2);
            $dateFinPrec = Carbon::now()->subYear()->subDay();
        } else {
            $duree = $dateDebut->diffInDays($dateFin);
            $dateDebutPrec = $dateDebut->copy()->subDays($duree + 1);
            $dateFinPrec = $dateDebut->copy()->subDay();
        }

        $periodeActuelle = $this->getBilanFinancier($wilayaId, $dateDebut, $dateFin);
        $periodePrecedente = $this->getBilanFinancier($wilayaId, $dateDebutPrec, $dateFinPrec);

        $evolutionCA = $periodePrecedente['chiffre_affaires_total'] > 0
            ? round((($periodeActuelle['chiffre_affaires_total'] - $periodePrecedente['chiffre_affaires_total']) / $periodePrecedente['chiffre_affaires_total']) * 100, 2)
            : 0;

        return [
            'chiffre_affaires' => [
                'actuel' => $periodeActuelle['chiffre_affaires_total'],
                'precedent' => $periodePrecedente['chiffre_affaires_total'],
                'evolution' => $evolutionCA,
                'tendance' => $evolutionCA >= 0 ? 'hausse' : 'baisse'
            ]
        ];
    }

    // ==================== UTILITAIRES ====================

    private function getPeriodDates($periode, $request): array
    {
        switch ($periode) {
            case 'jour':
                $date = $request->date ? Carbon::parse($request->date) : Carbon::today();
                return [$date->copy()->startOfDay(), $date->copy()->endOfDay()];
            case 'semaine':
                $date = $request->date ? Carbon::parse($request->date) : Carbon::today();
                return [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()];
            case 'mois':
                $date = $request->date ? Carbon::parse($request->date) : Carbon::today();
                return [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()];
            case 'annee':
                $date = $request->date ? Carbon::parse($request->date) : Carbon::today();
                return [$date->copy()->startOfYear(), $date->copy()->endOfYear()];
            case 'personnalise':
                return [
                    Carbon::parse($request->date_debut)->startOfDay(),
                    Carbon::parse($request->date_fin)->endOfDay()
                ];
            default:
                // Par défaut : toute l'année en cours
                return [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()];
        }
    }

    private function getPeriodLibelle($periode, $debut, $fin): string
    {
        if (!$debut || !$fin) {
            return 'Période non définie';
        }

        if ($periode === 'personnalise') {
            return 'Du ' . $debut->format('d/m/Y') . ' au ' . $fin->format('d/m/Y');
        }

        $labels = [
            'jour' => 'Journée du ' . $debut->format('d/m/Y'),
            'semaine' => 'Semaine du ' . $debut->format('d/m/Y') . ' au ' . $fin->format('d/m/Y'),
            'mois' => 'Mois de ' . $debut->locale('fr')->isoFormat('MMMM YYYY'),
            'annee' => 'Année ' . $debut->format('Y')
        ];

        return $labels[$periode] ?? 'Période';
    }

    /**
     * CORRIGÉ : Gère les valeurs NULL et retourne une chaîne par défaut
     */
    private function getWilayaName($code): string
    {
        // Si le code est null, retourner "Inconnue"
        if ($code === null) {
            return 'Wilaya inconnue';
        }

        $wilayas = [
            '01' => 'Adrar',
            '02' => 'Chlef',
            '03' => 'Laghouat',
            '04' => 'Oum El Bouaghi',
            '05' => 'Batna',
            '06' => 'Béjaïa',
            '07' => 'Biskra',
            '08' => 'Béchar',
            '09' => 'Blida',
            '10' => 'Bouira',
            '11' => 'Tamanrasset',
            '12' => 'Tébessa',
            '13' => 'Tlemcen',
            '14' => 'Tiaret',
            '15' => 'Tizi Ouzou',
            '16' => 'Alger',
            '17' => 'Djelfa',
            '18' => 'Jijel',
            '19' => 'Sétif',
            '20' => 'Saïda',
            '21' => 'Skikda',
            '22' => 'Sidi Bel Abbès',
            '23' => 'Annaba',
            '24' => 'Guelma',
            '25' => 'Constantine',
            '26' => 'Médéa',
            '27' => 'Mostaganem',
            '28' => 'M\'Sila',
            '29' => 'Mascara',
            '30' => 'Ouargla',
            '31' => 'Oran',
            '32' => 'El Bayadh',
            '33' => 'Illizi',
            '34' => 'Bordj Bou Arréridj',
            '35' => 'Boumerdès',
            '36' => 'El Tarf',
            '37' => 'Tindouf',
            '38' => 'Tissemsilt',
            '39' => 'El Oued',
            '40' => 'Khenchela',
            '41' => 'Souk Ahras',
            '42' => 'Tipaza',
            '43' => 'Mila',
            '44' => 'Aïn Defla',
            '45' => 'Naâma',
            '46' => 'Aïn Témouchent',
            '47' => 'Ghardaïa',
            '48' => 'Relizane',
            '49' => 'Timimoun',
            '50' => 'Bordj Badji Mokhtar',
            '51' => 'Ouled Djellal',
            '52' => 'Béni Abbès',
            '53' => 'In Salah',
            '54' => 'In Guezzam',
            '55' => 'Touggourt',
            '56' => 'Djanet',
            '57' => 'El M\'Ghair',
            '58' => 'El Meniaa'
        ];

        return $wilayas[$code] ?? 'Wilaya ' . $code;
    }




// ==================== MÉTHODES D'EXPORT ====================

    /**
     * Exporter le bilan global (Excel/PDF)
     */
    public function exportBilanGlobal(Request $request)
    {
        try {
            $format = $request->get('format', 'excel');
            $periode = $request->get('periode', 'annee');

            $dates = $this->getPeriodDates($periode, $request);
            $dateDebut = $dates[0];
            $dateFin = $dates[1];

            $data = [
                'periode' => [
                    'debut' => $dateDebut ? $dateDebut->format('Y-m-d') : 'Toutes',
                    'fin' => $dateFin ? $dateFin->format('Y-m-d') : 'Toutes',
                    'libelle' => $this->getPeriodLibelle($periode, $dateDebut, $dateFin)
                ],
                'colis' => $this->getStatsColis(null, $dateDebut, $dateFin),
                'livraisons' => $this->getStatsLivraisons(null, $dateDebut, $dateFin),
                'navettes' => $this->getStatsNavettes(null, $dateDebut, $dateFin),
                'finances' => $this->getBilanFinancier(null, $dateDebut, $dateFin),
                'top_wilayas' => $this->getTopWilayas($dateDebut, $dateFin),
                'date_generation' => Carbon::now()->format('d/m/Y H:i:s')
            ];

            if ($format === 'pdf') {
                $pdf = Pdf::loadView('pdf.bilan-global', ['data' => $data]);
                $pdf->setPaper('A4', 'portrait');
                return $pdf->download('bilan-global-' . Carbon::now()->format('Ymd-His') . '.pdf');
            } else {
                return Excel::download(
                    new BilanGlobalExport($data),
                    'bilan-global-' . Carbon::now()->format('Ymd-His') . '.xlsx'
                );
            }
        } catch (\Exception $e) {
            Log::error('Erreur exportBilanGlobal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exporter le bilan d'un gestionnaire (Excel/PDF)
     */
    public function exportBilanGestionnaire(Request $request, $wilayaId = null)
    {
        try {
            $user = $request->user();

            // Déterminer la wilaya
            if ($user->role === 'admin' && $wilayaId) {
                $wilaya = $wilayaId;
            } else {
                $gestionnaire = $user->gestionnaire;
                if (!$gestionnaire) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Accès non autorisé'
                    ], 403);
                }
                $wilaya = $gestionnaire->wilaya_id;
            }

            $format = $request->get('format', 'excel');
            $periode = $request->get('periode', 'annee');

            $dates = $this->getPeriodDates($periode, $request);
            $dateDebut = $dates[0];
            $dateFin = $dates[1];

            $data = [
                'wilaya_id' => $wilaya,
                'wilaya_nom' => $this->getWilayaName($wilaya),
                'periode' => [
                    'debut' => $dateDebut ? $dateDebut->format('Y-m-d') : 'Toutes',
                    'fin' => $dateFin ? $dateFin->format('Y-m-d') : 'Toutes',
                    'libelle' => $this->getPeriodLibelle($periode, $dateDebut, $dateFin)
                ],
                'colis' => $this->getStatsColis($wilaya, $dateDebut, $dateFin),
                'livraisons' => $this->getStatsLivraisons($wilaya, $dateDebut, $dateFin),
                'navettes' => $this->getStatsNavettes($wilaya, $dateDebut, $dateFin),
                'finances' => $this->getBilanFinancier($wilaya, $dateDebut, $dateFin),
                'date_generation' => Carbon::now()->format('d/m/Y H:i:s')
            ];

            if ($format === 'pdf') {
                $pdf = Pdf::loadView('pdf.bilan-gestionnaire', ['data' => $data]);
                $pdf->setPaper('A4', 'portrait');
                $filename = 'bilan-' . $data['wilaya_nom'] . '-' . Carbon::now()->format('Ymd-His') . '.pdf';
                return $pdf->download($filename);
            } else {
                return Excel::download(
                    new BilanGestionnaireExport($data),
                    'bilan-' . $data['wilaya_nom'] . '-' . Carbon::now()->format('Ymd-His') . '.xlsx'
                );
            }
        } catch (\Exception $e) {
            Log::error('Erreur exportBilanGestionnaire: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export'
            ], 500);
        }
    }
}
