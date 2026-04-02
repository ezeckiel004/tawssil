<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Livreur;
use App\Models\Gestionnaire;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LivreurController extends Controller
{
    /**
     * Middleware pour vérifier la wilaya du gestionnaire
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = Auth::user();
            $gestionnaire = $user->gestionnaire;

            if (!$gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil gestionnaire introuvable'
                ], 403);
            }

            // Injecter la wilaya et le gestionnaire dans la requête
            $request->merge([
                'gestionnaire_wilaya' => $gestionnaire->wilaya_id,
                'gestionnaire' => $gestionnaire
            ]);

            return $next($request);
        });
    }

    /**
     * Table de correspondance code wilaya -> nom
     */
    private function getWilayaNameFromCode($code): ?string
    {
        $wilayas = [
            '01' => 'Adrar', '02' => 'Chlef', '03' => 'Laghouat', '04' => 'Oum El Bouaghi',
            '05' => 'Batna', '06' => 'Béjaïa', '07' => 'Biskra', '08' => 'Béchar',
            '09' => 'Blida', '10' => 'Bouira', '11' => 'Tamanrasset', '12' => 'Tébessa',
            '13' => 'Tlemcen', '14' => 'Tiaret', '15' => 'Tizi Ouzou', '16' => 'Alger',
            '17' => 'Djelfa', '18' => 'Jijel', '19' => 'Sétif', '20' => 'Saïda',
            '21' => 'Skikda', '22' => 'Sidi Bel Abbès', '23' => 'Annaba', '24' => 'Guelma',
            '25' => 'Constantine', '26' => 'Médéa', '27' => 'Mostaganem', '28' => 'M\'Sila',
            '29' => 'Mascara', '30' => 'Ouargla', '31' => 'Oran', '32' => 'El Bayadh',
            '33' => 'Illizi', '34' => 'Bordj Bou Arréridj', '35' => 'Boumerdès',
            '36' => 'El Tarf', '37' => 'Tindouf', '38' => 'Tissemsilt', '39' => 'El Oued',
            '40' => 'Khenchela', '41' => 'Souk Ahras', '42' => 'Tipaza', '43' => 'Mila',
            '44' => 'Aïn Defla', '45' => 'Naâma', '46' => 'Aïn Témouchent', '47' => 'Ghardaïa',
            '48' => 'Relizane', '49' => 'Timimoun', '50' => 'Bordj Badji Mokhtar',
            '51' => 'Ouled Djellal', '52' => 'Béni Abbès', '53' => 'In Salah',
            '54' => 'In Guezzam', '55' => 'Touggourt', '56' => 'Djanet',
            '57' => 'El M\'Ghair', '58' => 'El Meniaa'
        ];

        return $wilayas[$code] ?? $code;
    }

    /**
     * Lister les livreurs de la wilaya (natifs + assignés)
     */
    public function index(Request $request): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $wilayaNom = $this->getWilayaNameFromCode($wilayaCode);
        $gestionnaire = $request->get('gestionnaire');

        try {
            // 1. Livreurs natifs de la wilaya
            $livreursNatifs = Livreur::with(['user', 'demandeAdhesion'])
                ->where('wilaya_id', $wilayaCode)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($livreur) {
                    $livreur->origine = 'natif';
                    $livreur->assignation = null;
                    return $livreur;
                });

            // 2. Livreurs assignés à ce gestionnaire (invités)
            $livreursAssignes = collect();

            if ($gestionnaire && method_exists($gestionnaire, 'livreursAssignes')) {
                try {
                    $livreursAssignes = $gestionnaire->livreursAssignes()
                        ->with(['user', 'demandeAdhesion'])
                        ->orderBy('livreur_assignations.created_at', 'desc')
                        ->get()
                        ->map(function($livreur) use ($gestionnaire) {
                            $livreur->origine = 'assigne';

                            // Récupérer les détails de l'assignation
                            $assignation = $gestionnaire->livreurAssignations()
                                ->where('livreur_id', $livreur->id)
                                ->where('status', 'active')
                                ->first();

                            $livreur->assignation = $assignation ? [
                                'id' => $assignation->id,
                                'date_debut' => $assignation->date_debut,
                                'date_fin' => $assignation->date_fin,
                                'motif' => $assignation->motif,
                                'wilaya_cible' => $assignation->wilaya_cible
                            ] : null;

                            return $livreur;
                        });
                } catch (\Exception $e) {
                    Log::warning('Erreur récupération livreurs assignés: ' . $e->getMessage());
                }
            }

            // 3. Fusionner et supprimer les doublons
            $allLivreurs = $livreursNatifs->merge($livreursAssignes)->unique('id');

            // 4. Statistiques
            $totalLivreurs = $allLivreurs->count();
            $actifs = $allLivreurs->where('desactiver', false)->count();
            $inactifs = $allLivreurs->where('desactiver', true)->count();
            $distributeurs = $allLivreurs->where('type', 'distributeur')->count();
            $ramasseurs = $allLivreurs->where('type', 'ramasseur')->count();
            $natifs = $livreursNatifs->count();
            $assignes = $livreursAssignes->count();

            return response()->json([
                'success' => true,
                'data' => $allLivreurs->values(),
                'stats' => [
                    'total' => $totalLivreurs,
                    'actifs' => $actifs,
                    'inactifs' => $inactifs,
                    'distributeurs' => $distributeurs,
                    'ramasseurs' => $ramasseurs,
                    'natifs' => $natifs,
                    'assignes' => $assignes,
                    'wilaya' => [
                        'code' => $wilayaCode,
                        'nom' => $wilayaNom
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur index livreurs: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livreurs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Voir un livreur spécifique
     */
    public function show(Request $request, $id): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $gestionnaire = $request->get('gestionnaire');

        try {
            $livreur = null;
            $origine = null;
            $assignation = null;

            // 1. Chercher dans les natifs
            $livreur = Livreur::with(['user', 'demandeAdhesion'])
                ->where('wilaya_id', $wilayaCode)
                ->find($id);

            if ($livreur) {
                $origine = 'natif';
            }

            // 2. Si pas trouvé, chercher dans les assignés
            if (!$livreur && $gestionnaire && method_exists($gestionnaire, 'livreursAssignes')) {
                try {
                    $livreur = $gestionnaire->livreursAssignes()
                        ->with(['user', 'demandeAdhesion'])
                        ->find($id);

                    if ($livreur) {
                        $origine = 'assigne';

                        // Récupérer les détails de l'assignation
                        $assignationData = $gestionnaire->livreurAssignations()
                            ->where('livreur_id', $livreur->id)
                            ->where('status', 'active')
                            ->first();

                        if ($assignationData) {
                            $assignation = [
                                'id' => $assignationData->id,
                                'date_debut' => $assignationData->date_debut,
                                'date_fin' => $assignationData->date_fin,
                                'motif' => $assignationData->motif,
                                'wilaya_cible' => $assignationData->wilaya_cible
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Erreur recherche livreur assigné: ' . $e->getMessage());
                }
            }

            if (!$livreur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Livreur introuvable dans votre wilaya'
                ], 404);
            }

            // Ajouter l'origine et l'assignation
            $livreur->origine = $origine;
            $livreur->assignation = $assignation;

            // Statistiques du livreur
            $stats = [
                'livraisons_total' => $livreur->livraisonsDistribution()->count() +
                                      $livreur->livraisonsRamassage()->count(),
                'livraisons_en_cours' => $livreur->livraisonsDistribution()
                                        ->whereNotIn('status', ['livre', 'annule'])
                                        ->count() +
                                        $livreur->livraisonsRamassage()
                                        ->whereNotIn('status', ['livre', 'annule'])
                                        ->count(),
                'livraisons_terminees' => $livreur->livraisonsDistribution()
                                         ->where('status', 'livre')
                                         ->count() +
                                         $livreur->livraisonsRamassage()
                                         ->where('status', 'livre')
                                         ->count(),
                'taux_reussite' => $this->calculateTauxReussite($livreur),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'livreur' => $livreur,
                    'stats' => $stats,
                    'assignation' => $assignation
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur show livreur: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du livreur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activer/désactiver un livreur
     */
    public function toggleActivation(Request $request, $id): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $gestionnaire = $request->get('gestionnaire');

        $validator = Validator::make($request->all(), [
            'desactiver' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $livreur = null;

            // 1. Chercher dans les natifs
            $livreur = Livreur::where('wilaya_id', $wilayaCode)->find($id);

            // 2. Si pas trouvé, chercher dans les assignés
            if (!$livreur && $gestionnaire && method_exists($gestionnaire, 'livreursAssignes')) {
                try {
                    $livreur = $gestionnaire->livreursAssignes()->find($id);
                } catch (\Exception $e) {
                    Log::warning('Erreur recherche livreur assigné pour toggle: ' . $e->getMessage());
                }
            }

            if (!$livreur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Livreur introuvable dans votre wilaya'
                ], 404);
            }

            // Si 'desactiver' est fourni dans la requête, l'utiliser, sinon faire un toggle
            if ($request->has('desactiver')) {
                $livreur->update([
                    'desactiver' => (bool) $request->desactiver
                ]);
                $message = $request->desactiver ? 'Livreur désactivé' : 'Livreur activé';
            } else {
                $livreur->update([
                    'desactiver' => !$livreur->desactiver
                ]);
                $message = $livreur->desactiver ? 'Livreur désactivé' : 'Livreur activé';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $livreur
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur toggle activation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechercher des livreurs (natifs + assignés)
     */
    public function search(Request $request): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $gestionnaire = $request->get('gestionnaire');
        $term = $request->query('q', '');

        try {
            // 1. Recherche dans les natifs
            $livreursNatifs = Livreur::with(['user', 'demandeAdhesion'])
                ->where('wilaya_id', $wilayaCode)
                ->whereHas('user', function ($query) use ($term) {
                    $query->where('nom', 'like', "%{$term}%")
                          ->orWhere('prenom', 'like', "%{$term}%")
                          ->orWhere('email', 'like', "%{$term}%")
                          ->orWhere('telephone', 'like', "%{$term}%");
                })
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($livreur) {
                    $livreur->origine = 'natif';
                    return $livreur;
                });

            // 2. Recherche dans les assignés
            $livreursAssignes = collect();

            if ($gestionnaire && method_exists($gestionnaire, 'livreursAssignes')) {
                try {
                    $livreursAssignes = $gestionnaire->livreursAssignes()
                        ->with(['user', 'demandeAdhesion'])
                        ->whereHas('user', function ($query) use ($term) {
                            $query->where('nom', 'like', "%{$term}%")
                                  ->orWhere('prenom', 'like', "%{$term}%")
                                  ->orWhere('email', 'like', "%{$term}%")
                                  ->orWhere('telephone', 'like', "%{$term}%");
                        })
                        ->orderBy('livreur_assignations.created_at', 'desc')
                        ->get()
                        ->map(function($livreur) {
                            $livreur->origine = 'assigne';
                            return $livreur;
                        });
                } catch (\Exception $e) {
                    Log::warning('Erreur recherche livreurs assignés: ' . $e->getMessage());
                }
            }

            // Fusionner les résultats
            $allLivreurs = $livreursNatifs->merge($livreursAssignes)->unique('id');

            return response()->json([
                'success' => true,
                'data' => $allLivreurs->values(),
                'count' => $allLivreurs->count(),
                'stats' => [
                    'natifs' => $livreursNatifs->count(),
                    'assignes' => $livreursAssignes->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur recherche livreurs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les livreurs par type (natifs + assignés)
     */
    public function byType(Request $request, $type): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $gestionnaire = $request->get('gestionnaire');

        if (!in_array($type, ['distributeur', 'ramasseur'])) {
            return response()->json([
                'success' => false,
                'message' => 'Type invalide. Utilisez "distributeur" ou "ramasseur"'
            ], 422);
        }

        try {
            // 1. Natifs par type
            $livreursNatifs = Livreur::with(['user', 'demandeAdhesion'])
                ->where('wilaya_id', $wilayaCode)
                ->where('type', $type)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($livreur) {
                    $livreur->origine = 'natif';
                    return $livreur;
                });

            // 2. Assignés par type
            $livreursAssignes = collect();

            if ($gestionnaire && method_exists($gestionnaire, 'livreursAssignes')) {
                try {
                    $livreursAssignes = $gestionnaire->livreursAssignes()
                        ->with(['user', 'demandeAdhesion'])
                        ->where('type', $type)
                        ->orderBy('livreur_assignations.created_at', 'desc')
                        ->get()
                        ->map(function($livreur) {
                            $livreur->origine = 'assigne';
                            return $livreur;
                        });
                } catch (\Exception $e) {
                    Log::warning('Erreur récupération livreurs assignés par type: ' . $e->getMessage());
                }
            }

            // Fusionner les résultats
            $allLivreurs = $livreursNatifs->merge($livreursAssignes)->unique('id');

            return response()->json([
                'success' => true,
                'data' => $allLivreurs->values(),
                'count' => $allLivreurs->count(),
                'stats' => [
                    'natifs' => $livreursNatifs->count(),
                    'assignes' => $livreursAssignes->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur récupération livreurs par type: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livreurs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les livreurs natifs uniquement
     */
    public function getNatifs(Request $request): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $wilayaNom = $this->getWilayaNameFromCode($wilayaCode);

        try {
            $livreurs = Livreur::with(['user', 'demandeAdhesion'])
                ->where('wilaya_id', $wilayaCode)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($livreur) {
                    $livreur->origine = 'natif';
                    return $livreur;
                });

            return response()->json([
                'success' => true,
                'data' => $livreurs,
                'count' => $livreurs->count(),
                'wilaya' => [
                    'code' => $wilayaCode,
                    'nom' => $wilayaNom
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur getNatifs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livreurs natifs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les livreurs assignés uniquement
     */
    public function getAssignes(Request $request): JsonResponse
    {
        $gestionnaire = $request->get('gestionnaire');
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $wilayaNom = $this->getWilayaNameFromCode($wilayaCode);

        if (!$gestionnaire || !method_exists($gestionnaire, 'livreursAssignes')) {
            return response()->json([
                'success' => true,
                'data' => [],
                'count' => 0,
                'message' => 'Aucun livreur assigné'
            ], 200);
        }

        try {
            $livreurs = $gestionnaire->livreursAssignes()
                ->with(['user', 'demandeAdhesion'])
                ->orderBy('livreur_assignations.created_at', 'desc')
                ->get()
                ->map(function($livreur) use ($gestionnaire) {
                    $livreur->origine = 'assigne';

                    $assignation = $gestionnaire->livreurAssignations()
                        ->where('livreur_id', $livreur->id)
                        ->where('status', 'active')
                        ->first();

                    $livreur->assignation = $assignation ? [
                        'id' => $assignation->id,
                        'date_debut' => $assignation->date_debut,
                        'date_fin' => $assignation->date_fin,
                        'motif' => $assignation->motif
                    ] : null;

                    return $livreur;
                });

            return response()->json([
                'success' => true,
                'data' => $livreurs,
                'count' => $livreurs->count(),
                'wilaya' => [
                    'code' => $wilayaCode,
                    'nom' => $wilayaNom
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur getAssignes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livreurs assignés',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculer le taux de réussite d'un livreur
     */
    private function calculateTauxReussite(Livreur $livreur): float
    {
        $total = $livreur->livraisonsDistribution()->count() +
                 $livreur->livraisonsRamassage()->count();

        if ($total === 0) {
            return 0;
        }

        $terminees = $livreur->livraisonsDistribution()
                      ->where('status', 'livre')
                      ->count() +
                      $livreur->livraisonsRamassage()
                      ->where('status', 'livre')
                      ->count();

        return round(($terminees / $total) * 100, 2);
    }

    // ⚠️ PAS de méthode destroy() - Le gestionnaire n'a pas le droit de suppression
}
