<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Livraison;
use App\Models\DemandeLivraison;
use App\Models\Livreur;
use App\Models\LivreurAssignation;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LivraisonController extends Controller
{
    /**
     * Middleware pour vérifier la wilaya
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

            // Injecter la wilaya du gestionnaire dans la requête
            $request->merge(['gestionnaire_wilaya' => $gestionnaire->wilaya_id]);

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

        return $wilayas[$code] ?? null;
    }

    /**
     * Lister les livraisons de la wilaya
     */
    public function index(Request $request): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $wilayaNom = $this->getWilayaNameFromCode($wilayaCode);

        // Construire la requête
        $query = Livraison::with([
            'demandeLivraison.client.user',
            'demandeLivraison.destinataire.user',
            'demandeLivraison.colis',
            'livreurDistributeur.user',
            'livreurRamasseur.user',
            'commentaires'
        ]);

        // Filtrer par wilaya (soit par code, soit par nom)
        $query->whereHas('demandeLivraison', function ($q) use ($wilayaCode, $wilayaNom) {
            $q->where(function ($subQuery) use ($wilayaCode, $wilayaNom) {
                // Filtrer par code (si le champ contient un code)
                $subQuery->where('wilaya', $wilayaCode);

                // Filtrer par nom (si le champ contient un nom)
                if ($wilayaNom) {
                    $subQuery->orWhere('wilaya', 'like', '%' . $wilayaNom . '%');
                }

                // Filtrer par wilaya_depot aussi (au cas où)
                $subQuery->orWhere('wilaya_depot', $wilayaCode);
                if ($wilayaNom) {
                    $subQuery->orWhere('wilaya_depot', 'like', '%' . $wilayaNom . '%');
                }
            });
        });

        // Paginer les résultats
        $livraisons = $query->orderBy('created_at', 'desc')->paginate(20);

        // Formater les données comme dans LivraisonController
        $formattedData = $livraisons->map(function ($livraison) {
            return [
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
                'demande_livraison' => $livraison->demandeLivraison ? [
                    'id' => $livraison->demandeLivraison->id,
                    'client_id' => $livraison->demandeLivraison->client_id,
                    'destinataire_id' => $livraison->demandeLivraison->destinataire_id,
                    'colis_id' => $livraison->demandeLivraison->colis_id,
                    'addresse_depot' => $livraison->demandeLivraison->addresse_depot,
                    'addresse_delivery' => $livraison->demandeLivraison->addresse_delivery,
                    'info_additionnel' => $livraison->demandeLivraison->info_additionnel,
                    'prix' => $livraison->demandeLivraison->prix,
                    'wilaya' => $livraison->demandeLivraison->wilaya,
                    'commune' => $livraison->demandeLivraison->commune,
                    'wilaya_depot' => $livraison->demandeLivraison->wilaya_depot,
                    'commune_depot' => $livraison->demandeLivraison->commune_depot,
                    'colis' => $livraison->demandeLivraison->colis,
                ] : null,
                'destinataire' => $livraison->demandeLivraison?->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedData,
            'pagination' => [
                'current_page' => $livraisons->currentPage(),
                'last_page' => $livraisons->lastPage(),
                'per_page' => $livraisons->perPage(),
                'total' => $livraisons->total(),
            ]
        ], 200);
    }

    /**
     * Voir une livraison spécifique
     */
    public function show(Request $request, $id): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $wilayaNom = $this->getWilayaNameFromCode($wilayaCode);

        $livraison = Livraison::with([
            'demandeLivraison.client.user',
            'demandeLivraison.destinataire.user',
            'demandeLivraison.colis',
            'livreurDistributeur.user',
            'livreurRamasseur.user',
            'commentaires'
        ])
        ->whereHas('demandeLivraison', function ($q) use ($wilayaCode, $wilayaNom) {
            $q->where(function ($subQuery) use ($wilayaCode, $wilayaNom) {
                $subQuery->where('wilaya', $wilayaCode)
                         ->orWhere('wilaya', 'like', '%' . $wilayaNom . '%')
                         ->orWhere('wilaya_depot', $wilayaCode)
                         ->orWhere('wilaya_depot', 'like', '%' . $wilayaNom . '%');
            });
        })
        ->find($id);

        if (!$livraison) {
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable dans votre wilaya'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
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
                'demande_livraison' => $livraison->demandeLivraison,
                'destinataire' => $livraison->demandeLivraison?->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
            ]
        ], 200);
    }

    /**
     * Rechercher des livraisons
     */
    public function search(Request $request): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $wilayaNom = $this->getWilayaNameFromCode($wilayaCode);
        $term = $request->query('q', '');

        $query = Livraison::with([
            'demandeLivraison.client.user',
            'demandeLivraison.destinataire.user',
            'demandeLivraison.colis',
            'livreurDistributeur.user',
            'livreurRamasseur.user',
            'commentaires'
        ]);

        // Filtrer par wilaya
        $query->whereHas('demandeLivraison', function ($q) use ($wilayaCode, $wilayaNom) {
            $q->where(function ($subQuery) use ($wilayaCode, $wilayaNom) {
                $subQuery->where('wilaya', $wilayaCode)
                         ->orWhere('wilaya', 'like', '%' . $wilayaNom . '%')
                         ->orWhere('wilaya_depot', $wilayaCode)
                         ->orWhere('wilaya_depot', 'like', '%' . $wilayaNom . '%');
            });
        });

        // Filtrer par terme de recherche
        if (!empty($term)) {
            $query->where(function ($q) use ($term) {
                $q->where('code_pin', 'like', "%{$term}%")
                  ->orWhereHas('demandeLivraison.client.user', function ($u) use ($term) {
                      $u->where('nom', 'like', "%{$term}%")
                        ->orWhere('prenom', 'like', "%{$term}%")
                        ->orWhere('telephone', 'like', "%{$term}%");
                  })
                  ->orWhereHas('demandeLivraison.destinataire.user', function ($u) use ($term) {
                      $u->where('nom', 'like', "%{$term}%")
                        ->orWhere('prenom', 'like', "%{$term}%")
                        ->orWhere('telephone', 'like', "%{$term}%");
                  })
                  ->orWhereHas('demandeLivraison.colis', function ($c) use ($term) {
                      $c->where('colis_label', 'like', "%{$term}%");
                  });
            });
        }

        $livraisons = $query->orderBy('created_at', 'desc')->paginate(20);

        $formattedData = $livraisons->map(function ($livraison) {
            return [
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
                'client' => $livraison->client?->user,
                'destinataire' => $livraison->demandeLivraison?->destinataire?->user,
                'colis' => $livraison->demandeLivraison?->colis,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedData,
            'pagination' => [
                'current_page' => $livraisons->currentPage(),
                'last_page' => $livraisons->lastPage(),
                'per_page' => $livraisons->perPage(),
                'total' => $livraisons->total(),
            ]
        ], 200);
    }

    /**
     * Filtrer les livraisons par statut
     */
    public function byStatus(Request $request, $status): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $wilayaNom = $this->getWilayaNameFromCode($wilayaCode);

        $validStatuses = ['en_attente', 'prise_en_charge_ramassage', 'ramasse',
                          'en_transit', 'prise_en_charge_livraison', 'livre', 'annule'];

        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Statut invalide'
            ], 422);
        }

        $query = Livraison::with([
            'demandeLivraison.client.user',
            'demandeLivraison.destinataire.user',
            'demandeLivraison.colis',
            'livreurDistributeur.user',
            'livreurRamasseur.user'
        ])
        ->whereHas('demandeLivraison', function ($q) use ($wilayaCode, $wilayaNom) {
            $q->where(function ($subQuery) use ($wilayaCode, $wilayaNom) {
                $subQuery->where('wilaya', $wilayaCode)
                         ->orWhere('wilaya', 'like', '%' . $wilayaNom . '%')
                         ->orWhere('wilaya_depot', $wilayaCode)
                         ->orWhere('wilaya_depot', 'like', '%' . $wilayaNom . '%');
            });
        })
        ->where('status', $status);

        $livraisons = $query->orderBy('created_at', 'desc')->paginate(20);

        $formattedData = $livraisons->map(function ($livraison) {
            return [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'created_at' => $livraison->created_at,
                'status' => $livraison->status,
                'code_pin' => $livraison->code_pin,
                'client' => $livraison->client?->user,
                'destinataire' => $livraison->demandeLivraison?->destinataire?->user,
                'colis' => $livraison->demandeLivraison?->colis,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedData,
            'pagination' => [
                'current_page' => $livraisons->currentPage(),
                'last_page' => $livraisons->lastPage(),
                'per_page' => $livraisons->perPage(),
                'total' => $livraisons->total(),
            ]
        ], 200);
    }

    /**
     * Mettre à jour le statut d'une livraison
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $wilayaNom = $this->getWilayaNameFromCode($wilayaCode);

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:prise_en_charge_ramassage,ramasse,en_transit,prise_en_charge_livraison,livre,annule'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $livraison = Livraison::whereHas('demandeLivraison', function ($q) use ($wilayaCode, $wilayaNom) {
            $q->where(function ($subQuery) use ($wilayaCode, $wilayaNom) {
                $subQuery->where('wilaya', $wilayaCode)
                         ->orWhere('wilaya', 'like', '%' . $wilayaNom . '%')
                         ->orWhere('wilaya_depot', $wilayaCode)
                         ->orWhere('wilaya_depot', 'like', '%' . $wilayaNom . '%');
            });
        })->find($id);

        if (!$livraison) {
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable dans votre wilaya'
            ], 404);
        }

        $livraison->update([
            'status' => $request->status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour avec succès',
            'data' => [
                'id' => $livraison->id,
                'status' => $livraison->status
            ]
        ], 200);
    }

   /**
 * ⭐ NOUVELLE MÉTHODE : Attribuer un livreur à une livraison (pour gestionnaire)
 */
public function assignLivreur(Request $request, $id): JsonResponse
{
    Log::info("Manager - Début assignation livreur pour livraison ID: " . $id);

    // Validation des données
    $validator = Validator::make($request->all(), [
        'livreur_id' => 'required|string|exists:livreurs,id',
        'type' => 'required|integer|in:1,2',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur de validation',
            'errors' => $validator->errors(),
        ], 422);
    }

    $validatedData = $validator->validated();
    $type = $validatedData['type'];
    $livreurId = $validatedData['livreur_id'];

    // Récupérer le gestionnaire connecté et sa wilaya
    $user = Auth::user();
    $gestionnaire = $user->gestionnaire;

    if (!$gestionnaire) {
        return response()->json([
            'success' => false,
            'message' => 'Profil gestionnaire introuvable',
        ], 403);
    }

    $wilayaGestionnaire = $gestionnaire->wilaya_id;
    Log::info("Manager wilaya: " . $wilayaGestionnaire);

    // Récupérer la livraison avec sa demande
    $livraison = Livraison::with(['demandeLivraison'])->find($id);

    if (!$livraison) {
        return response()->json([
            'success' => false,
            'message' => 'Livraison introuvable',
        ], 404);
    }

    $demande = $livraison->demandeLivraison;

    // Récupérer les wilayas de départ et d'arrivée
    $wilayaDepartRaw = $demande->wilaya_depot;
    $wilayaArriveeRaw = $demande->wilaya;

    // Convertir en codes pour comparaison
    $wilayaDepartCode = $this->getWilayaCodeFromName($wilayaDepartRaw);
    $wilayaArriveeCode = $this->getWilayaCodeFromName($wilayaArriveeRaw);

    Log::info("Normalisation wilayas", [
        'depart_raw' => $wilayaDepartRaw,
        'depart_code' => $wilayaDepartCode,
        'arrivee_raw' => $wilayaArriveeRaw,
        'arrivee_code' => $wilayaArriveeCode,
        'gestionnaire_code' => $wilayaGestionnaire
    ]);

    // Vérifier si la livraison concerne la wilaya du gestionnaire (comparer les codes)
    $concerneWilaya = ($wilayaDepartCode == $wilayaGestionnaire) || ($wilayaArriveeCode == $wilayaGestionnaire);

    if (!$concerneWilaya) {
        Log::warning("Manager tentant d'assigner un livreur à une livraison hors de sa wilaya", [
            'gestionnaire_wilaya' => $wilayaGestionnaire,
            'wilaya_depart_code' => $wilayaDepartCode,
            'wilaya_arrivee_code' => $wilayaArriveeCode,
            'wilaya_depart_raw' => $wilayaDepartRaw,
            'wilaya_arrivee_raw' => $wilayaArriveeRaw
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Vous ne pouvez pas assigner de livreur à cette livraison car elle ne concerne pas votre wilaya.',
        ], 403);
    }

    // VÉRIFICATION : Le livreur doit être disponible pour ce gestionnaire
    $livreur = Livreur::with('user')->find($livreurId);

    if (!$livreur) {
        return response()->json([
            'success' => false,
            'message' => 'Livreur introuvable',
        ], 404);
    }

    // Vérifier si le livreur est disponible pour ce gestionnaire
    $estDisponible = $this->verifierDisponibiliteLivreur($livreur, $gestionnaire);

    if (!$estDisponible) {
        return response()->json([
            'success' => false,
            'message' => 'Ce livreur n\'est pas disponible dans votre wilaya. Seuls les livreurs de votre wilaya ou ceux qui vous sont assignés peuvent être sélectionnés.',
        ], 403);
    }

    // Vérifier les conditions selon le type de livreur
    try {
        DB::beginTransaction();

        if ($type == 2) {
            // Type 2 : Distributeur
            if ($livraison->status !== 'en_transit') {
                return response()->json([
                    'success' => false,
                    'message' => 'Le distributeur ne peut être assigné que lorsque la livraison est en transit',
                ], 400);
            }

            $livraison->update([
                'livreur_distributeur_id' => $livreurId,
            ]);

            Log::info("Manager - Distributeur assigné", [
                'livraison_id' => $id,
                'livreur_id' => $livreurId,
                'gestionnaire_id' => $gestionnaire->id,
            ]);

        } elseif ($type == 1) {
            // Type 1 : Ramasseur
            if ($livraison->status === 'ramasse') {
                return response()->json([
                    'success' => false,
                    'message' => 'Le colis a déjà été ramassé, vous ne pouvez plus attribuer un autre ramasseur',
                ], 400);
            }

            $livraison->update([
                'livreur_ramasseur_id' => $livreurId,
                'status' => 'prise_en_charge_ramassage'
            ]);

            Log::info("Manager - Ramasseur assigné", [
                'livraison_id' => $id,
                'livreur_id' => $livreurId,
                'gestionnaire_id' => $gestionnaire->id,
                'nouveau_statut' => 'prise_en_charge_ramassage',
            ]);

        } else {
            return response()->json([
                'success' => false,
                'message' => 'Type de livreur invalide',
            ], 400);
        }

        DB::commit();

        // Recharger la livraison avec les relations
        $livraison->load([
            'livreurRamasseur.user',
            'livreurDistributeur.user',
            'demandeLivraison',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Livreur attribué avec succès',
            'data' => $livraison,
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Erreur lors de l'assignation du livreur par manager: " . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de l\'attribution du livreur',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * ⭐ NOUVELLE MÉTHODE : Vérifier si un livreur est disponible pour un gestionnaire
     *
     * @param Livreur $livreur
     * @param Gestionnaire $gestionnaire
     * @return bool
     */
    private function verifierDisponibiliteLivreur($livreur, $gestionnaire): bool
    {
        // Cas 1 : Le livreur est natif de la wilaya du gestionnaire
        if ($livreur->wilaya_id == $gestionnaire->wilaya_id) {
            Log::info("Livreur natif de la wilaya", [
                'livreur_id' => $livreur->id,
                'wilaya_livreur' => $livreur->wilaya_id,
                'wilaya_gestionnaire' => $gestionnaire->wilaya_id,
            ]);
            return true;
        }

        // Cas 2 : Le livreur a été assigné à ce gestionnaire par l'admin
        $assignationActive = LivreurAssignation::where('livreur_id', $livreur->id)
            ->where('gestionnaire_id', $gestionnaire->id)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('date_fin')
                      ->orWhere('date_fin', '>=', now());
            })
            ->exists();

        if ($assignationActive) {
            Log::info("Livreur assigné à ce gestionnaire", [
                'livreur_id' => $livreur->id,
                'gestionnaire_id' => $gestionnaire->id,
            ]);
            return true;
        }

        Log::warning("Livreur non disponible pour ce gestionnaire", [
            'livreur_id' => $livreur->id,
            'wilaya_livreur' => $livreur->wilaya_id,
            'gestionnaire_wilaya' => $gestionnaire->wilaya_id,
        ]);

        return false;
    }

   /**
 * ⭐ NOUVELLE MÉTHODE : Récupérer les livreurs disponibles pour le gestionnaire
 *
 * @param Request $request
 * @return JsonResponse
 */
public function getLivreursDisponibles(Request $request): JsonResponse
{
    try {
        $user = Auth::user();
        $gestionnaire = $user->gestionnaire;

        if (!$gestionnaire) {
            return response()->json([
                'success' => false,
                'message' => 'Profil gestionnaire introuvable',
            ], 403);
        }

        $wilayaGestionnaire = $gestionnaire->wilaya_id;

        // 1. Livreurs natifs de la wilaya (TOUS les livreurs, quel que soit leur type)
        $livreursNatifs = Livreur::with('user')
            ->where('wilaya_id', $wilayaGestionnaire)
            ->where('desactiver', false)
            ->get();

        // 2. Livreurs assignés à ce gestionnaire par l'admin (TOUS les livreurs)
        $livreursAssignes = Livreur::with('user')
            ->whereHas('assignations', function ($query) use ($gestionnaire) {
                $query->where('gestionnaire_id', $gestionnaire->id)
                      ->where('status', 'active')
                      ->where(function ($q) {
                          $q->whereNull('date_fin')
                            ->orWhere('date_fin', '>=', now());
                      });
            })
            ->where('desactiver', false)
            ->get();

        // Fusionner et dédupliquer
        $tousLivreurs = $livreursNatifs->merge($livreursAssignes)->unique('id');

        // ⚠️ PLUS DE FILTRE PAR TYPE - TOUS LES LIVREURS SONT AFFICHÉS

        // Formater pour le select
        $formattedLivreurs = $tousLivreurs->map(function ($livreur) use ($wilayaGestionnaire) {
            return [
                'value' => $livreur->id,
                'label' => trim(($livreur->user->prenom ?? '') . ' ' . ($livreur->user->nom ?? '')),
                'telephone' => $livreur->user->telephone ?? '',
                'type' => $livreur->type,
                'wilaya_id' => $livreur->wilaya_id,
                'origine' => $livreur->wilaya_id == $wilayaGestionnaire ? 'natif' : 'assigne',
            ];
        })->values();

        Log::info("Livreurs disponibles pour gestionnaire (sans filtre type)", [
            'gestionnaire_id' => $gestionnaire->id,
            'wilaya' => $wilayaGestionnaire,
            'total' => $formattedLivreurs->count(),
            'natifs' => $livreursNatifs->count(),
            'assignes' => $livreursAssignes->count(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $formattedLivreurs,
            'meta' => [
                'wilaya_gestionnaire' => $wilayaGestionnaire,
                'total' => $formattedLivreurs->count(),
                'natifs' => $livreursNatifs->count(),
                'assignes' => $livreursAssignes->count(),
            ],
        ], 200);

    } catch (\Exception $e) {
        Log::error('Erreur getLivreursDisponibles manager: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des livreurs disponibles',
        ], 500);
    }
}

/**
 * Convertir un nom de wilaya en code
 */
private function getWilayaCodeFromName($wilayaName): ?string
{
    $wilayaMapping = [
        'Adrar' => '01', 'Chlef' => '02', 'Laghouat' => '03',
        'Oum El Bouaghi' => '04', 'Batna' => '05', 'Béjaïa' => '06',
        'Biskra' => '07', 'Béchar' => '08', 'Blida' => '09',
        'Bouira' => '10', 'Tamanrasset' => '11', 'Tébessa' => '12',
        'Tlemcen' => '13', 'Tiaret' => '14', 'Tizi Ouzou' => '15',
        'Alger' => '16', 'Djelfa' => '17', 'Jijel' => '18',
        'Sétif' => '19', 'Saïda' => '20', 'Skikda' => '21',
        'Sidi Bel Abbès' => '22', 'Annaba' => '23', 'Guelma' => '24',
        'Constantine' => '25', 'Médéa' => '26', 'Mostaganem' => '27',
        "M'Sila" => '28', 'Mascara' => '29', 'Ouargla' => '30',
        'Oran' => '31', 'El Bayadh' => '32', 'Illizi' => '33',
        'Bordj Bou Arréridj' => '34', 'Boumerdès' => '35',
        'El Tarf' => '36', 'Tindouf' => '37', 'Tissemsilt' => '38',
        'El Oued' => '39', 'Khenchela' => '40', 'Souk Ahras' => '41',
        'Tipaza' => '42', 'Mila' => '43', 'Aïn Defla' => '44',
        'Naâma' => '45', 'Aïn Témouchent' => '46', 'Ghardaïa' => '47',
        'Relizane' => '48', 'Timimoun' => '49', 'Bordj Badji Mokhtar' => '50',
        'Ouled Djellal' => '51', 'Béni Abbès' => '52', 'In Salah' => '53',
        'In Guezzam' => '54', 'Touggourt' => '55', 'Djanet' => '56',
        "El M'Ghair" => '57', 'El Meniaa' => '58'
    ];

    // Nettoyer la valeur
    $wilayaName = trim($wilayaName);

    // Si c'est déjà un code (ex: "16"), le retourner
    if (isset($wilayaMapping[$wilayaName])) {
        return $wilayaMapping[$wilayaName];
    }

    // Chercher par nom
    foreach ($wilayaMapping as $nom => $code) {
        if (strcasecmp($nom, $wilayaName) === 0) {
            return $code;
        }
    }

    return null;
}




}
