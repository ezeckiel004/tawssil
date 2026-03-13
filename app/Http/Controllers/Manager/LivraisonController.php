<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Livraison;
use App\Models\DemandeLivraison;
use Illuminate\Support\Facades\Validator;

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

    // ⚠️ PAS de méthode destroy() - Le gestionnaire n'a pas le droit de suppression
}