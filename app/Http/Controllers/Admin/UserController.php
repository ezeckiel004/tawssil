<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\Client;
use App\Models\Livreur;
use App\Models\Gestionnaire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\UsersExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class UserController extends Controller
{
    /**
     * Vérifier si l'utilisateur est admin
     */
    private function checkAdmin(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Admin requis.',
            ], 403);
        }
        return null;
    }

    /**
     * Vérifier si l'utilisateur est admin OU s'il s'agit de son propre profil
     */
    private function checkAuthorization(Request $request, $userId = null)
    {
        $user = $request->user();

        // Si l'utilisateur est admin, tout est autorisé
        if ($user->role === 'admin') {
            return null;
        }

        // Si l'utilisateur essaie d'accéder à son propre profil
        if ($userId && $user->id == $userId) {
            return null;
        }

        // Sinon, accès refusé
        return response()->json([
            'success' => false,
            'message' => 'Accès non autorisé. Vous devez être administrateur ou propriétaire du compte.',
        ], 403);
    }

    /**
     * Récupérer tous les utilisateurs du système (Admin seulement)
     */
    public function getAllUsers(Request $request): JsonResponse
    {
        // Vérifier si admin
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        try {
            // Récupérer tous les utilisateurs avec leurs relations
            $users = User::with(['client', 'livreur.demandeAdhesion', 'gestionnaire'])->get();

            return response()->json([
                'success' => true,
                'data' => $users,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des utilisateurs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Récupérer un utilisateur spécifique par ID
     */
    public function show(Request $request, $id): JsonResponse
    {
        // Vérifier l'autorisation
        $authCheck = $this->checkAuthorization($request, $id);
        if ($authCheck) {
            return $authCheck;
        }

        try {
            $user = User::with(['client', 'livreur.demandeAdhesion', 'gestionnaire'])->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur introuvable',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $user,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'utilisateur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour les positions latitude et longitude de l'utilisateur.
     */
    public function updatePosition(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        try {
            $user = $request->user();

            $user->update([
                'latitude' => $validatedData['latitude'],
                'longitude' => $validatedData['longitude'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Position mise à jour avec succès',
                'data' => $user,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la position',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprimer un utilisateur (soft delete)
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        // Vérifier si admin
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Ne pas permettre de supprimer son propre compte
        if ($user->id == $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 400);
        }

        // Soft delete
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur supprimé avec succès',
        ], 200);
    }

    /**
     * Activer ou désactiver un utilisateur (Admin seulement)
     */
    public function toggleActivation(Request $request, $id): JsonResponse
    {
        // Vérifier si admin
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        try {
            // Inverser le statut actif
            $user->update([
                'actif' => !$user->actif,
            ]);

            return response()->json([
                'success' => true,
                'message' => $user->actif ? 'Utilisateur activé avec succès' : 'Utilisateur désactivé avec succès',
                'data' => $user,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Créer un nouvel utilisateur (Admin seulement)
     */
    public function store(Request $request): JsonResponse
    {
        // Vérifier si admin
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        $validatedData = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'telephone' => 'required|string|unique:users,telephone',
            'password' => 'required|string|min:8',
            'role' => 'required|in:client,livreur,admin,gestionnaire',
            'wilaya_id' => 'nullable|string|max:10',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        try {
            DB::beginTransaction();

            // Créer l'utilisateur
            $user = User::create([
                'nom' => $validatedData['nom'],
                'prenom' => $validatedData['prenom'],
                'email' => $validatedData['email'],
                'telephone' => $validatedData['telephone'],
                'password' => Hash::make($validatedData['password']),
                'role' => $validatedData['role'],
                'latitude' => $validatedData['latitude'] ?? null,
                'longitude' => $validatedData['longitude'] ?? null,
                'actif' => true,
            ]);

            // Créer le record correspondant selon le rôle
            if ($user->role == 'client') {
                Client::create([
                    'user_id' => $user->id,
                    'status' => 'active',
                ]);
            } elseif ($user->role == 'livreur') {
                Livreur::create([
                    'user_id' => $user->id,
                    'type' => 'distributeur',
                    'desactiver' => false,
                ]);
            } elseif ($user->role == 'gestionnaire') {
                Gestionnaire::create([
                    'user_id' => $user->id,
                    'wilaya_id' => $validatedData['wilaya_id'] ?? '16',
                    'status' => 'active',
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur créé avec succès',
                'data' => $user->load(['client', 'livreur', 'gestionnaire']),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'utilisateur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour les informations d'un utilisateur
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $targetUser = User::find($id);

        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Vérifier l'autorisation
        $authCheck = $this->checkAuthorization($request, $id);
        if ($authCheck) {
            return $authCheck;
        }

        // Définir les règles de validation selon le rôle
        $validationRules = [
            'nom' => 'nullable|string|max:255',
            'prenom' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'telephone' => 'nullable|string|unique:users,telephone,' . $id,
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ];

        // Seul l'admin peut modifier le rôle et le statut
        if ($user->role === 'admin') {
            $validationRules['role'] = 'nullable|in:client,livreur,admin,gestionnaire';
            $validationRules['actif'] = 'nullable|boolean';
            $validationRules['wilaya_id'] = 'nullable|string|max:10';
        }

        try {
            $validatedData = $request->validate($validationRules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Nettoyer les données (supprimer les valeurs nulles)
            $cleanData = array_filter($validatedData, function ($value) {
                return !is_null($value) && $value !== '';
            });

            // Sauvegarder l'ancien rôle pour vérifier les changements
            $oldRole = $targetUser->role;
            $newRole = $cleanData['role'] ?? $oldRole;

            $targetUser->update($cleanData);

            // Gestion des changements de rôle ou mise à jour des tables associées
            if ($newRole == 'gestionnaire') {
                // Si l'utilisateur devient gestionnaire ou est déjà gestionnaire
                if ($oldRole != 'gestionnaire') {
                    // Supprimer les anciennes relations si changement de rôle
                    Client::where('user_id', $targetUser->id)->delete();
                    Livreur::where('user_id', $targetUser->id)->delete();

                    // Créer le gestionnaire
                    Gestionnaire::updateOrCreate(
                        ['user_id' => $targetUser->id],
                        [
                            'wilaya_id' => $validatedData['wilaya_id'] ?? '16',
                            'status' => 'active',
                        ]
                    );
                } else {
                    // Mettre à jour le gestionnaire existant
                    Gestionnaire::updateOrCreate(
                        ['user_id' => $targetUser->id],
                        ['wilaya_id' => $validatedData['wilaya_id'] ?? '16']
                    );
                }
            } elseif ($newRole == 'client' && $oldRole != 'client') {
                // Supprimer les autres relations
                Livreur::where('user_id', $targetUser->id)->delete();
                Gestionnaire::where('user_id', $targetUser->id)->delete();

                Client::updateOrCreate(
                    ['user_id' => $targetUser->id],
                    ['status' => 'active']
                );
            } elseif ($newRole == 'livreur' && $oldRole != 'livreur') {
                // Supprimer les autres relations
                Client::where('user_id', $targetUser->id)->delete();
                Gestionnaire::where('user_id', $targetUser->id)->delete();

                Livreur::updateOrCreate(
                    ['user_id' => $targetUser->id],
                    [
                        'type' => 'distributeur',
                        'desactiver' => false,
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur mis à jour avec succès',
                'data' => $targetUser->load(['client', 'livreur', 'gestionnaire']),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'utilisateur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques des utilisateurs (Admin seulement)
     */
    public function stats(Request $request): JsonResponse
    {
        // Vérifier si admin
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        try {
            $totalUsers = User::count();
            $totalClients = User::where('role', 'client')->count();
            $totalLivreurs = User::where('role', 'livreur')->count();
            $totalAdmins = User::where('role', 'admin')->count();
            $totalGestionnaires = User::where('role', 'gestionnaire')->count();
            $activeUsers = User::where('actif', 1)->count();
            $inactiveUsers = User::where('actif', 0)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_users' => $totalUsers,
                    'total_clients' => $totalClients,
                    'total_livreurs' => $totalLivreurs,
                    'total_admins' => $totalAdmins,
                    'total_gestionnaires' => $totalGestionnaires,
                    'active_users' => $activeUsers,
                    'inactive_users' => $inactiveUsers,
                    'percentages' => [
                        'clients' => $totalUsers > 0 ? round(($totalClients / $totalUsers) * 100, 2) : 0,
                        'livreurs' => $totalUsers > 0 ? round(($totalLivreurs / $totalUsers) * 100, 2) : 0,
                        'admins' => $totalUsers > 0 ? round(($totalAdmins / $totalUsers) * 100, 2) : 0,
                        'gestionnaires' => $totalUsers > 0 ? round(($totalGestionnaires / $totalUsers) * 100, 2) : 0,
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Chercher des utilisateurs par terme (Admin seulement)
     */
    public function search(Request $request): JsonResponse
    {
        // Vérifier si admin
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        $query = $request->query('q', '');
        $role = $request->query('role', null);

        try {
            $users = User::where(function ($q) use ($query) {
                $q->where('nom', 'like', "%$query%")
                    ->orWhere('prenom', 'like', "%$query%")
                    ->orWhere('email', 'like', "%$query%")
                    ->orWhere('telephone', 'like', "%$query%");
            })
                ->when($role, function ($q) use ($role) {
                    return $q->where('role', $role);
                })
                ->with(['client', 'livreur.demandeAdhesion', 'gestionnaire'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $users,
                'count' => count($users),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques d'un client
     */
    public function getClientStats(Request $request, $id): JsonResponse
    {
        // Vérifier si admin ou propriétaire
        if ($request->user()->role !== 'admin' && $request->user()->id != $id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        try {
            $user = User::find($id);

            if (!$user || $user->role !== 'client') {
                return response()->json([
                    'success' => false,
                    'message' => 'Client introuvable',
                ], 404);
            }

            // À implémenter selon votre logique métier
            $stats = [
                'total_livraisons' => 0,
                'livraisons_en_cours' => 0,
                'livraisons_terminees' => 0,
                'montant_total' => 0,
                'derniere_livraison' => null,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques d'un livreur
     */
    public function getLivreurStats(Request $request, $id): JsonResponse
    {
        // Vérifier si admin ou propriétaire
        if ($request->user()->role !== 'admin' && $request->user()->id != $id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        try {
            $user = User::find($id);

            if (!$user || $user->role !== 'livreur') {
                return response()->json([
                    'success' => false,
                    'message' => 'Livreur introuvable',
                ], 404);
            }

            // À implémenter selon votre logique métier
            $stats = [
                'total_livraisons' => 0,
                'livraisons_en_attente' => 0,
                'livraisons_en_cours' => 0,
                'livraisons_terminees' => 0,
                'note_moyenne' => 0,
                'revenu_total' => 0,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour le statut d'un utilisateur (avec desactiver)
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        // Vérifier si admin
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        $validatedData = $request->validate([
            'desactiver' => 'required|boolean',
        ]);

        try {
            $user->update([
                'desactiver' => $validatedData['desactiver'],
                'actif' => !$validatedData['desactiver'],
            ]);

            return response()->json([
                'success' => true,
                'message' => $validatedData['desactiver'] ? 'Utilisateur désactivé avec succès' : 'Utilisateur activé avec succès',
                'data' => $user,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprimer définitivement un utilisateur de la base de données (Admin seulement)
     */
    public function deleteUser(Request $request, $id): JsonResponse
    {
        // Vérifier si admin
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        try {
            DB::beginTransaction();

            $user = User::withTrashed()->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur introuvable',
                ], 404);
            }

            // Ne pas permettre de supprimer son propre compte
            if ($user->id == $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
                ], 400);
            }

            // Supprimer les données associées
            if ($user->role === 'client') {
                Client::where('user_id', $user->id)->forceDelete();
            } elseif ($user->role === 'livreur') {
                Livreur::where('user_id', $user->id)->forceDelete();
            } elseif ($user->role === 'gestionnaire') {
                Gestionnaire::where('user_id', $user->id)->forceDelete();
            }

            // Suppression définitive
            $user->forceDelete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur supprimé définitivement.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exporter les utilisateurs en Excel, CSV ou PDF
     */
    public function exportExcel(Request $request)
    {
        // Vérifier si admin
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        try {
            // Récupérer les paramètres de filtrage
            $search = $request->query('search', '');
            $role = $request->query('role', '');
            $format = $request->query('format', 'xlsx');

            \Log::info('Export demandé avec format:', [
                'format' => $format,
                'search' => $search,
                'role' => $role
            ]);

            // Si format PDF, utiliser la méthode dédiée
            if ($format === 'pdf') {
                return $this->exportPDF($request);
            }

            // Générer un nom de fichier unique
            $filename = 'users-export-' . Carbon::now()->format('Y-m-d-H-i-s') . '.' . $format;

            // Créer l'instance d'export avec les filtres
            $export = new UsersExport($search, $role);

            // Exporter selon le format demandé
            if ($format === 'csv') {
                return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV);
            }

            return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::XLSX);
        } catch (\Exception $e) {
            \Log::error('Erreur exportExcel: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir les paramètres pour un export asynchrone
     */
    public function exportUsers(Request $request): JsonResponse
    {
        // Vérifier si admin
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        try {
            // Valider les paramètres
            $validated = $request->validate([
                'search' => 'nullable|string|max:255',
                'role' => 'nullable|in:client,livreur,admin,gestionnaire',
                'status' => 'nullable|in:active,inactive',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'columns' => 'nullable|array',
                'columns.*' => 'in:id,nom,prenom,email,telephone,role,actif,created_at,updated_at',
                'format' => 'nullable|in:xlsx,csv,pdf',
            ]);

            // Préparer les paramètres pour l'export
            $params = [
                'search' => $validated['search'] ?? '',
                'role' => $validated['role'] ?? '',
                'status' => $validated['status'] ?? '',
                'start_date' => $validated['start_date'] ?? '',
                'end_date' => $validated['end_date'] ?? '',
                'columns' => $validated['columns'] ?? [
                    'id',
                    'nom',
                    'prenom',
                    'email',
                    'telephone',
                    'role',
                    'actif',
                    'created_at'
                ],
                'format' => $validated['format'] ?? 'xlsx',
            ];

            // Générer le token d'export
            $exportToken = 'export_' . md5(serialize($params) . time());

            // Stocker les paramètres dans le cache (1 heure)
            cache()->put($exportToken, $params, 3600);

            return response()->json([
                'success' => true,
                'message' => 'Export programmé avec succès',
                'data' => [
                    'export_token' => $exportToken,
                    'download_url' => url("/api/admin/users/export/download/{$exportToken}"),
                ]
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Erreur exportUsers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la préparation de l\'export',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Télécharger un export précédemment généré
     */
    public function downloadExport($token)
    {
        try {
            // Récupérer les paramètres depuis le cache
            $params = cache()->get($token);

            if (!$params) {
                return response()->json([
                    'success' => false,
                    'message' => 'Export non trouvé ou expiré',
                ], 404);
            }

            \Log::info('Téléchargement export avec token:', ['token' => $token]);

            // Supprimer le token après utilisation
            cache()->forget($token);

            // Générer le nom de fichier
            $filename = 'users-export-' . Carbon::now()->format('Y-m-d-H-i-s') . '.' . $params['format'];

            // Créer l'export avec les paramètres
            $export = new UsersExport(
                $params['search'],
                $params['role'],
                $params['status'],
                $params['start_date'],
                $params['end_date'],
                $params['columns']
            );

            // Télécharger selon le format
            if ($params['format'] === 'csv') {
                return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV);
            }

            return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::XLSX);
        } catch (\Exception $e) {
            \Log::error('Erreur downloadExport: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du téléchargement: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exporter les utilisateurs en PDF (CORRIGÉ)
     */
    public function exportPDF(Request $request)
    {
        // Vérifier si admin
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        try {
            // Récupérer les paramètres de filtrage
            $search = $request->query('search', '');
            $role = $request->query('role', '');

            // Récupérer les colonnes à afficher
            $columns = $request->query('columns', []);

            // Si aucune colonne spécifiée, utiliser les colonnes par défaut
            if (empty($columns)) {
                $columns = ['id', 'nom', 'prenom', 'email', 'telephone', 'role', 'actif', 'created_at'];
            } elseif (is_string($columns)) {
                $columns = explode(',', $columns);
            } elseif ($request->has('columns') && !is_array($columns)) {
                $columns = [$columns];
            }

            \Log::info('Export PDF avec paramètres:', [
                'search' => $search,
                'role' => $role,
                'columns' => $columns
            ]);

            // Récupérer les utilisateurs avec filtres
            $query = User::query()
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($query) use ($search) {
                        $query->where('nom', 'like', "%{$search}%")
                            ->orWhere('prenom', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('telephone', 'like', "%{$search}%");
                    });
                })
                ->when($role, function ($q) use ($role) {
                    $q->where('role', $role);
                })
                ->orderBy('created_at', 'desc');

            $users = $query->get();

            \Log::info('Nombre d\'utilisateurs trouvés:', ['count' => $users->count()]);

            // Calculer les statistiques
            $stats = [
                'total_users' => $users->count(),
                'active_users' => $users->where('actif', true)->count(),
                'inactive_users' => $users->where('actif', false)->count(),
                'total_clients' => $users->where('role', 'client')->count(),
                'total_livreurs' => $users->where('role', 'livreur')->count(),
                'total_admins' => $users->where('role', 'admin')->count(),
                'total_gestionnaires' => $users->where('role', 'gestionnaire')->count(),
            ];

            // Préparer les données pour la vue
            $data = [
                'users' => $users,
                'stats' => $stats,
                'filters' => [
                    'search' => $search,
                    'role' => $role,
                ],
                'columns' => $columns, // Important: passer les colonnes à la vue
                'date' => Carbon::now()->format('d/m/Y H:i'),
            ];

            // Générer le PDF
            $pdf = Pdf::loadView('pdf.users', $data);
            $pdf->setPaper('A4', 'landscape');
            $pdf->setOptions([
                'defaultFont' => 'Arial',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'dpi' => 150,
            ]);

            // Générer le nom de fichier
            $filename = 'users-export-' . Carbon::now()->format('Y-m-d-H-i-s') . '.pdf';

            \Log::info('PDF généré avec succès, téléchargement...');

            return $pdf->download($filename);
        } catch (\Exception $e) {
            \Log::error('Erreur exportPDF: ' . $e->getMessage());
            \Log::error('Trace:', $e->getTrace());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du PDF: ' . $e->getMessage(),
            ], 500);
        }
    }
}
