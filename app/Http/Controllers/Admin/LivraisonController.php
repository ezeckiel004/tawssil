<?php

namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller;
use App\Models\Livraison;
use App\Models\Commentaire;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\DemandeLivraison;
use App\Enums\NotificationType;
use App\Models\User;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Barryvdh\DomPDF\Facade\Pdf;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LivraisonController extends Controller
{
    /**
     * Afficher toutes les livraisons.
     */
    public function index(): JsonResponse
    {
        $livraisons = Livraison::get();

        $datas = [];
        foreach ($livraisons as $livraison) {
            $datas[] = [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'created_at' => $livraison->created_at,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison->load([
                    'client',
                    'destinataire',
                    'colis',
                ]),
                'destinataire' => $livraison->demandeLivraison->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,

            ];
        }

        return response()->json($datas, 200);
    }

    public function statistiquesClient($id): JsonResponse
    {
        $livraisons = Livraison::where('client_id', $id)->get();
        $total = $livraisons->isEmpty() ? 0 : $livraisons->count();
        $statuss = [
            'livraisons_terminees' => 0,
            'livraisons_en_attente' => 0,
            'livraisons_en_cours' => 0,
            'total_livraisons' => $total
        ];

        // Compter les livraisons selon leur status
        foreach ($livraisons as $livraison) {
            switch ($livraison->status) {
                case 'livre':
                    $statuss['livraisons_terminees']++;
                    break;
                case 'en_attente':
                    $statuss['livraisons_en_attente']++;
                    break;
                case 'prise_en_charge_ramassage':
                case 'prise_en_charge_livraison':
                case 'ramasse':
                case 'en_transit':
                    $statuss['livraisons_en_cours']++;
                    break;
            }
        }

        return response()->json(
            $statuss,
            200
        );
    }

    //Statistiques livreurs
    public function statistiquesLivreur($id): JsonResponse
    {
        $livraisons = Livraison::where('livreur_distributeur_id', $id)
            ->orWhere('livreur_ramasseur_id', $id)
            ->get();

        $total = $livraisons->isEmpty() ? 0 : $livraisons->count();
        $status = [
            'livraisons_terminees' => 0,
            'livraisons_en_attente' => 0,
            'livraisons_en_cours' => 0,
            'total_livraisons' => $total
        ];

        // Compter les livraisons selon leur status
        foreach ($livraisons as $livraison) {
            switch ($livraison->status) {
                case 'livre':
                    $status['livraisons_terminees']++;
                    break;
                case 'en_attente':
                    $status['livraisons_en_attente']++;
                    break;
                case 'prise_en_charge_ramassage':
                case 'prise_en_charge_livraison':
                case 'ramasse':
                case 'en_transit':
                    $status['livraisons_en_cours']++;
                    break;
            }
        }

        return response()->json(
            $status,
            200
        );
    }

    public function livraisonsEnCours(): JsonResponse
    {
        $livraisons = Livraison::whereNotIn('status', ['en_attente', 'livre'])->get();

        $datas = [];
        foreach ($livraisons as $livraison) {
            $datas[] = [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'created_at' => $livraison->created_at,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison->load([
                    'client',
                    'destinataire',
                    'colis',
                ]),
                'destinataire' => $livraison->demandeLivraison->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,

            ];
        }
        return response()->json($datas, 200);
    }

    public function livraisonsClientEnCours($clientId): JsonResponse
    {
        $livraisons = Livraison::where('client_id', $clientId)
            ->whereNotIn('status', ['en_attente', 'livre'])
            ->get();

        $datas = [];
        foreach ($livraisons as $livraison) {
            $datas[] = [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison->load([
                    'client',
                    'destinataire',
                    'colis',
                ]),
                'destinataire' => $livraison->demandeLivraison->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,

            ];
        }
        return response()->json($datas, 200);
    }

    public function livraisonsLivreurEnCours($livreurId): JsonResponse
    {
        $livraisons = Livraison::where(function ($query) use ($livreurId) {
            $query->where('livreur_distributeur_id', $livreurId)
                ->orWhere('livreur_ramasseur_id', $livreurId);
        })
            ->whereNotIn('status', ['en_attente', 'livre'])
            ->get();

        $datas = [];
        foreach ($livraisons as $livraison) {
            $datas[] = [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison->load([
                    'client',
                    'destinataire',
                    'colis',
                ]),
                'destinataire' => $livraison->demandeLivraison->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,

            ];
        }

        return response()->json($datas, 200);
    }

    /**
     * Afficher une livraison spécifique.
     */
    public function show($id): JsonResponse
    {
        Log::info("Récupération de la livraison avec ID: " . $id);

        $livraison = $this->findLivraison($id);

        if (!$livraison) {
            Log::warning("Livraison introuvable pour ID: " . $id);
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        }

        Log::info("Livraison trouvée: " . $livraison->id);

        return response()->json(
            [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'created_at' => $livraison->created_at,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison->load([
                    'client',
                    'destinataire',
                    'colis',
                ]),
                'destinataire' => $livraison->demandeLivraison->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,

            ],

            200
        );
    }

    public function getByClient($id): JsonResponse
    {
        try {
            Log::info("Récupération des livraisons pour client ID: " . $id);

            $livraisons = Livraison::where('client_id', $id)
                ->get();

            $datas = [];
            foreach ($livraisons as $livraison) {
                $datas[] = [
                    'id' => $livraison->id,
                    'client_id' => $livraison->client_id,
                    'created_at' => $livraison->created_at,
                    'demande_livraisons_id' => $livraison->demande_livraisons_id,
                    'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                    'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                    'bordereau_id' => $livraison->bordereau_id,
                    'code_pin' => $livraison->code_pin,
                    'date_ramassage' => $livraison->date_ramassage,
                    'date_livraison' => $livraison->date_livraison,
                    'status' => $livraison->status,
                    'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                    'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                    'demande_livraison' => $livraison->demandeLivraison->load([
                        'colis',
                    ]),
                    'destinataire' => $livraison->demandeLivraison->destinataire->load([
                        'user'
                    ]),
                    'client' => $livraison->client->load([
                        'user'
                    ]),
                    'commentaires' => $livraison->commentaires,
                    'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,

                ];
            }

            Log::info("Nombre de livraisons trouvées pour le client: " . count($datas));

            return response()->json(
                $datas,
                200
            );
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des livraisons du client ID {$id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livraisons du client.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getByLivreur($id): JsonResponse
    {
        try {
            Log::info("Récupération des livraisons pour livreur ID: " . $id);

            $livraisons = Livraison::where([
                'livreur_distributeur_id' => $id,
                'livreur_ramasseur_id' => $id,
            ])->orWhere([
                'livreur_distributeur_id' => $id,
            ])->orWhere([
                'livreur_ramasseur_id' => $id,
            ])->get();

            $datas = [];
            foreach ($livraisons as $livraison) {
                $datas[] = [
                    'id' => $livraison->id,
                    'client_id' => $livraison->client_id,
                    'demande_livraisons_id' => $livraison->demande_livraisons_id,
                    'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                    'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                    'bordereau_id' => $livraison->bordereau_id,
                    'code_pin' => $livraison->code_pin,
                    'date_ramassage' => $livraison->date_ramassage,
                    'date_livraison' => $livraison->date_livraison,
                    'status' => $livraison->status,
                    'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                    'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                    'demande_livraison' => $livraison->demandeLivraison->load([
                        'client',
                        'destinataire',
                        'colis',
                    ]),
                    'destinataire' => $livraison->demandeLivraison->destinataire?->user,
                    'client' => $livraison->client?->user,
                    'commentaires' => $livraison->commentaires,
                    'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,

                ];
            }

            Log::info("Nombre de livraisons trouvées pour le livreur: " . count($datas));

            return response()->json(
                $datas,
                200
            );
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des livraisons du livreur ID {$id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livraisons du livreur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Attribuer un livreur à une livraison.
     */
    public function assignLivreur(Request $request, $id): JsonResponse
    {
        Log::info("Début attribution livreur pour livraison ID: " . $id);

        $validated = Validator::make($request->all(), [
            'livreur_id' => 'required|string|exists:livreurs,id',
            'type' => 'required|integer|in:1,2',
        ]);

        if ($validated->fails()) {
            Log::warning("Validation échouée pour l'attribution de livreur: " . json_encode($validated->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validated->errors(),
            ], 422);
        }

        $validatedData = $validated->validated();

        $livraison = $this->findLivraison($id);
        $type = $validatedData['type'];

        if (!$livraison) {
            Log::warning("Livraison introuvable pour attribution: " . $id);
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        }

        if (Auth::user()->role !== 'admin') {
            Log::warning("Utilisateur non autorisé à attribuer un livreur: " . Auth::user()->id);
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à attribuer un livreur à cette livraison',
            ], 403);
        }

        try {
            DB::beginTransaction();

            if ($type == 2) {
                if ($livraison->status == 'en_transit') {
                    Log::warning("Impossible d'attribuer un distributeur, colis déjà en transit: " . $id);
                    return response()->json([
                        'success' => false,
                        'message' => 'Le colis est deja en transit, vous ne pouvez plus  attribuer un autre distributeur',
                    ], 400);
                } else {
                    Log::info("Attribution du distributeur " . $validatedData['livreur_id'] . " à la livraison " . $id);
                    $livraison->update([
                        'livreur_distributeur_id' => $validatedData['livreur_id'],
                        'status' => 'prise_en_charge_livraison'
                    ]);
                }
            } elseif ($type == 1) {
                if ($livraison->status == 'ramasse') {
                    Log::warning("Impossible d'attribuer un ramasseur, colis déjà ramassé: " . $id);
                    return response()->json([
                        'success' => false,
                        'message' => 'Le colis a deja ete ramasse , vous ne pouvez plus  attribuer un autre ramasseur',
                    ], 400);
                } else {
                    Log::info("Attribution du ramasseur " . $validatedData['livreur_id'] . " à la livraison " . $id);
                    $livraison->update([
                        'livreur_ramasseur_id' => $validatedData['livreur_id'],
                        'status' => 'prise_en_charge_ramassage'
                    ]);
                }
            } else {
                Log::warning("Type de livreur invalide: " . $type);
                return response()->json([
                    'success' => false,
                    'message' => 'Type de livreur invalide',
                ], 400);
            }

            DB::commit();

            Log::info("Livreur attribué avec succès à la livraison " . $id);

            return response()->json(
                $livraison,
                200
            );
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Erreur lors de l'attribution du livreur à la livraison {$id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'attribution du livreur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprimer une livraison.
     */
    public function destroy($id): JsonResponse
    {
        Log::info("Début suppression de la livraison ID: " . $id);

        $livraison = $this->findLivraison($id);

        if (!$livraison) {
            Log::warning("Livraison introuvable pour suppression: " . $id);
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        }

        $livraison->delete();

        Log::info("Livraison supprimée avec succès: " . $id);

        return response()->json([
            'success' => true,
            'message' => 'Livraison supprimée avec succès',
        ], 200);
    }

    public function destroyByClient($id): JsonResponse
    {
        Log::info("Début suppression par client de la livraison ID: " . $id);

        $livraison = $this->findLivraison($id);

        if (!$livraison) {
            Log::warning("Livraison introuvable pour suppression par client: " . $id);
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        } else {
            if ($livraison->status !== 'en_attente') {
                Log::warning("Impossible de supprimer la livraison, statut incorrect: " . $livraison->status);
                return response()->json([
                    'success' => false,
                    'message' => 'La livraison ne peut être supprimée que si elle est en attente',
                ], 400);
            }
        }

        $livraison->delete();

        Log::info("Livraison supprimée par client avec succès: " . $id);

        return response()->json([
            'success' => true,
            'message' => 'Livraison supprimée avec succès',
        ], 200);
    }

    /**
     * Mettre à jour le status d'une livraison.
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        Log::info("Début mise à jour du statut pour livraison ID: " . $id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:en_attente,prise_en_charge_ramassage,ramasse,en_transit,prise_en_charge_livraison,livre,annule',
        ]);

        if ($validator->fails()) {
            Log::warning("Validation échouée pour mise à jour statut: " . json_encode($validator->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validatedData = $validator->validated();

        try {
            $livraison = $this->findLivraison($id);

            if (!$livraison) {
                Log::warning("Livraison introuvable pour mise à jour statut: " . $id);
                return response()->json([
                    'success' => false,
                    'message' => 'Livraison introuvable',
                ], 404);
            }

            Log::info("Mise à jour du statut de {$livraison->status} à {$validatedData['status']} pour la livraison " . $id);

            $livraison->update([
                'status' => $validatedData['status'],
            ]);

            Log::info("Statut mis à jour avec succès pour la livraison " . $id);

            return response()->json(
                $livraison,
                200
            );
        } catch (\Exception $e) {
            Log::error("Erreur lors de la mise à jour du statut pour la livraison {$id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'attribution du status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function trackByColisLabel($colis_label): JsonResponse
    {
        try {
            Log::info('Recherche du colis: ' . $colis_label);

            $colis = \App\Models\Colis::where('colis_label', $colis_label)->first();

            if (!$colis) {
                Log::warning('Colis non trouvé: ' . $colis_label);
                return response()->json([
                    'success' => false,
                    'message' => 'Colis introuvable avec ce code de suivi'
                ], 404);
            }

            Log::info('Colis trouvé, ID: ' . $colis->id);

            $demandeLivraison = \App\Models\DemandeLivraison::where('colis_id', $colis->id)->first();

            if (!$demandeLivraison) {
                Log::warning('Aucune demande de livraison pour le colis ID: ' . $colis->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune demande de livraison trouvée pour ce colis'
                ], 404);
            }

            Log::info('Demande de livraison trouvée, ID: ' . $demandeLivraison->id);

            $livraison = Livraison::where('demande_livraisons_id', $demandeLivraison->id)
                ->with([
                    'livreurDistributeur.user',
                    'livreurRamasseur.user',
                    'client.user',
                    'demandeLivraison.client.user',
                    'demandeLivraison.destinataire.user',
                    'demandeLivraison.colis',
                    'commentaires',
                    'bordereau'
                ])
                ->first();

            if (!$livraison) {
                Log::warning('Aucune livraison pour la demande ID: ' . $demandeLivraison->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune livraison en cours pour ce colis'
                ], 404);
            }

            Log::info('Livraison trouvée, ID: ' . $livraison->id . ', Status: ' . $livraison->status);

            return response()->json([
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison,
                'destinataire' => $livraison->demandeLivraison->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau,
                'colis' => $colis,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur trackByColisLabel: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche de la livraison',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Générer le HTML pour impression
     */
    public function generatePrintHTML($id): JsonResponse
    {
        Log::info("Début génération HTML pour impression - ID: " . $id);

        try {
            $livraison = $this->findLivraison($id);

            if (!$livraison) {
                Log::warning("Livraison introuvable pour génération HTML - ID: " . $id);
                return response()->json([
                    'success' => false,
                    'message' => 'Livraison introuvable',
                ], 404);
            }

            Log::info("Livraison trouvée pour HTML - ID: " . $livraison->id);

            $data = $this->preparePrintData($livraison);

            Log::info("Données préparées pour HTML - Livraison: " . $livraison->id);

            $html = View::make('pdf.bordereau', $data)->render();

            Log::info("HTML généré avec succès - Taille: " . strlen($html) . " caractères");

            return response()->json([
                'success' => true,
                'html' => $html,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur génération HTML - ID ' . $id . ': ' . $e->getMessage());
            Log::error('Stack trace génération HTML: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du HTML',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function saveBase64ToTempFile($base64Data, $prefix = 'img')
    {
        try {
            // Extraire les données base64
            if (preg_match('/data:image\/(\w+);base64,/', $base64Data, $matches)) {
                $type = $matches[1];
                $data = substr($base64Data, strpos($base64Data, ',') + 1);
                $data = base64_decode($data);

                $tempFile = tempnam(sys_get_temp_dir(), $prefix . '_' . uniqid());
                file_put_contents($tempFile, $data);

                return $tempFile;
            }
        } catch (\Exception $e) {
            Log::warning('Erreur création fichier temporaire: ' . $e->getMessage());
        }

        return null;
    }

    private function ensureDataUri($imageData)
    {
        // Si c'est déjà un data URI, le retourner
        if (strpos($imageData, 'data:image') === 0) {
            return $imageData;
        }

        // Si c'est un fichier, le convertir en data URI
        if (file_exists($imageData)) {
            try {
                $imageData = file_get_contents($imageData);
                $type = pathinfo($imageData, PATHINFO_EXTENSION);
                $base64 = base64_encode($imageData);

                return 'data:image/' . ($type === 'svg' ? 'svg+xml' : 'png') . ';base64,' . $base64;
            } catch (\Exception $e) {
                Log::warning('Erreur conversion fichier en data URI: ' . $e->getMessage());
            }
        }

        // Si c'est une URL, essayer de la télécharger
        if (filter_var($imageData, FILTER_VALIDATE_URL)) {
            try {
                $imageData = file_get_contents($imageData);
                $base64 = base64_encode($imageData);

                return 'data:image/png;base64,' . $base64;
            } catch (\Exception $e) {
                Log::warning('Erreur téléchargement URL: ' . $e->getMessage());
            }
        }

        return $imageData;
    }

    private function saveBase64ToTempFileDebug($base64Data, $prefix = 'img')
    {
        try {
            // Extraire les données base64
            if (preg_match('/data:image\/(\w+);base64,/', $base64Data, $matches)) {
                $type = $matches[1];
                $data = substr($base64Data, strpos($base64Data, ',') + 1);
                $data = base64_decode($data);

                $tempDir = storage_path('app/temp');
                if (!file_exists($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }

                $tempFile = $tempDir . '/' . $prefix . '_' . uniqid() . '.' . $type;
                file_put_contents($tempFile, $data);

                Log::info('Fichier temporaire créé: ' . $tempFile);

                return $tempFile;
            }
        } catch (\Exception $e) {
            Log::warning('Erreur création fichier temporaire: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Méthode de debug pour voir ce qui est généré
     */
    public function debugBordereau($id)
    {
        try {
            $livraison = $this->findLivraison($id);

            if (!$livraison) {
                return response()->json(['error' => 'Livraison non trouvée'], 404);
            }

            $data = $this->preparePrintData($livraison);

            // Afficher les infos sur les images
            $debugInfo = [
                'livraison_id' => $livraison->id,
                'qrCode_exists' => isset($data['qrCode']),
                'qrCode_type' => isset($data['qrCode']) ? substr($data['qrCode'], 0, 50) . '...' : 'N/A',
                'barcode_exists' => isset($data['barcode']),
                'barcode_type' => isset($data['barcode']) ? substr($data['barcode'], 0, 50) . '...' : 'N/A',
            ];

            // Générer un aperçu HTML
            $html = view('pdf.bordereau', $data)->render();

            return response()->json([
                'debug' => $debugInfo,
                'html_preview' => $html,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }



    /**
     * Générer et télécharger le PDF du bordereau
     */
    public function generateBordereauPDF($id)
    {
        Log::info("Début génération PDF - ID: " . $id);

        try {
            $livraison = $this->findLivraison($id);

            if (!$livraison) {
                return response()->json([
                    'success' => false,
                    'message' => 'Livraison introuvable',
                ], 404);
            }

            $data = $this->preparePrintData($livraison);

            $pdf = Pdf::loadView('pdf.bordereau', $data);

            // Configuration SIMPLE et FONCTIONNELLE
            $pdf->setPaper([0, 0, 380, 700], 'portrait');
            $pdf->setOption('enable_html5_parser', true);
            $pdf->setOption('enable_remote', true);
            $pdf->setOption('defaultFont', 'Arial');

            $fileName = 'bordereau_livraison_' . $livraison->id . '.pdf';

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            Log::error('Erreur génération PDF - ID ' . $id . ': ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du PDF',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function generateSimpleQRCode($livraison)
    {
        try {
            // Données minimales pour le QR Code
            $data = urlencode("ID:{$livraison->id}|PIN:{$livraison->code_pin}");
            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={$data}&format=png&margin=0";

            // Retourner directement l'URL - DomPDF peut la charger
            return $qrUrl;
        } catch (\Exception $e) {
            Log::warning("Erreur génération QR Code: " . $e->getMessage());

            // Fallback: URL de base
            return "https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=livraison&format=png";
        }
    }

    private function createSimpleQRCodeSVG($livraisonId)
    {
        $shortId = substr(md5($livraisonId), 0, 8);
        $svg = '<svg width="80" height="80" xmlns="http://www.w3.org/2000/svg">
        <rect width="80" height="80" fill="#f8fafc" stroke="#000" stroke-width="1"/>
        <text x="40" y="40" text-anchor="middle" dominant-baseline="central"
              font-family="Arial" font-size="10" fill="#000">QR</text>
        <text x="40" y="55" text-anchor="middle" font-family="Arial"
              font-size="6" fill="#666">' . $shortId . '</text>
    </svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }



    /**
     * Préparer les données pour l'impression
     */
    private function preparePrintData($livraison): array
    {
        try {




            $livraison->load([
                'demandeLivraison.client.user',
                'demandeLivraison.destinataire.user',
                'demandeLivraison.colis',
                'livreurRamasseur.user',
                'livreurDistributeur.user',
            ]);

            $demande = $livraison->demandeLivraison;
            $colis = $demande->colis ?? null;
            $client = $demande->client->user ?? null;
            $destinataire = $demande->destinataire->user ?? null;
            $livreurRamasseur = $livraison->livreurRamasseur->user ?? null;
            $livreurDistributeur = $livraison->livreurDistributeur->user ?? null;

            // Nettoyer les caractères
            if ($client) {
                $client->prenom = $this->cleanText($client->prenom ?? '');
                $client->nom = $this->cleanText($client->nom ?? '');
                $client->telephone = $this->cleanText($client->telephone ?? '');
            }

            if ($destinataire) {
                $destinataire->prenom = $this->cleanText($destinataire->prenom ?? '');
                $destinataire->nom = $this->cleanText($destinataire->nom ?? '');
                $destinataire->telephone = $this->cleanText($destinataire->telephone ?? '');
            }

            if ($demande) {
                $demande->addresse_depot = $this->cleanText($demande->addresse_depot ?? '');
                $demande->addresse_delivery = $this->cleanText($demande->addresse_delivery ?? '');
            }



            // GÉNÉRATION DES IMAGES SIMPLES
            $qrCode = $this->generateSimpleQRCode($livraison);
            $barcodeValue = $colis->colis_label ?? 'COLIS-' . $livraison->id;
            $barcode = $this->generateSimpleBarcode($barcodeValue);

            $printDate = now()->locale('fr_FR')->isoFormat('DD/MM/YYYY');

            $statusLabels = [
                'en_attente' => 'En attente',
                'prise_en_charge_ramassage' => 'Prise en charge',
                'ramasse' => 'Ramasse',
                'en_transit' => 'En transit',
                'prise_en_charge_livraison' => 'En livraison',
                'livre' => 'Livré',
                'annule' => 'Annulé',
            ];

            $statusLabel = $statusLabels[$livraison->status] ?? str_replace('_', ' ', $livraison->status);

            return [
                'livraison' => $livraison,
                'demande' => $demande,
                'colis' => $colis,
                'client' => $client,
                'destinataire' => $destinataire,
                'livreurRamasseur' => $livreurRamasseur,
                'livreurDistributeur' => $livreurDistributeur,
                'qrCode' => $qrCode,
                'barcode' => $barcode,
                'colisLabel' => $barcodeValue,
                'printDate' => $printDate,
                'statusLabel' => $statusLabel,
            ];
        } catch (\Exception $e) {
            Log::error("Erreur préparation données: " . $e->getMessage());
            throw $e;
        }
    }

    private function cleanText($text)
    {
        if (empty($text)) {
            return '';
        }

        // 1. Décoder TOUTES les entités HTML (c'est la clé !)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 2. Remplacer les caractères problématiques spécifiques
        $text = str_replace(
            ['&comma;', '&#44;', '&amp;', '&quot;', '&lt;', '&gt;', '&nbsp;'],
            [',', ',', '&', '"', '<', '>', ' '],
            $text
        );

        // 3. Pour PDF, garder les accents et caractères spéciaux INTACTS
        // Ne pas convertir en entités HTML !

        // 4. Juste trimmer et retourner
        return trim($text);
    }

    private function cleanAllTexts($data)
    {
        // Nettoyer les adresses
        if (isset($data['demande'])) {
            $data['demande']->addresse_depot = $this->cleanText($data['demande']->addresse_depot ?? '');
            $data['demande']->addresse_delivery = $this->cleanText($data['demande']->addresse_delivery ?? '');
        }

        return $data;
    }


    private function generateSimpleBarcodeImage($value)
    {
        try {
            // Si GD est disponible, créer une image simple
            if (function_exists('imagecreatetruecolor')) {
                return $this->createBarcodeWithGD($value);
            }

            // Sinon, créer un SVG
            return $this->createBarcodeSVG($value);
        } catch (\Exception $e) {
            Log::warning("Erreur génération code-barres: " . $e->getMessage());
            return $this->createBarcodeSVG($value);
        }
    }

    private function createBarcodeSVG($value)
    {
        $svg = '<svg width="200" height="50" xmlns="http://www.w3.org/2000/svg">
        <rect width="200" height="50" fill="#fff" stroke="#000" stroke-width="1"/>
        <text x="100" y="25" text-anchor="middle" dominant-baseline="central"
              font-family="Arial" font-size="12" fill="#000" font-weight="bold">
              CODE: ' . htmlspecialchars($value) . '
        </text>
        <text x="100" y="40" text-anchor="middle" font-family="Arial"
              font-size="8" fill="#666">Code-barres</text>
    </svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }


    private function createBarcodeWithGD($value)
    {
        $width = 200;
        $height = 50;

        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        imagefill($image, 0, 0, $white);

        // Dessiner un motif simple de barres
        $x = 10;
        for ($i = 0; $i < strlen($value); $i++) {
            $charCode = ord($value[$i]);
            $barHeight = ($charCode % 30) + 10;

            imagefilledrectangle($image, $x, 10, $x + 3, 10 + $barHeight, $black);
            $x += 5;
        }

        // Ajouter le texte
        imagestring($image, 2, 50, $height - 20, $value, $black);

        // Capturer l'output
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,' . base64_encode($imageData);
    }

    /**
     * Générer un QR Code en base64
     */
    private function generateQRCode(string $data): string
    {
        try {
            Log::info("Tentative de génération de QR Code local...");

            if (class_exists('BaconQrCode\Writer')) {
                Log::info("Bibliothèque BaconQrCode disponible");

                $renderer = new ImageRenderer(
                    new RendererStyle(90),
                    new ImagickImageBackEnd()
                );

                $writer = new Writer($renderer);
                $qrCode = $writer->writeString($data);

                $base64 = 'data:image/png;base64,' . base64_encode($qrCode);
                Log::info("QR Code généré localement avec succès - Taille: " . strlen($base64) . " caractères");

                return $base64;
            } else {
                Log::warning("Bibliothèque BaconQrCode non disponible");
            }
        } catch (\Exception $e) {
            Log::warning('Erreur génération QR Code local: ' . $e->getMessage());
        }

        // Fallback: utiliser un service en ligne
        Log::info("Utilisation du service en ligne pour le QR Code");
        $encodedData = urlencode($data);
        return "https://api.qrserver.com/v1/create-qr-code/?size=90x90&data={$encodedData}&format=png&margin=1";
    }

    /**
     * Générer un code-barres en base64
     */
    private function generateBarcode(string $value): string
    {
        try {
            Log::info("Tentative de génération de code-barres local...");

            if (class_exists('Picqer\Barcode\BarcodeGeneratorPNG')) {
                Log::info("Bibliothèque Picqer Barcode disponible");

                $generator = new BarcodeGeneratorPNG();
                $barcode = $generator->getBarcode($value, $generator::TYPE_CODE_128, 2, 50);

                $base64 = 'data:image/png;base64,' . base64_encode($barcode);
                Log::info("Code-barres généré localement avec succès - Taille: " . strlen($base64) . " caractères");

                return $base64;
            } else {
                Log::warning("Bibliothèque Picqer Barcode non disponible");
            }
        } catch (\Exception $e) {
            Log::warning('Erreur génération code-barres local: ' . $e->getMessage());
        }

        Log::info("Utilisation de la méthode simplifiée pour le code-barres");
        return $this->generateSimpleBarcode($value);
    }

    /**
     * Générer un code-barres simplifié (fallback)
     */
    private function generateSimpleBarcode($value)
    {
        try {
            // Utiliser un service de code-barres en ligne
            $encodedValue = urlencode($value);
            return "https://barcode.tec-it.com/barcode.ashx?data={$encodedValue}&code=Code128&dpi=96&dataseparator=";
        } catch (\Exception $e) {
            Log::warning("Erreur génération code-barres: " . $e->getMessage());

            // Fallback: URL de base
            return "https://barcode.tec-it.com/barcode.ashx?data=123456&code=Code128";
        }
    }

    /**
     * Méthode helper pour trouver une livraison par ID (UUID ou numérique)
     */
    private function findLivraison($id)
    {
        if (Str::isUuid($id)) {
            return Livraison::where('id', $id)->first();
        }

        return Livraison::find($id);
    }

    ################__NOUVELLES FONCTIONS__#####################

    /**
     * Récupérer les livraisons en attente.
     */
    public function livraisonsEnAttente(): JsonResponse
    {
        Log::info("Récupération des livraisons en attente");

        $livraisons = Livraison::where('status', 'en_attente')->get();

        $datas = [];
        foreach ($livraisons as $livraison) {
            $datas[] = [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'created_at' => $livraison->created_at,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison->load([
                    'client',
                    'destinataire',
                    'colis',
                ]),
                'destinataire' => $livraison->demandeLivraison->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
            ];
        }

        return response()->json($datas, 200);
    }

    /**
     * Récupérer les livraisons terminées.
     */
    public function livraisonsTerminees(): JsonResponse
    {
        Log::info("Récupération des livraisons terminées");

        $livraisons = Livraison::where('status', 'livre')->get();

        $datas = [];
        foreach ($livraisons as $livraison) {
            $datas[] = [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'created_at' => $livraison->created_at,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison->load([
                    'client',
                    'destinataire',
                    'colis',
                ]),
                'destinataire' => $livraison->demandeLivraison->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
            ];
        }

        return response()->json($datas, 200);
    }

    /**
     * Récupérer les livraisons annulées.
     */
    public function livraisonsAnnulees(): JsonResponse
    {
        Log::info("Récupération des livraisons annulées");

        $livraisons = Livraison::where('status', 'annule')->get();

        $datas = [];
        foreach ($livraisons as $livraison) {
            $datas[] = [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'created_at' => $livraison->created_at,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison->load([
                    'client',
                    'destinataire',
                    'colis',
                ]),
                'destinataire' => $livraison->demandeLivraison->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
            ];
        }

        return response()->json($datas, 200);
    }

    /**
     * Statistiques générales (admin).
     */
    public function statistiquesGenerales(): JsonResponse
    {
        Log::info("Récupération des statistiques générales");

        try {
            // Totaux
            $totalLivraisons = Livraison::count();
            $totalEnAttente = Livraison::where('status', 'en_attente')->count();
            $totalEnCours = Livraison::whereNotIn('status', ['en_attente', 'livre', 'annule'])->count();
            $totalTerminees = Livraison::where('status', 'livre')->count();
            $totalAnnulees = Livraison::where('status', 'annule')->count();

            // Par statut détaillé
            $parStatut = [
                'en_attente' => $totalEnAttente,
                'prise_en_charge_ramassage' => Livraison::where('status', 'prise_en_charge_ramassage')->count(),
                'ramasse' => Livraison::where('status', 'ramasse')->count(),
                'en_transit' => Livraison::where('status', 'en_transit')->count(),
                'prise_en_charge_livraison' => Livraison::where('status', 'prise_en_charge_livraison')->count(),
                'livre' => $totalTerminees,
                'annule' => $totalAnnulees,
            ];

            // Évolution mensuelle (derniers 6 mois)
            $evolution = [];
            for ($i = 5; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $month = $date->format('Y-m');
                $monthLabel = $date->locale('fr_FR')->isoFormat('MMM YYYY');

                $evolution[$monthLabel] = [
                    'total' => Livraison::whereYear('created_at', $date->year)
                        ->whereMonth('created_at', $date->month)
                        ->count(),
                    'terminees' => Livraison::where('status', 'livre')
                        ->whereYear('created_at', $date->year)
                        ->whereMonth('created_at', $date->month)
                        ->count(),
                ];
            }

            // Taux de réussite
            $tauxReussite = $totalLivraisons > 0
                ? round(($totalTerminees / $totalLivraisons) * 100, 2)
                : 0;

            // Taux d'annulation
            $tauxAnnulation = $totalLivraisons > 0
                ? round(($totalAnnulees / $totalLivraisons) * 100, 2)
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'totaux' => [
                        'total' => $totalLivraisons,
                        'en_attente' => $totalEnAttente,
                        'en_cours' => $totalEnCours,
                        'terminees' => $totalTerminees,
                        'annulees' => $totalAnnulees,
                    ],
                    'par_statut' => $parStatut,
                    'taux' => [
                        'reussite' => $tauxReussite,
                        'annulation' => $tauxAnnulation,
                        'en_cours' => $totalLivraisons > 0
                            ? round(($totalEnCours / $totalLivraisons) * 100, 2)
                            : 0,
                    ],
                    'evolution' => $evolution,
                    'dernieres_24h' => Livraison::where('created_at', '>=', Carbon::now()->subDay())
                        ->count(),
                    'derniere_semaine' => Livraison::where('created_at', '>=', Carbon::now()->subWeek())
                        ->count(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur statistiques générales: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
