<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Livreur;
use Illuminate\Support\Facades\Validator;

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

        return $wilayas[$code] ?? $code;
    }

    /**
     * Lister les livreurs de la wilaya
     */
    public function index(Request $request): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $wilayaNom = $this->getWilayaNameFromCode($wilayaCode);
        
        try {
            // ✅ FILTRAGE PAR WILAYA_ID DIRECTEMENT SUR LA TABLE LIVREURS
            $livreurs = Livreur::with(['user', 'demandeAdhesion'])
                ->where('wilaya_id', $wilayaCode)
                ->orderBy('created_at', 'desc')
                ->get();

            // Statistiques pour le gestionnaire
            $totalLivreurs = $livreurs->count();
            $actifs = $livreurs->where('desactiver', false)->count();
            $inactifs = $livreurs->where('desactiver', true)->count();
            $distributeurs = $livreurs->where('type', 'distributeur')->count();
            $ramasseurs = $livreurs->where('type', 'ramasseur')->count();

            return response()->json([
                'success' => true,
                'data' => $livreurs,
                'stats' => [
                    'total' => $totalLivreurs,
                    'actifs' => $actifs,
                    'inactifs' => $inactifs,
                    'distributeurs' => $distributeurs,
                    'ramasseurs' => $ramasseurs,
                    'wilaya' => [
                        'code' => $wilayaCode,
                        'nom' => $wilayaNom
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
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
        
        try {
            $livreur = Livreur::with(['user', 'demandeAdhesion'])
                ->where('wilaya_id', $wilayaCode)
                ->find($id);

            if (!$livreur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Livreur introuvable dans votre wilaya'
                ], 404);
            }

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
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'livreur' => $livreur,
                    'stats' => $stats
                ]
            ], 200);

        } catch (\Exception $e) {
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
            $livreur = Livreur::where('wilaya_id', $wilayaCode)->find($id);

            if (!$livreur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Livreur introuvable dans votre wilaya'
                ], 404);
            }

            // Si 'desactiver' est fourni dans la requête, l'utiliser, sinon faire un toggle
            if ($request->has('desactiver')) {
                $livreur->update([
                    'desactiver' => $request->desactiver
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
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechercher des livreurs
     */
    public function search(Request $request): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $term = $request->query('q', '');

        try {
            $livreurs = Livreur::with(['user', 'demandeAdhesion'])
                ->where('wilaya_id', $wilayaCode)
                ->whereHas('user', function ($query) use ($term) {
                    $query->where('nom', 'like', "%{$term}%")
                          ->orWhere('prenom', 'like', "%{$term}%")
                          ->orWhere('email', 'like', "%{$term}%")
                          ->orWhere('telephone', 'like', "%{$term}%");
                })
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $livreurs,
                'count' => $livreurs->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les livreurs par type
     */
    public function byType(Request $request, $type): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        
        if (!in_array($type, ['distributeur', 'ramasseur'])) {
            return response()->json([
                'success' => false,
                'message' => 'Type invalide. Utilisez "distributeur" ou "ramasseur"'
            ], 422);
        }

        try {
            $livreurs = Livreur::with(['user', 'demandeAdhesion'])
                ->where('wilaya_id', $wilayaCode)
                ->where('type', $type)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $livreurs,
                'count' => $livreurs->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livreurs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ⚠️ PAS de méthode destroy() - Le gestionnaire n'a pas le droit de suppression
}