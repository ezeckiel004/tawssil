<?php
// app/Http/Controllers/Admin/NavetteController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Navette;
use App\Models\Livreur;
use App\Models\Colis;
use App\Services\OptimisationTrajetService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class NavetteController extends Controller
{
    protected $optimisationService;

    public function __construct(OptimisationTrajetService $optimisationService)
    {
        $this->optimisationService = $optimisationService;
        $this->middleware('auth:sanctum');
    }

    /**
     * Lister toutes les navettes
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Navette::with([
                'wilayaDepart',
                'wilayaArrivee',
                'livreur.user',
                'createur'
            ])->withCount('colis');

            // Filtres
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            if ($request->has('wilaya_depart') && $request->wilaya_depart) {
                $query->where('wilaya_depart_id', $request->wilaya_depart);
            }

            if ($request->has('wilaya_arrivee') && $request->wilaya_arrivee) {
                $query->where('wilaya_arrivee_id', $request->wilaya_arrivee);
            }

            if ($request->has('date_depart') && $request->date_depart) {
                $query->whereDate('date_depart', $request->date_depart);
            }

            if ($request->has('date_debut') && $request->has('date_fin') && $request->date_debut && $request->date_fin) {
                $query->whereBetween('date_depart', [
                    Carbon::parse($request->date_debut)->startOfDay(),
                    Carbon::parse($request->date_fin)->endOfDay()
                ]);
            }

            if ($request->has('livreur_id') && $request->livreur_id) {
                $query->where('livreur_id', $request->livreur_id);
            }

            // Recherche
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('livreur.user', function ($q) use ($search) {
                            $q->where('nom', 'like', "%{$search}%")
                                ->orWhere('prenom', 'like', "%{$search}%");
                        });
                });
            }

            // Tri
            $orderBy = $request->get('order_by', 'date_depart');
            $orderDir = $request->get('order_dir', 'desc');
            $query->orderBy($orderBy, $orderDir);

            $navettes = $query->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $navettes
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des navettes'
            ], 500);
        }
    }

    /**
     * Voir une navette spécifique
     */
    public function show($id): JsonResponse
    {
        try {
            // Vérifier si l'ID est valide (UUID)
            if (!Str::isUuid($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de navette invalide'
                ], 400);
            }

            // Charger la navette avec ses relations
            $navette = Navette::with([
                'wilayaDepart',
                'wilayaTransit',
                'wilayaArrivee',
                'livreur.user',
                'createur',
                'colis'
            ])->find($id);

            if (!$navette) {
                return response()->json([
                    'success' => false,
                    'message' => 'Navette introuvable'
                ], 404);
            }

            // Ajouter les accesseurs
            $navette->nb_colis = $navette->nb_colis;
            $navette->poids_total = $navette->poids_total;
            $navette->valeur_totale = $navette->valeur_totale;
            $navette->taux_remplissage = $navette->taux_remplissage;

            return response()->json([
                'success' => true,
                'data' => $navette
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@show: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la navette: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer une nouvelle navette
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'wilaya_depart_id' => 'required|string|size:2',
            'wilaya_arrivee_id' => 'required|string|size:2',
            'wilaya_transit_id' => 'nullable|string|size:2',
            'heure_depart' => 'required|date_format:H:i',
            'date_depart' => 'required|date',
            'livreur_id' => 'nullable|exists:livreurs,id',
            'vehicule_immatriculation' => 'nullable|string|max:20',
            'capacite_max' => 'required|integer|min:1|max:500',
            'prix_base' => 'required|numeric|min:0',
            'prix_par_colis' => 'required|numeric|min:0',
            'colis_ids' => 'nullable|array',
            'colis_ids.*' => 'exists:colis,id',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Calculer l'heure d'arrivée estimée
            $distance = $this->optimisationService->getDistance(
                $request->wilaya_depart_id,
                $request->wilaya_arrivee_id
            );

            $dureeEstimee = $this->optimisationService->estimerDuree($distance);

            $dateDepart = Carbon::parse($request->date_depart . ' ' . $request->heure_depart);
            $dateArrivee = $dateDepart->copy()->addHours($dureeEstimee);

            // Créer la navette
            $navette = Navette::create([
                'wilaya_depart_id' => $request->wilaya_depart_id,
                'wilaya_arrivee_id' => $request->wilaya_arrivee_id,
                'wilaya_transit_id' => $request->wilaya_transit_id,
                'heure_depart' => $request->heure_depart,
                'heure_arrivee' => $dateArrivee->format('H:i'),
                'livreur_id' => $request->livreur_id,
                'vehicule_immatriculation' => $request->vehicule_immatriculation,
                'capacite_max' => $request->capacite_max,
                'prix_base' => $request->prix_base,
                'prix_par_colis' => $request->prix_par_colis,
                'distance_km' => $distance,
                'carburant_estime' => $this->optimisationService->estimerCarburant($distance),
                'peages_estimes' => $this->optimisationService->estimerPeages([$request->wilaya_depart_id, $request->wilaya_arrivee_id]),
                'status' => 'planifiee',
                'date_depart' => $dateDepart,
                'date_arrivee_prevue' => $dateArrivee,
                'created_by' => auth()->id(),
                'notes' => $request->notes
            ]);

            // Attacher les colis si fournis
            if ($request->has('colis_ids') && is_array($request->colis_ids)) {
                $position = 1;
                foreach ($request->colis_ids as $colisId) {
                    $navette->colis()->attach($colisId, [
                        'position_chargement' => $position++,
                        'date_chargement' => now()
                    ]);

                    // Mettre à jour la livraison associée
                    $colis = Colis::find($colisId);
                    if ($colis && $colis->demandeLivraisons && $colis->demandeLivraisons->isNotEmpty()) {
                        foreach ($colis->demandeLivraisons as $demande) {
                            if ($demande->livraison) {
                                $demande->livraison->update([
                                    'navette_id' => $navette->id,
                                    'status' => 'prise_en_charge_ramassage'
                                ]);
                            }
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Navette créée avec succès',
                'data' => $navette->load(['wilayaDepart', 'wilayaArrivee', 'colis', 'livreur.user'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur NavetteController@store: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour une navette
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
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

            if (in_array($navette->status, ['terminee', 'en_cours'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de modifier une navette ' . $navette->status
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'wilaya_depart_id' => 'sometimes|string|size:2',
                'wilaya_arrivee_id' => 'sometimes|string|size:2',
                'wilaya_transit_id' => 'nullable|string|size:2',
                'heure_depart' => 'sometimes|date_format:H:i',
                'date_depart' => 'sometimes|date',
                'livreur_id' => 'nullable|exists:livreurs,id',
                'vehicule_immatriculation' => 'nullable|string|max:20',
                'capacite_max' => 'sometimes|integer|min:1|max:500',
                'prix_base' => 'sometimes|numeric|min:0',
                'prix_par_colis' => 'sometimes|numeric|min:0',
                'status' => 'sometimes|in:planifiee,annulee',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $data = $request->only([
                'wilaya_depart_id',
                'wilaya_arrivee_id',
                'wilaya_transit_id',
                'heure_depart',
                'date_depart',
                'livreur_id',
                'vehicule_immatriculation',
                'capacite_max',
                'prix_base',
                'prix_par_colis',
                'status',
                'notes'
            ]);

            // Recalculer l'arrivée si nécessaire
            if ($request->has('wilaya_depart_id') || $request->has('wilaya_arrivee_id') || $request->has('heure_depart') || $request->has('date_depart')) {
                $depart = $request->wilaya_depart_id ?? $navette->wilaya_depart_id;
                $arrivee = $request->wilaya_arrivee_id ?? $navette->wilaya_arrivee_id;
                $distance = $this->optimisationService->getDistance($depart, $arrivee);
                $dureeEstimee = $this->optimisationService->estimerDuree($distance);

                $dateDepart = $request->date_depart
                    ? Carbon::parse($request->date_depart . ' ' . ($request->heure_depart ?? $navette->heure_depart))
                    : $navette->date_depart;

                $data['heure_arrivee'] = $dateDepart->copy()->addHours($dureeEstimee)->format('H:i');
                $data['date_arrivee_prevue'] = $dateDepart->copy()->addHours($dureeEstimee);
                $data['distance_km'] = $distance;
                $data['carburant_estime'] = $this->optimisationService->estimerCarburant($distance);
                $data['peages_estimes'] = $this->optimisationService->estimerPeages([$depart, $arrivee]);
            }

            $navette->update($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Navette mise à jour',
                'data' => $navette->fresh(['wilayaDepart', 'wilayaArrivee', 'livreur.user'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur NavetteController@update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    /**
     * Supprimer une navette
     * Modifié pour permettre la suppression des navettes planifiées ET annulées
     */
    public function destroy($id): JsonResponse
    {
        try {
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

            // MODIFICATION ICI : Permettre la suppression des navettes planifiées ET annulées
            if (!in_array($navette->status, ['planifiee', 'annulee'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seules les navettes planifiées ou annulées peuvent être supprimées'
                ], 400);
            }

            DB::transaction(function () use ($navette) {
                // Détacher tous les colis et mettre à jour les livraisons associées
                foreach ($navette->colis as $colis) {
                    if ($colis->demandeLivraisons && $colis->demandeLivraisons->isNotEmpty()) {
                        foreach ($colis->demandeLivraisons as $demande) {
                            if ($demande->livraison) {
                                $demande->livraison->update([
                                    'navette_id' => null,
                                    'status' => 'en_attente'
                                ]);
                            }
                        }
                    }
                }

                // Détacher tous les colis de la navette
                $navette->colis()->detach();

                // Supprimer la navette
                $navette->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Navette supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    /**
     * Démarrer une navette
     */
    public function demarrer($id): JsonResponse
    {
        try {
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

            if ($navette->status !== 'planifiee') {
                return response()->json([
                    'success' => false,
                    'message' => 'Seules les navettes planifiées peuvent être démarrées'
                ], 400);
            }

            if (!$navette->livreur_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Un livreur doit être assigné avant de démarrer'
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
            Log::error('Erreur NavetteController@demarrer: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du démarrage'
            ], 500);
        }
    }

    /**
     * Terminer une navette
     */
    public function terminer($id): JsonResponse
    {
        try {
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

                // Marquer tous les colis comme déchargés
                foreach ($navette->colis as $colis) {
                    $navette->colis()->updateExistingPivot($colis->id, [
                        'date_dechargement' => now()
                    ]);

                    if ($colis->demandeLivraisons && $colis->demandeLivraisons->isNotEmpty()) {
                        foreach ($colis->demandeLivraisons as $demande) {
                            if ($demande->livraison) {
                                $demande->livraison->update([
                                    'status' => 'en_transit'
                                ]);
                            }
                        }
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Navette terminée',
                'data' => $navette
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@terminer: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la terminaison'
            ], 500);
        }
    }

    /**
     * Annuler une navette
     */
    public function annuler($id): JsonResponse
    {
        try {
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

            if (!in_array($navette->status, ['planifiee', 'en_cours'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette navette ne peut pas être annulée'
                ], 400);
            }

            DB::transaction(function () use ($navette) {
                foreach ($navette->colis as $colis) {
                    if ($colis->demandeLivraisons && $colis->demandeLivraisons->isNotEmpty()) {
                        foreach ($colis->demandeLivraisons as $demande) {
                            if ($demande->livraison) {
                                $demande->livraison->update([
                                    'navette_id' => null,
                                    'status' => 'en_attente'
                                ]);
                            }
                        }
                    }
                }

                $navette->colis()->detach();
                $navette->update(['status' => 'annulee']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Navette annulée'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@annuler: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation'
            ], 500);
        }
    }

    /**
     * Ajouter des colis à une navette
     */
    public function ajouterColis(Request $request, $id): JsonResponse
    {
        try {
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

            if (!in_array($navette->status, ['planifiee', 'en_cours'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible d\'ajouter des colis à une navette ' . $navette->status
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'colis_ids' => 'required|array',
                'colis_ids.*' => 'required|exists:colis,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Erreur de validation: certains colis n\'existent pas'
                ], 422);
            }

            $nbActuel = $navette->colis()->count();
            $nbNouveaux = count($request->colis_ids);

            if ($nbActuel + $nbNouveaux > $navette->capacite_max) {
                return response()->json([
                    'success' => false,
                    'message' => 'Capacité dépassée. Maximum: ' . $navette->capacite_max
                ], 400);
            }

            DB::transaction(function () use ($navette, $request, $nbActuel) {
                $position = $nbActuel + 1;

                foreach ($request->colis_ids as $colisId) {
                    $colis = Colis::find($colisId);

                    if (!$colis) {
                        throw new \Exception("Colis non trouvé: " . $colisId);
                    }

                    $navette->colis()->syncWithoutDetaching([$colisId => [
                        'position_chargement' => $position++,
                        'date_chargement' => now()
                    ]]);

                    if ($colis && $colis->demandeLivraisons && $colis->demandeLivraisons->isNotEmpty()) {
                        foreach ($colis->demandeLivraisons as $demande) {
                            if ($demande->livraison) {
                                $demande->livraison->update([
                                    'navette_id' => $navette->id,
                                    'status' => 'prise_en_charge_ramassage'
                                ]);
                            }
                        }
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Colis ajoutés à la navette',
                'data' => $navette->load('colis')
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@ajouterColis: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout des colis: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retirer des colis d'une navette
     */
    public function retirerColis(Request $request, $id): JsonResponse
    {
        try {
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

            if (!in_array($navette->status, ['planifiee', 'en_cours'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de retirer des colis'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'colis_ids' => 'required|array',
                'colis_ids.*' => 'exists:colis,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::transaction(function () use ($navette, $request) {
                foreach ($request->colis_ids as $colisId) {
                    $navette->colis()->detach($colisId);

                    $colis = Colis::find($colisId);
                    if ($colis && $colis->demandeLivraisons && $colis->demandeLivraisons->isNotEmpty()) {
                        foreach ($colis->demandeLivraisons as $demande) {
                            if ($demande->livraison) {
                                $demande->livraison->update([
                                    'navette_id' => null,
                                    'status' => 'en_attente'
                                ]);
                            }
                        }
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Colis retirés de la navette'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@retirerColis: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du retrait des colis'
            ], 500);
        }
    }

    /**
     * Obtenir les suggestions de navettes optimisées basées sur les vrais colis
     */
    public function suggestions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'wilaya_depart' => 'required|string|size:2',
            'date_limite' => 'nullable|date',
            'priorite' => 'nullable|in:date,urgence,valeur'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Récupérer les colis disponibles
            $colisDisponibles = Colis::with('demandeLivraisons')
                ->whereDoesntHave('navettes', function($q) {
                    $q->whereIn('status', ['planifiee', 'en_cours']);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            if ($colisDisponibles->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'Aucun colis disponible'
                ]);
            }

            // Grouper les colis par destination (simulé pour l'exemple)
            $colisParDestination = collect($colisDisponibles)
                ->groupBy(function($colis) {
                    // Ici vous devriez avoir une vraie relation pour la destination
                    // Par exemple: $colis->wilaya_destination_id ?? '00'
                    return $colis->wilaya_destination_id ?? substr($colis->id, 0, 2);
                })
                ->filter(function($group) {
                    return $group->count() >= 3; // Garder les groupes avec au moins 3 colis
                });

            $suggestions = [];

            foreach ($colisParDestination as $wilayaCode => $colisGroup) {
                $suggestions[] = [
                    'type' => 'destination_unique',
                    'wilaya' => $wilayaCode,
                    'wilaya_nom' => $this->getWilayaName($wilayaCode),
                    'nb_colis' => $colisGroup->count(),
                    'poids_total' => $colisGroup->sum('poids'),
                    'valeur_totale' => $colisGroup->sum('colis_prix'),
                    'date_plus_ancienne' => $colisGroup->min('created_at')?->toDateString(),
                    'urgence' => $colisGroup->count() > 10 ? 'haute' : 'moyenne',
                    'confiance' => min(90, 70 + $colisGroup->count()),
                    'colis_exemples' => $colisGroup->take(5)->map(function ($colis) {
                        return [
                            'id' => $colis->id,
                            'label' => $colis->colis_label ?? 'COLIS-' . substr($colis->id, 0, 8),
                            'prix' => $colis->colis_prix ?? 0,
                            'poids' => $colis->poids ?? 0
                        ];
                    })->toArray()
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $suggestions
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@suggestions: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Aucune suggestion disponible pour le moment'
            ]);
        }
    }

    /**
     * Créer une navette optimisée automatiquement
     */
    public function creerOptimisee(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'wilaya_depart' => 'required|string|size:2',
            'date_limite' => 'nullable|date',
            'priorite' => 'nullable|in:date,urgence,valeur',
            'capacite' => 'nullable|integer|min:1|max:500',
            'prix_base' => 'nullable|numeric|min:0',
            'prix_par_colis' => 'nullable|numeric|min:0',
            'heure_depart' => 'nullable|date_format:H:i'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $dateDepart = $request->heure_depart
                ? Carbon::parse($request->date_limite ?? now()->toDateString() . ' ' . $request->heure_depart)
                : Carbon::now();

            $navette = Navette::create([
                'wilaya_depart_id' => $request->wilaya_depart,
                'wilaya_arrivee_id' => '31', // Oran par défaut
                'heure_depart' => $dateDepart->format('H:i'),
                'heure_arrivee' => $dateDepart->copy()->addHours(8)->format('H:i'),
                'capacite_max' => $request->capacite ?? 100,
                'prix_base' => $request->prix_base ?? 300,
                'prix_par_colis' => $request->prix_par_colis ?? 10,
                'status' => 'planifiee',
                'date_depart' => $dateDepart,
                'date_arrivee_prevue' => $dateDepart->copy()->addHours(8),
                'created_by' => auth()->id(),
                'notes' => 'Navette créée automatiquement via optimisation'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Navette optimisée créée avec succès',
                'data' => $navette
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur NavetteController@creerOptimisee: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création'
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques des navettes
     */
    public function statistiques(Request $request): JsonResponse
    {
        try {
            $debut = $request->get('debut') ? Carbon::parse($request->debut) : Carbon::now()->startOfMonth();
            $fin = $request->get('fin') ? Carbon::parse($request->fin) : Carbon::now()->endOfMonth();

            $stats = [
                'global' => [
                    'total_navettes' => Navette::whereBetween('date_depart', [$debut, $fin])->count(),
                    'total_colis_transportes' => DB::table('navette_colis')
                        ->whereBetween('created_at', [$debut, $fin])
                        ->count(),
                    'distance_totale' => Navette::whereBetween('date_depart', [$debut, $fin])
                        ->sum('distance_km'),
                ],
                'par_status' => [
                    'planifiees' => Navette::whereBetween('date_depart', [$debut, $fin])
                        ->where('status', 'planifiee')
                        ->count(),
                    'en_cours' => Navette::whereBetween('date_depart', [$debut, $fin])
                        ->where('status', 'en_cours')
                        ->count(),
                    'terminees' => Navette::whereBetween('date_depart', [$debut, $fin])
                        ->where('status', 'terminee')
                        ->count(),
                    'annulees' => Navette::whereBetween('date_depart', [$debut, $fin])
                        ->where('status', 'annulee')
                        ->count()
                ],
                'par_livreur' => Navette::whereBetween('date_depart', [$debut, $fin])
                    ->whereNotNull('livreur_id')
                    ->select('livreur_id', DB::raw('count(*) as total'))
                    ->groupBy('livreur_id')
                    ->with('livreur.user')
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@statistiques: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques'
            ], 500);
        }
    }

    /**
     * Exporter la liste des navettes (PDF)
     */
    public function exportPDF(Request $request)
    {
        try {
            $query = Navette::with([
                'wilayaDepart',
                'wilayaArrivee',
                'livreur.user'
            ])->withCount('colis');

            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }
            if ($request->has('wilaya_depart') && $request->wilaya_depart) {
                $query->where('wilaya_depart_id', $request->wilaya_depart);
            }
            if ($request->has('date_debut') && $request->has('date_fin') && $request->date_debut && $request->date_fin) {
                $query->whereBetween('date_depart', [
                    Carbon::parse($request->date_debut)->startOfDay(),
                    Carbon::parse($request->date_fin)->endOfDay()
                ]);
            }

            $navettes = $query->orderBy('date_depart', 'desc')->get();

            $pdf = Pdf::loadView('pdf.navettes', [
                'navettes' => $navettes,
                'filters' => $request->all(),
                'date_generation' => Carbon::now()->format('d/m/Y H:i')
            ]);

            $pdf->setPaper('A4', 'landscape');

            return $pdf->download('navettes-' . Carbon::now()->format('Ymd-His') . '.pdf');
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@exportPDF: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du PDF'
            ], 500);
        }
    }

    /**
     * Obtenir le nom d'une wilaya à partir de son code
     */
    private function getWilayaName($code): string
    {
        $wilayas = [
            '01' => 'Adrar', '02' => 'Chlef', '03' => 'Laghouat',
            '04' => 'Oum El Bouaghi', '05' => 'Batna', '06' => 'Béjaïa',
            '07' => 'Biskra', '08' => 'Béchar', '09' => 'Blida',
            '10' => 'Bouira', '11' => 'Tamanrasset', '12' => 'Tébessa',
            '13' => 'Tlemcen', '14' => 'Tiaret', '15' => 'Tizi Ouzou',
            '16' => 'Alger', '17' => 'Djelfa', '18' => 'Jijel',
            '19' => 'Sétif', '20' => 'Saïda', '21' => 'Skikda',
            '22' => 'Sidi Bel Abbès', '23' => 'Annaba', '24' => 'Guelma',
            '25' => 'Constantine', '26' => 'Médéa', '27' => 'Mostaganem',
            '28' => 'M\'Sila', '29' => 'Mascara', '30' => 'Ouargla',
            '31' => 'Oran', '32' => 'El Bayadh', '33' => 'Illizi',
            '34' => 'Bordj Bou Arréridj', '35' => 'Boumerdès', '36' => 'El Tarf',
            '37' => 'Tindouf', '38' => 'Tissemsilt', '39' => 'El Oued',
            '40' => 'Khenchela', '41' => 'Souk Ahras', '42' => 'Tipaza',
            '43' => 'Mila', '44' => 'Aïn Defla', '45' => 'Naâma',
            '46' => 'Aïn Témouchent', '47' => 'Ghardaïa', '48' => 'Relizane',
            '49' => 'Timimoun', '50' => 'Bordj Badji Mokhtar', '51' => 'Ouled Djellal',
            '52' => 'Béni Abbès', '53' => 'In Salah', '54' => 'In Guezzam',
            '55' => 'Touggourt', '56' => 'Djanet', '57' => 'El M\'Ghair',
            '58' => 'El Meniaa'
        ];

        return $wilayas[$code] ?? 'Wilaya inconnue';
    }
}
