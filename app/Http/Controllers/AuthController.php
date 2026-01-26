<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Client;
use App\Models\Livreur;
use App\Models\NotificationToken;
use App\Enums\NotificationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\PasswordResetToken;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\ResetPasswordMail;

use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Inscription d'un nouvel utilisateur
     */
    
public function register(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'nom'       => 'required|string|max:255',
        'prenom'    => 'required|string|max:255',
        'email'     => 'required|string|email|max:255|unique:users',
        'password'  => 'required|string|min:8',
        'telephone' => 'required|string|max:20|unique:users',
        'role'      => 'string|in:client,livreur,admin',
        'latitude'  => 'nullable|numeric|between:-90,90',
        'longitude' => 'nullable|numeric|between:-180,180',
        'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur de validation',
            'errors'  => $validator->errors(),
        ], 422);
    }

    $utilsController = new UtilsController();
    $photoPath = $utilsController->uploadPhoto($request, 'photo');

    try {
        DB::beginTransaction();

        $user = User::create([
            'nom'       => $request->nom,
            'prenom'    => $request->prenom,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'telephone' => $request->telephone,
            'role'      => $request->role ?? 'client',
            'latitude'  => $request->latitude,
            'longitude' => $request->longitude,
            'photo'     => $photoPath,
            'photo_url' => $photoPath ?? asset('storage/' . $photoPath),
            'actif'     => true,
        ]);

        // Créer l'enregistrement correspondant selon le rôle
        if ($user->role == 'client') {
            Client::create([
                'user_id' => $user->id,
                'status'  => 'active',
            ]);
        } elseif ($user->role == 'livreur') {
            // 🆕 AJOUT : Création automatique du livreur
            Livreur::create([
                'user_id' => $user->id,
                'demande_adhesions_id' => null, // Peut être null comme spécifié
                'type' => 'distributeur', // Valeur par défaut, peut être changée plus tard
                'desactiver' => false,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        DB::commit();

        // Charger les relations pour la réponse
        $user->load('client', 'livreur');
        
        return response()->json([
            'user'       => $user,
            'token'      => $token,
            'token_type' => 'Bearer'
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la création de l\'utilisateur',
            'error'   => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Connexion d'un utilisateur
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'     => 'nullable|email|required_without:telephone',
            'telephone' => 'nullable|string|required_without:email',
            'password' => 'string',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }
        	   

        try {
            $user = User::where(function ($query) use ($request) {
                if ($request->filled('email')) {
                    $query->where('email', $request->email);
                }
            
                if ($request->filled('telephone')) {
                    $query->orWhere('telephone', $request->telephone);
                }
            })->first();
            
           
           /* $user = User::where(column: 'email', $request->email)
            ->orWhere('telephone', $request->telephone)
            ->first();

            */

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Identifiants incorrects',
                ], 401);
            }

            if (! $user->actif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Compte désactivé',
                ], 403);
            }

            // Révoquer tous les tokens existants
            $user->tokens()->delete();

            $token = $user->createToken('auth_token')->plainTextToken;

	   $user->load('client', 'livreur');
            return response()->json(
                /*[
                'success' => true,
                'message' => 'Connexion réussie',
                'data'    =>
                */
                 [
                    'user'       => $user,
                    'token'      => $token,
                    'token_type' => 'Bearer'
                  
                ],
             200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la connexion',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Déconnexion d'un utilisateur
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Déconnexion de tous les appareils
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $request->user()->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion de tous les appareils réussie',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour le profil utilisateur
     */
 public function updateProfile(Request $request): JsonResponse
{
    $user = $request->user();
    
    // Loguer les données brutes
    \Log::info('Requête brute : ', [
        'input' => $request->all(),
        'files' => $request->files->all(),
        'headers' => $request->headers->all(),
    ]);

    $input = $request->all();
    
    \Log::info('Données reçues : ', $input);

    $validator = Validator::make($input, [
        'nom' => 'sometimes|required|string|max:255',
        'prenom' => 'sometimes|required|string|max:255',
        'email' => [
            'sometimes',
            'required',
            'string',
            'email',
            'max:255',
            Rule::unique('users', 'email')->ignore($user->id),
        ],
        'telephone' => [
            'sometimes',
            'required',
            'string',
            'max:20',
            Rule::unique('users', 'telephone')->ignore($user->id),
        ],
        'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
    ]);

    if ($validator->fails()) {
        \Log::error('Erreur de validation : ', $validator->errors()->toArray());
        return response()->json([
            'success' => false,
            'message' => 'Erreur de validation',
            'errors' => $validator->errors(),
        ], 422);
    }

    try {
        $user->update(array_filter($input, fn($value) => !is_null($value) && $value !== ''));

        \Log::info('Profil mis à jour : ', $user->toArray());
        return response()->json($user, 200);
    } catch (\Exception $e) {
        \Log::error('Erreur lors de la mise à jour : ', ['error' => $e->getMessage()]);
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour du profil',
            'error' => $e->getMessage(),
        ], 500);
    }
}
     /**
     * Mettre à jour sa photo de profil utilisateur
     */
    public function updatePhotoProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Validation de la photo
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();
            

            // Supprimer l'ancienne photo si elle existe
            if ($user->photo) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->photo);
            }

            // Stocker la nouvelle photo
            $utilsController = new UtilsController();
            $photoPath = $utilsController->uploadPhoto($request, 'photo');
        
            // Mettre à jour le chemin de la photo dans la base de données
            $user->update([
                'photo' => $photoPath,
            ]);

            /*
            $user = NotificationToken::find($user->id); // Replace with the user you want to notify
            $notificationData = [
                'title' => 'Photo de Profil mise à jour!',
                'body' => 'Votre photo de profil a été mise à jour avec succès.',
                'data' => ['type' => NotificationType::UPDATE_PHOTO, 'user_id'=>$user->id], // Additional data if needed
            ];
           

            $user->notify(new FcmNotification($notificationData));

            */

            return response()->json([
                'success' => true,
                'message' => 'Photo de Profil mis à jour avec succès',
                'data'    => $user,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la Photo de profil',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Changer le mot de passe
     */
    public function changePassword(Request $request): JsonResponse
    {
        \Log::info('Change password request:', $request->all());
        
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();

            if (! Hash::check($request->current_password, $user->password)) {
                \Log::warning('Current password mismatch for user: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Mot de passe actuel incorrect',
                ], 401);
            }

            $user->update([
                'password' => Hash::make($request->new_password),
            ]);
            
            \Log::info('Password changed successfully for user: ' . $user->id);

            // Révoquer tous les tokens existants pour forcer une nouvelle connexion
            $user->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe changé avec succès. Veuillez vous reconnecter.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de mot de passe',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Réinitialisation du mot de passe (envoi email)
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            // Générer un token unique de 60 caractères
            $token = Str::random(60);

            // Supprimer les anciens tokens pour cet email
            PasswordResetToken::where('email', $request->email)->delete();

            // Créer un nouveau token
            PasswordResetToken::create([
                'email' => $request->email,
                'token' => $token,
                'created_at' => now(),
            ]);

            // Envoyer l'email avec le lien de réinitialisation
            try {
                Mail::to($request->email)->send(new ResetPasswordMail($request->email, $token));
                \Log::info('Password reset email sent to: ' . $request->email);
            } catch (\Exception $mailError) {
                \Log::error('Erreur envoi email:', ['error' => $mailError->getMessage()]);
                // On continue même si l'email échoue, mais on log l'erreur
            }

            // Réponse pour l'utilisateur
            return response()->json([
                'success' => true,
                'message' => 'Email de réinitialisation envoyé. Veuillez vérifier votre boîte de réception.',
                'expires_in' => 1800, // 30 minutes en secondes
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Erreur forgotPassword:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du token',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 2. Vérifier si le token est valide
     * Route: POST /api/auth/verify-reset-token
     */
    public function verifyResetToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string|min:60|max:60',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Email ou token invalide',
            ], 422);
        }

        try {
            // Chercher le token
            $resetToken = PasswordResetToken::where('email', $request->email)
                ->where('token', $request->token)
                ->first();

            if (!$resetToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token ou email invalide',
                ], 404);
            }

            // Vérifier si le token n'est pas expiré
            if (!$resetToken->isValid()) {
                $resetToken->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Ce lien de réinitialisation a expiré (durée: 30 minutes)',
                ], 410); // 410 Gone
            }

            return response()->json([
                'success' => true,
                'message' => 'Token valide',
                'email' => $request->email,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Erreur verifyResetToken:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification du token',
            ], 500);
        }
    }

    /**
     * 3. Réinitialiser le mot de passe
     * Route: POST /api/auth/reset-password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'                 => 'required|email|exists:users,email',
            'token'                 => 'required|string|min:60|max:60',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            // Vérifier le token
            $resetToken = PasswordResetToken::where('email', $request->email)
                ->where('token', $request->token)
                ->first();

            if (!$resetToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token ou email invalide',
                ], 404);
            }

            // Vérifier l'expiration
            if (!$resetToken->isValid()) {
                $resetToken->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Ce lien de réinitialisation a expiré',
                ], 410);
            }

            // Chercher l'utilisateur
            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouvé',
                ], 404);
            }

            // Mettre à jour le mot de passe
            $user->update([
                'password' => Hash::make($request->password),
            ]);

            // Révoquer tous les tokens Sanctum (déconnecter l'utilisateur de tous les appareils)
            $user->tokens()->delete();

            // Supprimer le token de réinitialisation
            $resetToken->delete();

            \Log::info('Password reset successfully for: ' . $request->email);

            return response()->json([
                'success' => true,
                'message' => 'Votre mot de passe a été réinitialisé avec succès. Veuillez vous connecter.',
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Erreur resetPassword:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation du mot de passe',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    

    /**
     * Vérifier si un token est valide
     */
    public function verifyToken(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token invalide',
                ], 401);
            }

            return response()->json([
                'success' => true,
                'message' => 'Token valide',
                'data'    => $user,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification du token',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour le rôle de l'utilisateur
     */
    public function updateRole(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'role' => 'required|string|in:admin,user,client', // Remplacez par les rôles valides de votre application
        ]);

        try {
            $user = $request->user();
            $user->update([
                'role' => $validatedData['role'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rôle mis à jour avec succès',
                'data'    => $user,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du rôle',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    
    
    public function deleteAccount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();

            // Vérifier le mot de passe
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mot de passe incorrect',
                ], 401);
            }

            // Récupérer l'ID avant suppression
            $userId = $user->id;

            DB::beginTransaction();

            // Supprimer le Client s'il existe
            Client::where('user_id', $userId)->delete();

            // Supprimer le Livreur s'il existe
            Livreur::where('user_id', $userId)->delete();

            // Supprimer les tokens
            $user->tokens()->delete();

            // Supprimer la photo s'il existe
            if ($user->photo) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->photo);
            }

            // Supprimer l'utilisateur
            $user->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Compte supprimé avec succès',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du compte',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    
}
