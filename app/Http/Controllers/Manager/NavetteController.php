<?php
// app/Http/Controllers/Manager/NavetteController.php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Navette;
use App\Models\Livraison;
use App\Models\Gestionnaire;
use App\Services\OptimisationTrajetService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Events\NavetteTerminee;

class NavetteController extends Controller
{
    protected $optimisationService;

    public function __construct(OptimisationTrajetService $optimisationService)
    {
        $this->optimisationService = $optimisationService;
        $this->middleware('auth:sanctum');
        $this->middleware('gestionnaire'); // Utiliser le middleware 'gestionnaire' qui existe déjà
    }

    /**
     * Lister les navettes de la wilaya du gestionnaire
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $gestionnaire = $user->gestionnaire;

            if (!$gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil gestionnaire introuvable'
                ], 403);
            }

            $wilayaId = $gestionnaire->wilaya_id;

            $query = Navette::with([
                'wilayaDepart',
                'wilayaArrivee',
                'hub',
                'createur',
                'acteurs',
                'gains'
            ])->withCount('livraisons')
                ->where(function ($q) use ($wilayaId) {
                    // Navettes qui concernent la wilaya du gestionnaire (départ, arrivée ou transit)
                    $q->where('wilaya_depart_id', $wilayaId)
                        ->orWhere('wilaya_arrivee_id', $wilayaId);

                    // Pour les wilayas de transit (stockées en JSON)
                    $q->orWhereJsonContains('wilayas_transit', $wilayaId);
                });

            // Filtres
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            if ($request->has('date_debut') && $request->has('date_fin') && $request->date_debut && $request->date_fin) {
                $query->whereBetween('date_depart', [
                    Carbon::parse($request->date_debut)->startOfDay(),
                    Carbon::parse($request->date_fin)->endOfDay()
                ]);
            }

            if ($request->has('search') && $request->search) {
                $query->where('reference', 'like', "%{$request->search}%");
            }

            // Tri
            $orderBy = $request->get('order_by', 'date_depart');
            $orderDir = $request->get('order_dir', 'desc');
            $query->orderBy($orderBy, $orderDir);

            $navettes = $query->paginate($request->get('per_page', 20));

            // Ajouter les totaux calculés
            $navettes->getCollection()->transform(function ($navette) {
                $navette->total_gains = $navette->gains->sum('montant_commission');
                $navette->nb_acteurs = $navette->acteurs->count();
                return $navette;
            });

            return response()->json([
                'success' => true,
                'data' => $navettes
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur ManagerNavetteController@index: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des navettes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Voir une navette spécifique
     */
    public function show($id): JsonResponse
    {
        try {
            $user = request()->user();
            $gestionnaire = $user->gestionnaire;
            $wilayaId = $gestionnaire->wilaya_id;

            if (!Str::isUuid($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de navette invalide'
                ], 400);
            }

            $navette = Navette::with([
                'wilayaDepart',
                'wilayaArrivee',
                'hub',
                'createur',
                'acteurs',
                'acteurs.gestionnaire.user',
                'acteurs.hub',
                'livraisons' => function ($query) {
                    $query->with([
                        'demandeLivraison' => function ($q) {
                            $q->with([
                                'client.user',
                                'colis',
                                'destinataire.user'
                            ]);
                        },
                        'client.user'
                    ]);
                },
                'gains'
            ])->find($id);

            if (!$navette) {
                return response()->json([
                    'success' => false,
                    'message' => 'Navette introuvable'
                ], 404);
            }

            // Vérifier que la navette concerne la wilaya du gestionnaire
            $concerneWilaya = (
                $navette->wilaya_depart_id == $wilayaId ||
                $navette->wilaya_arrivee_id == $wilayaId
            );

            // Vérifier aussi les wilayas de transit
            if (!$concerneWilaya && is_array($navette->wilayas_transit)) {
                $concerneWilaya = in_array($wilayaId, $navette->wilayas_transit);
            }

            if (!$concerneWilaya) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas accès à cette navette'
                ], 403);
            }

            // Ajouter les totaux
            $navette->total_gains = $navette->gains->sum('montant_commission');

            return response()->json([
                'success' => true,
                'data' => $navette
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur ManagerNavetteController@show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la navette: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer une nouvelle navette pour la wilaya du gestionnaire
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $gestionnaire = $user->gestionnaire;

        if (!$gestionnaire) {
            return response()->json([
                'success' => false,
                'message' => 'Profil gestionnaire introuvable'
            ], 403);
        }

        $wilayaId = $gestionnaire->wilaya_id;

        $validator = Validator::make($request->all(), [
            'wilaya_depart_id' => 'required|string|size:2',
            'wilaya_arrivee_id' => 'required|string|size:2',
            'wilayas_transit' => 'nullable|array',
            'wilayas_transit.*' => 'string|size:2',
            'heure_depart' => 'required|date_format:H:i',
            'date_depart' => 'required|date',
            'hub_id' => 'nullable|exists:hubs,id',
            'vehicule_immatriculation' => 'nullable|string|max:20',
            'capacite_max' => 'required|integer|min:1|max:500',
            'prix_base' => 'required|numeric|min:0',
            'prix_par_livraison' => 'required|numeric|min:0',
            'livraison_ids' => 'nullable|array',
            'livraison_ids.*' => 'exists:livraisons,id',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier que la wilaya de départ correspond à celle du gestionnaire
        if ($request->wilaya_depart_id != $wilayaId) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez créer une navette que depuis votre wilaya de gestion'
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Calculer la distance via le service d'optimisation
            $distance = $this->optimisationService->getDistance(
                $request->wilaya_depart_id,
                $request->wilaya_arrivee_id
            );

            $dureeEstimee = $this->optimisationService->estimerDuree($distance);

            $dateDepart = Carbon::parse($request->date_depart . ' ' . $request->heure_depart);
            $dateArrivee = $dateDepart->copy()->addHours($dureeEstimee);

            $navette = Navette::create([
                'wilaya_depart_id' => $request->wilaya_depart_id,
                'wilaya_arrivee_id' => $request->wilaya_arrivee_id,
                'wilayas_transit' => $request->wilayas_transit ?? [],
                'heure_depart' => $request->heure_depart,
                'heure_arrivee' => $dateArrivee->format('H:i'),
                'hub_id' => $request->hub_id,
                'vehicule_immatriculation' => $request->vehicule_immatriculation,
                'capacite_max' => $request->capacite_max,
                'prix_base' => $request->prix_base,
                'prix_par_livraison' => $request->prix_par_livraison,
                'distance_km' => $distance,
                'carburant_estime' => $this->optimisationService->estimerCarburant($distance),
                'peages_estimes' => $this->optimisationService->estimerPeages([$request->wilaya_depart_id, $request->wilaya_arrivee_id]),
                'status' => 'planifiee',
                'date_depart' => $dateDepart,
                'date_arrivee_prevue' => $dateArrivee,
                'created_by' => auth()->id(),
                'notes' => $request->notes
            ]);

            // Ajouter les wilayas de transit
            if ($request->has('wilayas_transit') && is_array($request->wilayas_transit)) {
                foreach ($request->wilayas_transit as $index => $wilayaCode) {
                    DB::table('navette_wilaya_transit')->insert([
                        'navette_id' => $navette->id,
                        'wilaya_code' => $wilayaCode,
                        'ordre' => $index,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            // Ajouter les livraisons
            if ($request->has('livraison_ids') && is_array($request->livraison_ids)) {
                $ordre = 1;
                foreach ($request->livraison_ids as $livraisonId) {
                    $livraison = Livraison::find($livraisonId);

                    if ($livraison) {
                        $navette->livraisons()->attach($livraisonId, [
                            'ordre_chargement' => $ordre++,
                            'date_prise_en_charge' => now()
                        ]);

                        $livraison->update([
                            'navette_id' => $navette->id,
                            'status' => 'prise_en_charge_ramassage'
                        ]);
                    }
                }
            }

            // Calculer la répartition des parts
            $navette->calculerRepartitionParts();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Navette créée avec succès',
                'data' => $navette->load(['wilayaDepart', 'wilayasTransit', 'wilayaArrivee', 'hub', 'acteurs'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur ManagerNavetteController@store: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les livraisons disponibles pour la navette
     * Filtre les livraisons qui ont pour wilaya de départ celle du gestionnaire
     */
    public function getLivraisonsDisponibles(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $gestionnaire = $user->gestionnaire;

            if (!$gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil gestionnaire introuvable'
                ], 403);
            }

            $wilayaId = $gestionnaire->wilaya_id;
            $wilayaNom = $this->getWilayaName($wilayaId); // Utiliser la méthode existante

            // Récupérer les livraisons en attente qui partent de la wilaya du gestionnaire
            $livraisons = Livraison::with([
                'demandeLivraison' => function ($q) use ($wilayaId, $wilayaNom) {
                    $q->where(function ($sub) use ($wilayaId, $wilayaNom) {
                        // Filtrer par code de wilaya
                        $sub->where('wilaya_depot', $wilayaId)
                            // Ou filtrer par nom de wilaya
                            ->orWhere('wilaya_depot', 'like', '%' . $wilayaNom . '%');
                    });
                },
                'client.user',
                'colis'
            ])
                ->whereNull('navette_id')  // Non assignées à une navette
                ->where('status', 'en_attente')  // En attente seulement
                ->whereHas('demandeLivraison', function ($q) use ($wilayaId, $wilayaNom) {
                    $q->where(function ($sub) use ($wilayaId, $wilayaNom) {
                        $sub->where('wilaya_depot', $wilayaId)
                            ->orWhere('wilaya_depot', 'like', '%' . $wilayaNom . '%');
                    });
                })
                ->orderBy('created_at', 'desc')
                ->get();

            $formattedLivraisons = $livraisons->map(function ($livraison) use ($wilayaId) {
                // Récupérer les informations de la demande de livraison
                $demande = $livraison->demandeLivraison;
                $colis = $livraison->colis;

                // Calculer le prix
                $prix = 0;
                if ($colis && $colis->colis_prix) {
                    $prix = $colis->colis_prix;
                } elseif ($demande && $demande->prix) {
                    $prix = $demande->prix;
                }

                return [
                    'id' => $livraison->id,
                    'reference' => $demande->reference ?? $livraison->id,
                    'client' => $livraison->client?->user?->prenom . ' ' . $livraison->client?->user?->nom,
                    'destination' => $demande->addresse_delivery ?? 'Non spécifiée',
                    'wilaya_depart' => $demande->wilaya_depot ?? $wilayaId,
                    'wilaya_arrivee' => $demande->wilaya ?? 'Non spécifiée',
                    'colis_label' => $colis->colis_label ?? 'N/A',
                    'poids' => $colis->poids ?? 0,
                    'prix' => $prix,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedLivraisons
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur ManagerNavetteController@getLivraisonsDisponibles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livraisons: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Démarrer une navette
     */
    public function demarrer($id): JsonResponse
    {
        try {
            $user = request()->user();
            $gestionnaire = $user->gestionnaire;
            $wilayaId = $gestionnaire->wilaya_id;

            if (!Str::isUuid($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de navette invalide'
                ], 400);
            }

            $navette = Navette::find($id);

            if (!$navette) {
                return response()->json([
                    'success' => false,
                    'message' => 'Navette introuvable'
                ], 404);
            }

            // Vérifier que la navette concerne la wilaya du gestionnaire
            if ($navette->wilaya_depart_id != $wilayaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas démarrer cette navette'
                ], 403);
            }

            if ($navette->status !== 'planifiee') {
                return response()->json([
                    'success' => false,
                    'message' => 'Seules les navettes planifiées peuvent être démarrées'
                ], 400);
            }

            $navette->update([
                'status' => 'en_cours',
                'date_depart' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Navette démarrée',
                'data' => $navette
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur ManagerNavetteController@demarrer: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du démarrage: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terminer une navette
     */
    public function terminer($id): JsonResponse
    {
        try {
            $user = request()->user();
            $gestionnaire = $user->gestionnaire;
            $wilayaId = $gestionnaire->wilaya_id;

            if (!Str::isUuid($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de navette invalide'
                ], 400);
            }

            $navette = Navette::find($id);

            if (!$navette) {
                return response()->json([
                    'success' => false,
                    'message' => 'Navette introuvable'
                ], 404);
            }

            // Vérifier que la navette concerne la wilaya du gestionnaire
            if ($navette->wilaya_depart_id != $wilayaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas terminer cette navette'
                ], 403);
            }

            if ($navette->status !== 'en_cours') {
                return response()->json([
                    'success' => false,
                    'message' => 'Seules les navettes en cours peuvent être terminées'
                ], 400);
            }

            DB::transaction(function () use ($navette) {
                $navette->update([
                    'status' => 'terminee',
                    'date_arrivee_reelle' => now()
                ]);

                foreach ($navette->livraisons as $livraison) {
                    $navette->livraisons()->updateExistingPivot($livraison->id, [
                        'date_livraison' => now()
                    ]);

                    $livraison->update([
                        'status' => 'en_transit'
                    ]);
                }

                event(new NavetteTerminee($navette));
            });

            return response()->json([
                'success' => true,
                'message' => 'Navette terminée et gains calculés',
                'data' => $navette->load(['acteurs', 'gains'])
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur ManagerNavetteController@terminer: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la terminaison: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Annuler une navette
     */
    public function annuler($id): JsonResponse
    {
        try {
            $user = request()->user();
            $gestionnaire = $user->gestionnaire;
            $wilayaId = $gestionnaire->wilaya_id;

            if (!Str::isUuid($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de navette invalide'
                ], 400);
            }

            $navette = Navette::find($id);

            if (!$navette) {
                return response()->json([
                    'success' => false,
                    'message' => 'Navette introuvable'
                ], 404);
            }

            // Vérifier que la navette concerne la wilaya du gestionnaire
            if ($navette->wilaya_depart_id != $wilayaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas annuler cette navette'
                ], 403);
            }

            if (!in_array($navette->status, ['planifiee', 'en_cours'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette navette ne peut pas être annulée'
                ], 400);
            }

            DB::transaction(function () use ($navette) {
                foreach ($navette->livraisons as $livraison) {
                    $livraison->update([
                        'navette_id' => null,
                        'status' => 'en_attente'
                    ]);
                }

                $navette->livraisons()->detach();
                $navette->update(['status' => 'annulee']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Navette annulée'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur ManagerNavetteController@annuler: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir le nom de la wilaya à partir du code
     */
    private function getWilayaName($code): string
    {
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

        return $wilayas[$code] ?? $code;
    }
}
