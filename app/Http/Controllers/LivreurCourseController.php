<?php

namespace App\Http\Controllers;

use App\Models\Livraison;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LivreurCourseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Récupère le livreur connecté et son type
     */
    private function getLivreur()
    {
        $user = auth()->user();
        if (!$user || !$user->livreur) {
            abort(403, 'Accès refusé : vous n\'êtes pas un livreur.');
        }
        return $user->livreur;
    }

    /**
     * Liste toutes les courses assignées au livreur selon son type
     */
    public function index(): JsonResponse
    {
        $livreur = $this->getLivreur();

        $query = Livraison::with([
            'demandeLivraison',
            'demandeLivraison.colis',
            'client.user',
            'demandeLivraison.destinataire.user'
        ]);

        // Filtrage selon le type de livreur
        if ($livreur->type === 'distributeur') {
            $query->where('livreur_distributeur_id', $livreur->id);
        } elseif ($livreur->type === 'ramasseur') {
            $query->where('livreur_ramasseur_id', $livreur->id);
        } else {
            // Livreurs polyvalents : toutes les courses assignées
            $query->where('livreur_distributeur_id', $livreur->id)
                ->orWhere('livreur_ramasseur_id', $livreur->id);
        }

        $courses = $query->get();

        return response()->json(['success' => true, 'data' => $courses], 200);
    }

    /**
     * Voir une course spécifique
     */
    public function show(string $id): JsonResponse
    {
        $livreur = $this->getLivreur();

        $query = Livraison::where('id', $id)
            ->with([
                'demandeLivraison',
                'demandeLivraison.colis',
                'client.user',
                'demandeLivraison.destinataire.user'
            ]);

        if ($livreur->type === 'distributeur') {
            $query->where('livreur_distributeur_id', $livreur->id);
        } elseif ($livreur->type === 'ramasseur') {
            $query->where('livreur_ramasseur_id', $livreur->id);
        } else {
            $query->where(function ($q) use ($livreur) {
                $q->where('livreur_distributeur_id', $livreur->id)
                    ->orWhere('livreur_ramasseur_id', $livreur->id);
            });
        }

        $course = $query->first();

        if (!$course) {
            return response()->json(['success' => false, 'message' => 'Course introuvable ou non assignée à ce livreur.'], 404);
        }

        return response()->json(['success' => true, 'data' => $course], 200);
    }

    /**
     * Valider une course
     */
    public function complete(string $id): JsonResponse
    {
        $livreur = $this->getLivreur();

        $query = Livraison::where('id', $id);

        if ($livreur->type === 'distributeur') {
            $query->where('livreur_distributeur_id', $livreur->id);
        } elseif ($livreur->type === 'ramasseur') {
            $query->where('livreur_ramasseur_id', $livreur->id);
        } else {
            $query->where(function ($q) use ($livreur) {
                $q->where('livreur_distributeur_id', $livreur->id)
                    ->orWhere('livreur_ramasseur_id', $livreur->id);
            });
        }

        $course = $query->first();

        if (!$course) {
            return response()->json(['success' => false, 'message' => 'Course introuvable ou non assignée à ce livreur.'], 404);
        }

        if ($course->status === 'livre') {
            return response()->json(['success' => false, 'message' => 'Course déjà terminée.'], 422);
        }

        $course->update(['status' => 'livre']);
        return response()->json(['success' => true, 'message' => 'Course marquée comme terminée.', 'data' => $course], 200);
    }

    /**
     * Nombre total de courses assignées au livreur
     */
    public function count(): JsonResponse
    {
        $livreur = $this->getLivreur();

        if ($livreur->type === 'distributeur') {
            $total = $livreur->livraisonsDistribution()->count();
        } elseif ($livreur->type === 'ramasseur') {
            $total = $livreur->livraisonsRamassage()->count();
        } else {
            $total = $livreur->livraisonsDistribution()->count() + $livreur->livraisonsRamassage()->count();
        }

        return response()->json(['success' => true, 'total_courses' => $total], 200);
    }

    /**
     * Filtrer les courses par statut
     */
    public function byStatus(string $status): JsonResponse
    {
        $livreur = $this->getLivreur();

        $validStatuses = [
            'en_attente',
            'prise_en_charge_ramassage',
            'ramasse',
            'en_transit',
            'prise_en_charge_livraison',
            'livre',
            'annule'
        ];

        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Statut invalide. Utiliser : ' . implode(', ', $validStatuses)
            ], 422);
        }

        $query = Livraison::with([
            'demandeLivraison',
            'demandeLivraison.colis',
            'client.user',
            'demandeLivraison.destinataire.user'
        ])->where('status', $status);

        if ($livreur->type === 'distributeur') {
            $query->where('livreur_distributeur_id', $livreur->id);
        } elseif ($livreur->type === 'ramasseur') {
            $query->where('livreur_ramasseur_id', $livreur->id);
        } else {
            $query->where(function ($q) use ($livreur) {
                $q->where('livreur_distributeur_id', $livreur->id)
                    ->orWhere('livreur_ramasseur_id', $livreur->id);
            });
        }

        $courses = $query->get();

        return response()->json([
            'success' => true,
            'status' => $status,
            'data' => $courses
        ], 200);
    }

    public function colis(): JsonResponse
    {
        $livreur = $this->getLivreur();

        // Récupère toutes les courses du livreur avec leurs colis et informations client/destinataire
        $courses = Livraison::with(['demandeLivraison.colis', 'demandeLivraison.client.user', 'demandeLivraison.destinataire.user'])
            ->where(function ($q) use ($livreur) {
                $q->where('livreur_distributeur_id', $livreur->id)
                    ->orWhere('livreur_ramasseur_id', $livreur->id);
            })
            ->get();

        // On collecte les colis avec les informations d'expédition
        $colis = $courses->map(function ($course) {
            if (!$course->demandeLivraison || !$course->demandeLivraison->colis) {
                return null;
            }
            
            $demande = $course->demandeLivraison;
            $colisData = $demande->colis->toArray();
            
            // Ajouter les informations de l'expéditeur (client)
            if ($demande->client && $demande->client->user) {
                $colisData['expediteur_nom'] = $demande->client->user->nom . ' ' . $demande->client->user->prenom;
                $colisData['expediteur_telephone'] = $demande->client->user->telephone;
            } else {
                $colisData['expediteur_nom'] = 'Non renseigné';
                $colisData['expediteur_telephone'] = 'Non renseigné';
            }
            $colisData['expediteur_adresse'] = $demande->addresse_depot ?? 'Non renseigné';
            
            // Ajouter les informations du destinataire
            if ($demande->destinataire && $demande->destinataire->user) {
                $colisData['destinataire_nom'] = $demande->destinataire->user->nom . ' ' . $demande->destinataire->user->prenom;
                $colisData['destinataire_telephone'] = $demande->destinataire->user->telephone;
            } else {
                $colisData['destinataire_nom'] = 'Non renseigné';
                $colisData['destinataire_telephone'] = 'Non renseigné';
            }
            $colisData['destinataire_adresse'] = $demande->addresse_delivery ?? 'Non renseigné';
            
            // Ajouter les coordonnées GPS pour le tracking
            $colisData['lat_depot'] = $demande->lat_depot;
            $colisData['lng_depot'] = $demande->lng_depot;
            $colisData['lat_delivery'] = $demande->lat_delivery;
            $colisData['lng_delivery'] = $demande->lng_delivery;
            
            // Ajouter le statut de la livraison
            $colisData['status'] = $course->status;
            $colisData['livraison_id'] = $course->id;
            
            return $colisData;
        })->filter(); // enlève les null

        return response()->json([
            'success' => true,
            'total_colis' => $colis->count(),
            'data' => $colis->values(),
        ], 200);
    }
}
