<?php

namespace App\Http\Controllers;

use App\Models\DemandeLivraison;
use \App\Models\User;
use Illuminate\Http\Request;
use App\Models\Colis;
use App\Models\Livraison;
use App\Models\Client;
use App\Enums\NotificationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DemandeLivraisonController extends Controller
{
    /**
     * Afficher toutes les demandes de livraison.
     */
    public function index(): JsonResponse
    {
        $demandes = DemandeLivraison::all();

        return response()->json([
            'success' => true,
            'data' => $demandes,
        ], 200);
    }

    /**
     * Créer une nouvelle demande de livraison.
     */
    /**
 * Créer une nouvelle demande de livraison.
 */
    public function store(Request $request): JsonResponse
    {
        // LOG AVANT VALIDATION - Voir exactement ce qui arrive
        \Log::info('=== DEBUT DEMANDE LIVRAISON ===');
        \Log::info('Request method: ' . $request->method());
        \Log::info('Content-Type: ' . $request->header('Content-Type'));
        \Log::info('All input keys: ' . json_encode(array_keys($request->all())));
        \Log::info('Has file colis_photo: ' . ($request->hasFile('colis_photo') ? 'YES' : 'NO'));
        
        if ($request->hasFile('colis_photo')) {
            $file = $request->file('colis_photo');
            \Log::info('Photo file details BEFORE validation:', [
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
                'is_valid' => $file->isValid(),
                'error_code' => $file->getError(),
                'max_file_size' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ]);
        } else {
            \Log::warning('NO FILE FOUND - Checking all files in request');
            \Log::info('All files: ' . json_encode($request->allFiles()));
        }
    
        // Valider les données de la requête
        $validated = Validator::make($request->all(), [
            'client_id' => 'required|string|exists:clients,id',
            'addresse_depot' => 'required|string|max:255',
            'addresse_delivery' => 'required|string|max:255',
            'info_additionnel' => 'required|string',
            'lat_depot' => 'required|numeric',
            'lng_depot' => 'required|numeric',  // Latitude et longitude du point de départ
            'lat_delivery' => 'required|numeric', // Latitude du point de livraison
            'lng_delivery' => 'required|numeric', // Longitude du point de livraison
            'destinataire_nom' => 'required|string|max:255', // Nom du destinataire
            'destinataire_email' => 'required|email|max:255', // Email du destinataire
            'destinataire_telephone' => 'required|string|max:20', // Téléphone du destinataire
            'colis_poids' => 'required|numeric|min:0.1', // Poids du colis
            'prix' => 'required|numeric|min:0.1', // Poids du colis
            'colis_type' => 'string',
            'colis_photo' => 'nullable|file|max:10240', // Validation de la photo (10MB max)

        ]);
   
        if ($validated->fails()) {
            \Log::error('=== VALIDATION FAILED ===');
            \Log::error('Validation errors:', $validated->errors()->toArray());
            \Log::error('Request data (without photo):', $request->except(['colis_photo']));
            
            if ($request->hasFile('colis_photo')) {
                $file = $request->file('colis_photo');
                \Log::error('Photo file that FAILED validation:', [
                    'size_bytes' => $file->getSize(),
                    'size_kb' => round($file->getSize() / 1024, 2),
                    'size_mb' => round($file->getSize() / 1024 / 1024, 2),
                    'mime_type' => $file->getMimeType(),
                    'original_name' => $file->getClientOriginalName(),
                    'is_valid' => $file->isValid(),
                    'error_code' => $file->getError(),
                    'error_message' => $this->getUploadErrorMessage($file->getError()),
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validated->errors(),
            ], 422);
        }
        
        \Log::info('=== VALIDATION PASSED ===');


    $validatedData = $validated->validated();

    try {
        DB::beginTransaction();

        $parts = preg_split('/\s+/', trim($validatedData['destinataire_nom']));
        // Créer un utilisateur pour le destinataire
        
        /*
        $user = User::create([
            'nom' =>  implode(' ', $parts),
            'prenom' => array_pop($parts),
            'email' => $validatedData['destinataire_email'],
            'telephone' => $validatedData['destinataire_telephone'],
            'password' => bcrypt('default_password'), 
            
        ]);
        
        $destinataire = Client::create([
            'user_id'   => $user->id,
            'status'    => 'active',
        ]);
        */
        
        // Recherche d'un utilisateur existant par email ou téléphone
	$user = User::where('email', $validatedData['destinataire_email'])
		    ->orWhere('telephone', $validatedData['destinataire_telephone'])
		    ->first();

	if (!$user) {
	    $parts = explode(' ', $validatedData['destinataire_nom']); // suppose qu'on a le nom complet

	    $user = User::create([
		'nom'       => implode(' ', $parts),
		'prenom'    => array_pop($parts),
		'email'     => $validatedData['destinataire_email'],
		'telephone' => $validatedData['destinataire_telephone'],
		'password'  => bcrypt('default_password'), // À remplacer plus tard
		'role'      => 'client',
		'actif'     => true,
	    ]);
	}

	// Création du client uniquement si non existant
	$destinataire = Client::firstOrCreate(
	    ['user_id' => $user->id],
	    ['status' => 'active']
	);


        // Générer un colis_label unique
        $colisLabel = 'COLIS-' . strtoupper(uniqid());
        
        // Upload de la photo (nullable)
        $photoPath = null;
        if ($request->hasFile('colis_photo')) {
            \Log::info('Photo field exists in request');
            $utilsController = new UtilsController();
            $photoPath = $utilsController->uploadPhoto($request, 'colis_photo');
        } else {
            \Log::warning('No colis_photo field in request', [
                'all_files' => $request->allFiles(),
                'has_file' => $request->hasFile('colis_photo')
            ]);
        }

        // Créer un colis
        $colis = Colis::create([
            'poids' => $validatedData['colis_poids'],
            'colis_type' => $validatedData['colis_type'] ?? null,
            'colis_label' => $colisLabel,
            'colis_photo' => $photoPath,
            'colis_photo_url' => $photoPath ? asset('storage/' . $photoPath) : null, // URL de la photo

        ]);

        // Créer la demande de livraison
        $demande = DemandeLivraison::create([
            'client_id' => $validatedData['client_id'],
            'addresse_depot' => $validatedData['addresse_depot'],
            'addresse_delivery' => $validatedData['addresse_delivery'],
            'info_additionnel' => $validatedData['info_additionnel'] ?? null,
            'destinataire_id' => $destinataire->id, // Associer l'utilisateur destinataire
            'colis_id' => $colis->id, // Associer le colis
            'prix' => $validatedData['prix'],
            'lat_depot' => $validatedData['lat_depot'],
            'lng_depot' => $validatedData['lng_depot'],  
            'lat_delivery' => $validatedData['lat_delivery'], 
            'lng_delivery' => $validatedData['lng_delivery']

        ]);

          // Créer une livraison associée à la demande
          $livraison = Livraison::create([
            'client_id' => $validatedData['client_id'],
            'demande_livraisons_id' => $demande->id,
            'code_pin' => $this->generateUniquePin(),            
        ]);

        //$notificationController = new NotificationController();

        // Envoi de la notification à l'utilisateur 
        /*$notificationController->sendNotificationToUser(
            userId: [User::where('role', 'admin')->first()->id, $demande->client_id],
            type: NotificationType::NEW_DELIVERY_REQUEST,
            title: "Nouvelle demande de livraison",
            body: "Votre demande de livraison a été créée avec succès."
        );
        */

        DB::commit();
        

        /*
        return response()->json([
            'success' => true,
            'message' => 'Demande de livraison créée avec succès',
          // 'data' => [ 
                //'demande' => $demande,
                //'destinataire' => $destinataire,
                //'colis' => $colis,
              //  'livraison' => $livraison,
           // ],
            'data' =>$demande->load([
                'client',
                'colis',      
                  ]),

        ], 201);
        */
        return response()->json(

            $demande->load([
                'client',
                'destinataire',
                'colis',     
                  ]),

        201);

    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la création de la demande de livraison',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Afficher une demande de livraison spécifique.
     */
    public function show($id): JsonResponse
    {
        $demande = DemandeLivraison::with(relations: ['user', 'colis', 'client'] )->find($id);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande de livraison introuvable',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $demande,
        ], 200);
    }

    /**
     * Mettre à jour une demande de livraison.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $demande = DemandeLivraison::find($id);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande de livraison introuvable',
            ], 404);
        }

        $validatedData = $request->validate([
            'client_id' => 'sometimes|string|exists:clients,id',
            'addresse_depot' => 'sometimes|string|max:255',
            'addresse_delivery' => 'sometimes|string|max:255',
            'info_additionnel' => 'nullable|string',
            'date_livraison' => 'sometimes|date',
            'statut' => 'sometimes|string|in:en_attente,en_cours,livree,annulee',
        ]);

        $demande->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Demande de livraison mise à jour avec succès',
            'data' => $demande,
        ], 200);
    }

    /**
 * Générer un code PIN unique à 5 chiffres.
 */
    public function generateUniquePin(): string
    {
        do {
            // Générer un code PIN aléatoire à 5 chiffres
            $pin = str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        } while (Livraison::where('code_pin', $pin)->exists()); // Vérifier l'unicité

        return $pin;
    }

   

    /**
     * Supprimer une demande de livraison.
     */
    public function destroy($id): JsonResponse
    {
        $demande = DemandeLivraison::find($id);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande de livraison introuvable',
            ], 404);
        }

        if ($demande->colis->photo) {
            Storage::disk('public')->delete($demande->colis->photo);
        }

        $demande->delete();

        return response()->json([
            'success' => true,
            'message' => 'Demande de livraison supprimée avec succès',
        ], 200);
    }
    
    /**
     * Convertir le code d'erreur d'upload en message lisible
     */
    private function getUploadErrorMessage($errorCode): string
    {
        $errors = [
            UPLOAD_ERR_OK => 'No error',
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in HTML form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
        ];
        
        return $errors[$errorCode] ?? 'Unknown upload error';
    }
}

