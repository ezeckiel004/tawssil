<?php

namespace App\Http\Controllers;

use App\Models\Livraison;
use App\Models\Commentaire;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\DemandeLivraison;
use App\Enums\NotificationType;
use App\Models\Livreur;
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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\NewLivreurAssignmentMail; 

class LivraisonController extends Controller
{
    /**
     * Afficher toutes les livraisons.
     */
    public function index(): JsonResponse
    {
        //$livraisons = Livraison::with(relations: ['client','livreur', 'demande_livraison', 'bordereau'] )->get();

        $livraisons = Livraison::get();

        $datas = [];
        foreach ($livraisons as $livraison) {
            $datas[] = [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'demande_livraisons_id'=> $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id'=> $livraison->bordereau_id,
                'code_pin'=> $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'livreur_distributeur'=> $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison->load([
                    'client',
                    'destinataire',
                    'colis',     
                      ]),
               'destinataire'=> $livraison->demandeLivraison->destinataire?->user,
                'client'=> $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
            
            ];
        }
        
        return response()->json($datas, 200);
    }
    

    public function statistiquesClient( $id): JsonResponse
    {

        $livraisons = Livraison::where('client_id', $id)->get();
        $total = $livraisons->isEmpty()?0: $livraisons->count();
        $statuss = [
            'livraisons_terminees' => 0,
            'livraisons_en_attente' => 0,
            'livraisons_en_cours' => 0,
            'total_livraisons' => $total
        ];
        
        // Récupérer les données des livraisons
        // Initialiser les compteurs

            

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
            $statuss, 200);
    }

//Statistiques livreurs

	public function statistiquesLivreur( $id): JsonResponse
	    {

		$livraisons = Livraison::where('livreur_distributeur_id', $id)
		    ->orWhere('livreur_ramasseur_id', $id)
		    ->get();

		$total = $livraisons->isEmpty()?0: $livraisons->count();
		$status = [
		    'livraisons_terminees' => 0,
		    'livraisons_en_attente' => 0,
		    'livraisons_en_cours' => 0,
		    'total_livraisons' => $total
		];
		
		// Récupérer les données des livraisons
		// Initialiser les compteurs

		    

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
		    $status, 200);
	    }


	public function livraisonsEnCours(): JsonResponse
	{
	    $livraisons = Livraison::whereNotIn('status', ['en_attente', 'livre'])->get();
	    

		$datas = [];
		foreach ($livraisons as $livraison) {
		    $datas[] = [
		        'id' => $livraison->id,
		        'client_id' => $livraison->client_id,
		        'demande_livraisons_id'=> $livraison->demande_livraisons_id,
		        'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
		        'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
		        'bordereau_id'=> $livraison->bordereau_id,
		        'code_pin'=> $livraison->code_pin,
		        'date_ramassage' => $livraison->date_ramassage,
		        'date_livraison' => $livraison->date_livraison,
		        'status' => $livraison->status,
		        'livreur_distributeur'=> $livraison->livreurDistributeur?->user,
		        'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
		        'demande_livraison' => $livraison->demandeLivraison->load([
		            'client',
		            'destinataire',
		            'colis',     
		              ]),
		       'destinataire'=> $livraison->demandeLivraison->destinataire?->user,
		        'client'=> $livraison->client?->user,
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
		        'demande_livraisons_id'=> $livraison->demande_livraisons_id,
		        'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
		        'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
		        'bordereau_id'=> $livraison->bordereau_id,
		        'code_pin'=> $livraison->code_pin,
		        'date_ramassage' => $livraison->date_ramassage,
		        'date_livraison' => $livraison->date_livraison,
		        'status' => $livraison->status,
		        'livreur_distributeur'=> $livraison->livreurDistributeur?->user,
		        'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
		        'demande_livraison' => $livraison->demandeLivraison->load([
		            'client',
		            'destinataire',
		            'colis',     
		              ]),
		       'destinataire'=> $livraison->demandeLivraison->destinataire?->user,
		        'client'=> $livraison->client?->user,
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
		        'demande_livraisons_id'=> $livraison->demande_livraisons_id,
		        'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
		        'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
		        'bordereau_id'=> $livraison->bordereau_id,
		        'code_pin'=> $livraison->code_pin,
		        'date_ramassage' => $livraison->date_ramassage,
		        'date_livraison' => $livraison->date_livraison,
		        'status' => $livraison->status,
		        'livreur_distributeur'=> $livraison->livreurDistributeur?->user,
		        'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
		        'demande_livraison' => $livraison->demandeLivraison->load([
		            'client',
		            'destinataire',
		            'colis',     
		              ]),
		       'destinataire'=> $livraison->demandeLivraison->destinataire?->user,
		        'client'=> $livraison->client?->user,
		        'commentaires' => $livraison->commentaires,
		        'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
		    
		    ];
		}

	    return response()->json($datas, 200);
	}



    /**
     * Créer une nouvelle livraison.
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'demande_livraison_id' => 'required|integer|exists:demandes_livraison,id',
            'livreur_id' => 'required|string|exists:users,id',
            'status' => 'required|string|in:en_attente,en_cours,livree,annulee',
            'date_livraison' => 'required|date',
        ]);

        try {
            $livraison = Livraison::create($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Livraison créée avec succès',
                'data' => $livraison,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la livraison',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Afficher une livraison spécifique.
     */
    public function show($id): JsonResponse
    {
        //$livraison = Livraison::with(relations: ['user', 'demande_livraison', 'commentaires','client', 'bordereau'] )->find($id);

        $livraison = Livraison::find($id);

        if (!$livraison) {
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        }

        return response()->json( 
            [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'demande_livraisons_id'=> $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id'=> $livraison->bordereau_id,
                'code_pin'=> $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'livreur_distributeur'=> $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison->load([
                    'client',
                    'destinataire',
                    'colis',     
                      ]),
                   'destinataire'=> $livraison->demandeLivraison->destinataire?->user,
                'client'=> $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
            
            ],
               
         200);
    }


    public function getByClient($id): JsonResponse
    {
        try{
            $livraisons = Livraison::where('client_id', $id)
            ->get();
               
        $datas = [];
        foreach ($livraisons as $livraison) {
            $demandeLivraison = $livraison->demandeLivraison->load([
                'colis',     
            ]);
            
            $datas[] = [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'demande_livraisons_id'=> $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id'=> $livraison->bordereau_id,
                'code_pin'=> $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'livreur_distributeur'=> $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => [
                    'id' => $demandeLivraison->id,
                    'client_id' => $demandeLivraison->client_id,
                    'destinataire_id' => $demandeLivraison->destinataire_id,
                    'colis_id' => $demandeLivraison->colis_id,
                    'addresse_depot' => $demandeLivraison->addresse_depot,
                    'addresse_delivery' => $demandeLivraison->addresse_delivery,
                    'info_additionnel' => $demandeLivraison->info_additionnel,
                    'prix' => $demandeLivraison->prix,
                    'lat_depot' => $demandeLivraison->lat_depot,
                    'lng_depot' => $demandeLivraison->lng_depot,
                    'lat_delivery' => $demandeLivraison->lat_delivery,
                    'lng_delivery' => $demandeLivraison->lng_delivery,
                    'wilaya' => $demandeLivraison->wilaya,
                    'commune' => $demandeLivraison->commune,
                    'created_at' => $demandeLivraison->created_at,
                    'updated_at' => $demandeLivraison->updated_at,
                    'colis' => $demandeLivraison->colis,
                ],
                'destinataire'=> $livraison->demandeLivraison->destinataire->load([
                    'user'
                      ]),
                'client'=> $livraison->client->load([
                    'user'
                      ]),
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
            
            ];
        }
    
            /*
              if ($livraisons->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune livraison trouvée pour ce client.',
            ], 404);
            }
            */
    
            /*
            return response()->json([
               // 'success' => true,
                //'data' => [
                    'livraison' => $livraisons ?? []
                    //'demande_livraison' => $livraison->demandeLivraison,
                   // 'commentaires' => Commentaire::firstWhere('livraison_id', $livraison->id),
                   // 'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
                    ,
                    
            , 200]);
              */

              return response()->json(
               
                      $datas
                    
             , 200);


        }catch(\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livraisons du client.',
                'error' => $e->getMessage(),
            ], 500);
        }
        
    }

    public function getByLivreur($id): JsonResponse
    {
        try{
            $livraisons = Livraison::where([
                'livreur_distributeur_id' => $id,
                'livreur_ramasseur_id' => $id,
            ])->orWhere([
                'livreur_distributeur_id' => $id,
            ])->orWhere([
                'livreur_ramasseur_id' => $id,
            ])->get();   
    
            // Vérifier si la livraison existe
    
            /*
            if (!$livraisons) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune livraison trouvée pour ce livreur.',
                ], 404);
            }
            */
    
               
        $datas = [];
        foreach ($livraisons as $livraison) {
            $datas[] = [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'demande_livraisons_id'=> $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id'=> $livraison->bordereau_id,
                'code_pin'=> $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'livreur_distributeur'=> $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison->load([
                    'client',
                    'destinataire',
                    'colis',     
                      ]),
                'destinataire'=> $livraison->demandeLivraison->destinataire?->user,
                'client'=> $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
            
            ];
        }
            return response()->json(
               // 'success' => true,
                //'data' => [
                    $datas,
                    /*'demande_livraison' => $livraison->demandeLivraison,
                    'commentaires' => Commentaire::firstWhere('livraison_id', $livraison->id),
                    'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
                    ,
                    */
             200);

        }catch(\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livraisons du livreur',
                'error' => $e->getMessage(),
            ], 500);
        }
        
    }
    /**
     * Mettre à jour une livraison.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $livraison = Livraison::find($id);

        if (!$livraison) {
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        }

        $validatedData = $request->validate([
            'demande_livraison_id' => 'sometimes|integer|exists:demandes_livraison,id',
            'livreur_id' => 'sometimes|integer|exists:users,id',
            'status' => 'sometimes|string|in:en_attente,en_cours,livree,annulee',
            'date_livraison' => 'sometimes|date',
        ]);

        $livraison->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Livraison mise à jour avec succès',
            'data' => $livraison,
        ], 200);
    }

    
    /**
     * Attribuer un livreur à une livraison.
     */
    public function assignLivreur(Request $request, $id): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'livreur_id' => 'required|string|exists:livreurs,id', // Vérifie que le livreur existe
            'type' => 'required|integer|in:1,2', // 1 pour ramasseur, 2 pour distributeur
        ]);


        if ($validated->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validated->errors(),
            ], 422);
        }
        
        $validatedData = $validated->validated();

        $livraison = Livraison::find($id);
        $type = $validatedData['type'];

        if (!$livraison) {
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        }
        if( Auth::user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à attribuer un livreur à cette livraison',
            ], 403);

        }

        try {
            DB::beginTransaction();

            // Mettre à jour le livreur de la livraison
            if ($type == 2) {
                // Type 2 : Livreurs distributeurs
                if( $livraison->status == 'en_transit') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Le colis est deja en transit, vous ne pouvez plus  attribuer un autre distributeur',
                    ], 400);
                }
                  else {
                    $livraison->update([
                    'livreur_distributeur_id' => $validatedData['livreur_id'],
                    'status' => 'prise_en_charge_livraison'
                ]);
                }
                
            } elseif ($type == 1) {
                // Type 1 : Livreurs ramasseurs
                if( $livraison->status == 'ramasse') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Le colis a deja ete ramasse , vous ne pouvez plus  attribuer un autre ramasseur',
                    ], 400);
                }
                  else {

                    $livraison->update([
                        'livreur_ramasseur_id' => $validatedData['livreur_id'],
                        'status' => 'prise_en_charge_ramassage'
                    ]);
                }
                
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Type de livreur invalide',
                ], 400);
            }

            /*
            $notificationController = new NotificationController();
            // Envoi de la notification à l'utilisateur
            $notificationController->sendNotificationToUser(
                userId: $livraison->demandeLivraison->client_id,
                type: NotificationType::LIVRAISON_CONFIRMER,
                title: "Livreur attribué à la livraison",
                body: "Un livreur a été attribué à votre livraison. Veuillez vérifier les détails de la livraison."
            );
            // Envoi de la notification au livreur
            $notificationController->sendNotificationToUser(
                userId: $validatedData['livreur_id'],
                type: NotificationType::LIVRAISON_ATTRIBUER,
                title: "Vous avez été attribué à une livraison",
                body: "Vous avez été attribué à une livraison. Veuillez vérifier les détails de la livraison."
            );
            */
            
             // Envoyer un email au livreur
            try {
                $livreur = Livreur::with('user')->find($validatedData['livreur_id']);
                
                if ($livreur && $livreur->user && $livreur->user->email) {
                    Mail::to($livreur->user->email)->send(new NewLivreurAssignmentMail($livraison, $livreur->user, $type));
                    Log::info('Email d\'assignation envoyé au livreur: ' . $livreur->user->email . ' (Type: ' . ($type === 1 ? 'Ramasseur' : 'Distributeur') . ')');
                } else {
                    Log::warning('Livreur non trouvé ou email du livreur non défini - ID: ' . $validatedData['livreur_id']);
                }
            } catch (\Exception $e) {
                // L'envoi d'email a échoué, mais on continue l'action
                Log::error('Erreur lors de l\'envoi du mail au livreur: ' . $e->getMessage());
            }
            
            DB::commit();

            /*
            return response()->json([
                'success' => true,
                'message' => 'Livreur attribué avec succès à la livraison',
                'data' => $livraison,
            ], 200);
            */
            return response()->json( $livraison,
             200);


        } catch (\Exception $e) {
            DB::rollBack();

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
        $livraison = Livraison::find($id);

        if (!$livraison) {
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        }

        $livraison->delete();

        return response()->json([
            'success' => true,
            'message' => 'Livraison supprimée avec succès',
        ], 200);
    }

    public function destroyByClient($id): JsonResponse
    {
        $livraison = Livraison::find($id);

        if (!$livraison) {
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        }else{
            if ($livraison->status !== 'en_attente') {
                return response()->json([
                    'success' => false,
                    'message' => 'La livraison ne peut être supprimée que si elle est en attente',
                ], 400);
            }
        }

        $livraison->delete();

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
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:en_attente,prise_en_charge_ramassage,ramasse,en_transit,prise_en_charge_livraison,livre,annule',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validatedData = $validator->validated();

        try{
            $livraison = Livraison::find($id);

            if (!$livraison) {
                return response()->json([
                    'success' => false,
                    'message' => 'Livraison introuvable',
                ], 404);
            }
    
            $livraison->update([
                'status' => $validatedData['status'],
            ]);
    
            /*
            $notificationController = new NotificationController();
            // Envoi de la notification à l'utilisateur
            $notificationController->sendNotificationToUser(
                userId: [$livraison->demandeLivraison->client_id, $livraison->livreur_ramasseur_id, $livraison->livreur_distributeur_id, User::where('role', 'admin')->first()->id],
                type: NotificationType::LIVRAISON_status_MISE_A_JOUR,
                title: "status de la livraison mis à jour",
                body: "Le status de votre livraison a été mis à jour. Veuillez vérifier les détails de la livraison."
            );
            */
    
            /*
            return response()->json([
                'success' => true,
                'message' => 'status de la livraison mis à jour avec succès',
                'data' => $livraison,
            ], 200);
            */
    
            return response()->json($livraison,
             200);
        }catch (\Exception $e) {

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
        \Log::info('Recherche du colis: ' . $colis_label);
        
        // 1. Trouver le colis
        $colis = \App\Models\Colis::where('colis_label', $colis_label)->first();
        
        if (!$colis) {
            \Log::warning('Colis non trouvé: ' . $colis_label);
            return response()->json([
                'success' => false, 
                'message' => 'Colis introuvable avec ce code de suivi'
            ], 404);
        }
        
        \Log::info('Colis trouvé, ID: ' . $colis->id);
        
        // 2. Trouver la demande de livraison qui a ce colis_id
        $demandeLivraison = \App\Models\DemandeLivraison::where('colis_id', $colis->id)->first();
        
        if (!$demandeLivraison) {
            \Log::warning('Aucune demande de livraison pour le colis ID: ' . $colis->id);
            return response()->json([
                'success' => false, 
                'message' => 'Aucune demande de livraison trouvée pour ce colis'
            ], 404);
        }
        
        \Log::info('Demande de livraison trouvée, ID: ' . $demandeLivraison->id);
        
        // 3. Trouver la livraison associée à cette demande
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
            \Log::warning('Aucune livraison pour la demande ID: ' . $demandeLivraison->id);
            return response()->json([
                'success' => false, 
                'message' => 'Aucune livraison en cours pour ce colis'
            ], 404);
        }
        
        \Log::info('Livraison trouvée, ID: ' . $livraison->id . ', Status: ' . $livraison->status);
        
        // 4. Retourner les données formatées
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
        \Log::error('Erreur trackByColisLabel: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        
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
    try {
        $livraison = Livraison::find($id);
        
        if (!$livraison) {
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        }
        
        $data = $this->preparePrintData($livraison);
        
        $html = View::make('pdf.bordereau', $data)->render();
        
        return response()->json([
            'success' => true,
            'html' => $html,
        ], 200);
        
    } catch (\Exception $e) {
        \Log::error('Erreur génération HTML: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la génération du HTML',
            'error' => $e->getMessage(),
        ], 500);
    }
}

/**
 * Générer et télécharger le PDF du bordereau
 */
public function generateBordereauPDF($id)
{
    try {
        $livraison = Livraison::find($id);
        
        if (!$livraison) {
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        }
        
        $data = $this->preparePrintData($livraison);
        
        $pdf = Pdf::loadView('pdf.bordereau', $data);
        
        $pdf->setPaper([0, 0, 380, 700], 'portrait');
        $pdf->setOption('enable_html5_parser', true);
        $pdf->setOption('enable_remote', true);
        $pdf->setOption('defaultFont', 'DejaVu Sans');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isPhpEnabled', false);
        
        $fileName = 'bordereau_livraison_' . $livraison->id . '_' . date('Ymd_His') . '.pdf';
        
        return $pdf->download($fileName);
        
    } catch (\Exception $e) {
        \Log::error('Erreur génération PDF: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la génération du PDF',
            'error' => $e->getMessage(),
        ], 500);
    }
}



/**
 * Préparer les données pour l'impression
 */
private function preparePrintData($livraison): array
{
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
    
    $qrCodeData = json_encode([
        'id' => $livraison->id,
        'code_pin' => $livraison->code_pin,
        'colis_label' => $colis->colis_label ?? 'N/A',
        'status' => $livraison->status,
        'client' => ($client->prenom ?? '') . ' ' . ($client->nom ?? ''),
        'destinataire' => ($destinataire->prenom ?? '') . ' ' . ($destinataire->nom ?? ''),
        'date' => now()->toISOString(),
    ]);
    
    $qrCode = $this->generateQRCode($qrCodeData);
    
    $barcodeValue = $colis->colis_label ?? 'COLIS-' . $livraison->id;
    $barcode = $this->generateBarcode($barcodeValue);
    
    $createdAt = $livraison->created_at
        ? Carbon::parse($livraison->created_at)->locale('fr_FR')->isoFormat('DD/MM/YYYY HH:mm')
        : 'Non définie';
        
    $dateLivraison = $livraison->date_livraison
        ? Carbon::parse($livraison->date_livraison)->locale('fr_FR')->isoFormat('DD/MM/YYYY HH:mm')
        : 'Non définie';
        
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
        'createdAt' => $createdAt,
        'dateLivraison' => $dateLivraison,
        'printDate' => $printDate,
        'statusLabel' => $statusLabel,
    ];
}

/**
 * Générer un QR Code en base64
 */
private function generateQRCode(string $data): string
{
    try {
        if (class_exists('BaconQrCode\Writer')) {
            $renderer = new ImageRenderer(
                new RendererStyle(90),
                new ImagickImageBackEnd()
            );
            
            $writer = new Writer($renderer);
            $qrCode = $writer->writeString($data);
            
            return 'data:image/png;base64,' . base64_encode($qrCode);
        }
    } catch (\Exception $e) {
        \Log::warning('Erreur génération QR Code local: ' . $e->getMessage());
    }
    
    $encodedData = urlencode($data);
    return "https://api.qrserver.com/v1/create-qr-code/?size=90x90&data={$encodedData}&format=png&margin=1";
}

/**
 * Générer un code-barres en base64
 */
private function generateBarcode(string $value): string
{
    try {
        if (class_exists('Picqer\Barcode\BarcodeGeneratorPNG')) {
            $generator = new BarcodeGeneratorPNG();
            $barcode = $generator->getBarcode($value, $generator::TYPE_CODE_128, 2, 50);
            
            return 'data:image/png;base64,' . base64_encode($barcode);
        }
    } catch (\Exception $e) {
        \Log::warning('Erreur génération code-barres local: ' . $e->getMessage());
    }
    
    return $this->generateSimpleBarcode($value);
}

/**
 * Générer un code-barres simplifié (fallback)
 */
private function generateSimpleBarcode(string $value): string
{
    if (!function_exists('imagecreatetruecolor')) {
        return 'data:image/svg+xml;base64,' . base64_encode('
            <svg width="250" height="50" xmlns="http://www.w3.org/2000/svg">
                <rect width="250" height="50" fill="white"/>
                <text x="125" y="30" text-anchor="middle" font-family="Arial" font-size="12">' . htmlspecialchars($value) . '</text>
            </svg>
        ');
    }
    
    $width = 250;
    $height = 50;
    
    $image = imagecreatetruecolor($width, $height);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    
    imagefill($image, 0, 0, $white);
    
    $charWidth = 3;
    $x = 10;
    
    for ($i = 0; $i < strlen($value); $i++) {
        $char = ord($value[$i]);
        $barHeight = ($char % 40) + 10;
        
        imagefilledrectangle($image, $x, 10, $x + $charWidth, 10 + $barHeight, $black);
        $x += $charWidth + 1;
    }
    
    imagestring($image, 2, $width / 2 - (strlen($value) * 3), $height - 15, $value, $black);
    
    ob_start();
    imagepng($image);
    $barcode = ob_get_clean();
    imagedestroy($image);
    
    return 'data:image/png;base64,' . base64_encode($barcode);
}
}