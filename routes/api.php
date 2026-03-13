<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DemandeLivraisonController;
use App\Http\Controllers\LivraisonController;
use App\Http\Controllers\LivreurController;
use App\Http\Controllers\LivreurStatsController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DemandeAdhesionController;
use App\Http\Controllers\AvisController;
use App\Http\Controllers\ResponseAvisController;
use App\Http\Controllers\CommentaireController;
use App\Http\Controllers\BordereauController;
use App\Http\Controllers\LivreurCourseController;
use App\Http\Controllers\NotificationTokenController;
use App\Http\Controllers\NotificationHistoriqueController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WilayaController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\LivraisonController as AdminLivraisonController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\NavetteController;
use App\Http\Controllers\Admin\ComptabiliteController;
use App\Http\Controllers\Admin\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ==================== ROUTES PUBLIQUES ====================
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthorized.'], 401);
})->name('login');

// Wilayas et communes (publiques)
Route::get('/wilayas', [WilayaController::class, 'index']);
Route::get('/wilayas/{wilayaCode}/communes', [WilayaController::class, 'communes']);

// Tracking public
Route::get('/livraisons/track/{colis_label}', [LivraisonController::class, 'trackByColisLabel']);

// ==================== AUTHENTIFICATION ====================
Route::prefix('auth')->group(function () {
    // Routes publiques
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-reset-token', [AuthController::class, 'verifyResetToken']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Routes protégées
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/verify-token', [AuthController::class, 'verifyToken']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/profile/photo', [AuthController::class, 'updatePhotoProfile']);
        Route::put('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });
});

// ==================== ROUTES PROTÉGÉES (AUTH:SANCTUM) ====================
Route::middleware('auth:sanctum')->group(function () {

    // ======== UTILISATEURS ========
    Route::apiResource('users', UserController::class);
    Route::get('/all-users', [UserController::class, 'getAllUsers']);
    Route::get('users/search', [UserController::class, 'search']);
    Route::get('users/stats', [UserController::class, 'stats']);
    Route::get('users/{id}/stats/client', [UserController::class, 'getClientStats']);
    Route::get('users/{id}/stats/livreur', [UserController::class, 'getLivreurStats']);
    Route::patch('users/{id}/toggle-activation', [UserController::class, 'toggleActivation']);
    Route::get('/users/show/{id}', [UserController::class, 'show']);
    Route::delete('/delete/{id}', [UserController::class, 'destroy']);

    // ======== POSITION GPS ========
    Route::patch('/update-position', [UserController::class, 'updatePosition']);
    Route::patch('user/position', [AuthController::class, 'updatePosition']);
    Route::patch('/update/photo', [AuthController::class, 'updatePhoto']);

    // ======== NOTIFICATIONS ========
    Route::post('/users/{userId}/fcm-token', [NotificationTokenController::class, 'store']);
    Route::get('/users/{user}/notifications', [NotificationHistoriqueController::class, 'index']);
    Route::post('/users/{user}/notifications/{notification}/read', [NotificationHistoriqueController::class, 'markAsRead']);

    // ======== DEMANDES DE LIVRAISON ========
    Route::apiResource('demandes-livraison', DemandeLivraisonController::class);

    // ======== LIVRAISONS ========
    Route::apiResource('livraisons', LivraisonController::class);
    Route::get('livraisons/en-cours', [LivraisonController::class, 'livraisonsEnCours']);
    Route::get('livraisons/toutes-en-cours', [LivraisonController::class, 'toutesLivraisonsEnCours']);
    Route::patch('livraisons/{id}/status', [LivraisonController::class, 'updateStatus']);
    Route::patch('livraisons/{id}/assign-livreur', [LivraisonController::class, 'assignLivreur']);
    Route::patch('livraisons/{id}/destroy_by_client', [LivraisonController::class, 'destroyByClient']);
    Route::get('livraisons/{id}/statistiques', [LivraisonController::class, 'statistiquesClient']);
    Route::get('livraisons/{id}/statistiques/livreur', [LivraisonController::class, 'statistiquesLivreur']);
    Route::get('livraisons/getByClient/{id}', [LivraisonController::class, 'getByClient']);
    Route::get('livraisons/getByLivreur/{id}', [LivraisonController::class, 'getByLivreur']);
    Route::get('livraisons/client/{id}/en-cours', [LivraisonController::class, 'livraisonsClientEnCours']);
    Route::get('livraisons/livreur/{id}/en-cours', [LivraisonController::class, 'livraisonsLivreurEnCours']);

    // ======== ROUTES DYNAMIQUES DE STATUTS (Livreur) ========
    Route::patch('livraisons/{id}/status-by-livreur', [LivreurCourseController::class, 'updateStatusByLivreurType']);
    Route::get('livraisons/{id}/valid-transitions', [LivreurCourseController::class, 'getValidTransitions']);
    Route::get('livraisons/by-status-and-type/{status}', [LivreurCourseController::class, 'getByStatusAndLivreurType']);
    Route::get('livraisons/statistics-by-status', [LivreurCourseController::class, 'getStatistiquesByStatus']);

    // ======== BORDEREAUX ========
    Route::apiResource('bordereaux', BordereauController::class);
    Route::get('livraisons/{id}/bordereau-pdf', [LivraisonController::class, 'generateBordereauPDF']);
    Route::get('livraisons/{id}/print-html', [LivraisonController::class, 'generatePrintHTML']);

    // ======== LIVREURS ========
    Route::apiResource('livreurs', LivreurController::class);
    Route::patch('livreurs/{id}/toggle-activation', [LivreurController::class, 'toggleActivation']);

    // ======== CLIENTS ========
    Route::apiResource('clients', ClientController::class);

    // ======== DEMANDES D'ADHÉSION ========
    Route::apiResource('demandes-adhesion', DemandeAdhesionController::class);
    Route::patch('demandes-adhesion/{id}/status', [DemandeAdhesionController::class, 'updateStatus']);
    Route::get('demandes-adhesion/by-status/{status}', [DemandeAdhesionController::class, 'getByStatus']);

    // ======== AVIS ET COMMENTAIRES ========
    Route::apiResource('avis', AvisController::class);
    Route::apiResource('reponses-avis', ResponseAvisController::class);
    Route::apiResource('commentaires', CommentaireController::class);

    // ======== SUPPRESSION DE COMPTE ========
    Route::post('/auth/delete-account', [AuthController::class, 'deleteAccount']);

    // ==================== ROUTES ADMIN ====================
    Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {

        // ======== DASHBOARD ========
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/charts', [DashboardController::class, 'charts']);

        // ======== LIVRAISONS ========
        Route::post('livraisons', [AdminLivraisonController::class, 'store']);
        Route::get('livraisons', [AdminLivraisonController::class, 'index']);
        Route::get('livraisons/{id}', [AdminLivraisonController::class, 'show']);
        Route::put('livraisons/{id}', [AdminLivraisonController::class, 'update']);
        Route::delete('livraisons/{id}', [AdminLivraisonController::class, 'destroy']);
        Route::get('livraisons/statistics', [AdminLivraisonController::class, 'statistics']);
        Route::get('livraisons/search', [AdminLivraisonController::class, 'search']);
        Route::patch('livraisons/{id}/assign-livreur', [AdminLivraisonController::class, 'assignLivreur']);
        Route::patch('livraisons/{id}/status', [AdminLivraisonController::class, 'updateStatus']);
        Route::get('livraisons/stats/general', [AdminLivraisonController::class, 'statistiquesGenerales']);
        Route::get('livraisons/en-attente', [AdminLivraisonController::class, 'livraisonsEnAttente']);
        Route::get('livraisons/terminees', [AdminLivraisonController::class, 'livraisonsTerminees']);
        Route::get('livraisons/annulees', [AdminLivraisonController::class, 'livraisonsAnnulees']);
        Route::get('livraisons/en-cours', [AdminLivraisonController::class, 'livraisonsEnCours']);
        Route::get('livraisons/getByClient/{id}', [AdminLivraisonController::class, 'getByClient']);
        Route::get('livraisons/getByLivreur/{id}', [AdminLivraisonController::class, 'getByLivreur']);

        // ======== BORDEREAUX PDF ========
        Route::get('livraisons/{id}/bordereau-pdf', [AdminLivraisonController::class, 'generateBordereauPDF']);
        Route::get('livraisons/{id}/print-html', [AdminLivraisonController::class, 'generatePrintHTML']);
        Route::get('livraisons/{id}/print-etiquette', [LivraisonController::class, 'generatePrintBordereau']);

        // ======== EXPORTS ========
        Route::get('users/export/excel', [AdminUserController::class, 'exportExcel']);
        Route::post('users/export', [AdminUserController::class, 'exportUsers']);
        Route::get('users/export/download/{token}', [AdminUserController::class, 'downloadExport']);
        Route::get('livraisons/export/excel', [AdminLivraisonController::class, 'exportExcel']);
        Route::post('livraisons/export', [AdminLivraisonController::class, 'exportLivraisons']);
        Route::get('livraisons/export/download/{token}', [AdminLivraisonController::class, 'downloadExport']);

        // ======== UTILISATEURS (ADMIN) ========
        // Routes CRUD complètes pour les utilisateurs
        Route::get('/users', [AdminUserController::class, 'getAllUsers']);           // GET /admin/users - Liste
        Route::post('/users', [AdminUserController::class, 'store']);                // POST /admin/users - Création
        Route::get('/users/{id}', [AdminUserController::class, 'show']);             // GET /admin/users/{id} - Détail
        Route::put('/users/{id}', [AdminUserController::class, 'update']);           // PUT /admin/users/{id} - Mise à jour
        Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);       // DELETE /admin/users/{id} - Soft delete
        Route::delete('/users/{id}/force-delete', [AdminUserController::class, 'deleteUser']); // DELETE /admin/users/{id}/force-delete - Suppression définitive
        Route::patch('/users/{id}/toggle-activation', [AdminUserController::class, 'toggleActivation']); // PATCH /admin/users/{id}/toggle-activation - Activer/désactiver
        Route::get('/users/stats', [AdminUserController::class, 'stats']);           // GET /admin/users/stats - Statistiques
        Route::get('/users/search', [AdminUserController::class, 'search']);         // GET /admin/users/search - Recherche
        Route::get('/users/{id}/stats/client', [AdminUserController::class, 'getClientStats']); // GET /admin/users/{id}/stats/client - Stats client
        Route::get('/users/{id}/stats/livreur', [AdminUserController::class, 'getLivreurStats']); // GET /admin/users/{id}/stats/livreur - Stats livreur

        // ======== NAVETTES ========
        Route::prefix('navettes')->group(function () {
            // Routes principales
            Route::get('/', [NavetteController::class, 'index']);
            Route::post('/', [NavetteController::class, 'store']);
            Route::get('/suggestions', [NavetteController::class, 'suggestions']);
            Route::post('/creer-optimisee', [NavetteController::class, 'creerOptimisee']);
            Route::get('/statistiques', [NavetteController::class, 'statistiques']);
            Route::get('/export-pdf', [NavetteController::class, 'exportPDF']);

            // Routes pour une navette spécifique
            Route::get('/{id}', [NavetteController::class, 'show']);
            Route::put('/{id}', [NavetteController::class, 'update']);
            Route::delete('/{id}', [NavetteController::class, 'destroy']);

            // Actions sur une navette
            Route::post('/{id}/demarrer', [NavetteController::class, 'demarrer']);
            Route::post('/{id}/terminer', [NavetteController::class, 'terminer']);
            Route::post('/{id}/annuler', [NavetteController::class, 'annuler']);

            // Gestion des colis dans une navette
            Route::post('/{id}/colis', [NavetteController::class, 'ajouterColis']);
            Route::delete('/{id}/colis', [NavetteController::class, 'retirerColis']);
        });

        // ======== COMPTABILITÉ - BILANS ========
        Route::prefix('comptabilite')->group(function () {

            // BILAN GLOBAL (admin uniquement)
            Route::get('/bilan-global', [ComptabiliteController::class, 'bilanGlobal']);
            Route::get('/bilan-global/export', [ComptabiliteController::class, 'exportBilanGlobal']);

            // BILAN PAR GESTIONNAIRE
            Route::get('/bilan-gestionnaire', [ComptabiliteController::class, 'bilanGestionnaire']);
            Route::get('/bilan-gestionnaire/export', [ComptabiliteController::class, 'exportBilanGestionnaire']);

            // Admin peut voir le bilan d'une wilaya spécifique
            Route::get('/bilan-wilaya/{wilayaId}', [ComptabiliteController::class, 'bilanGestionnaire']);
            Route::get('/bilan-wilaya/{wilayaId}/export', [ComptabiliteController::class, 'exportBilanGestionnaire']);

            // Anciennes routes (optionnel, à garder pour compatibilité ou supprimer)
            Route::get('/dashboard', [ComptabiliteController::class, 'dashboard'])->withoutMiddleware(['role:admin']);
            Route::get('/rapport', [ComptabiliteController::class, 'rapport'])->withoutMiddleware(['role:admin']);
            Route::get('/evolution-mensuelle', [ComptabiliteController::class, 'evolutionMensuelle'])->withoutMiddleware(['role:admin']);
            Route::post('/calculer-gains', [ComptabiliteController::class, 'calculerGains'])->withoutMiddleware(['role:admin']);
            Route::get('/export', [ComptabiliteController::class, 'export'])->withoutMiddleware(['role:admin']);
        });
    });

    // ==================== ROUTES LIVREUR (MOBILE) ====================
    Route::prefix('livreur')->group(function () {
        Route::get('courses', [LivreurCourseController::class, 'index']);
        Route::get('courses/count', [LivreurCourseController::class, 'count']);
        Route::get('courses/status/{status}', [LivreurCourseController::class, 'byStatus']);
        Route::get('courses/colis', [LivreurCourseController::class, 'colis']);
        Route::get('courses/{id}', [LivreurCourseController::class, 'show']);
        Route::post('courses/{id}/complete', [LivreurCourseController::class, 'complete']);
        Route::get('stats/dashboard', [LivreurStatsController::class, 'dashboard']);
        Route::get('stats/detailed', [LivreurStatsController::class, 'detailedStats']);
    });

    // ==================== ROUTES GESTIONNAIRE (PRESTATAIRE) ====================
    Route::prefix('manager')->middleware(['gestionnaire'])->group(function () {

        // Dashboard
        Route::get('dashboard', [App\Http\Controllers\Manager\DashboardController::class, 'index']);

        // Profil
        Route::prefix('profile')->group(function () {
            Route::get('/', [App\Http\Controllers\Manager\ProfileController::class, 'show']);
            Route::put('/', [App\Http\Controllers\Manager\ProfileController::class, 'update']);
            Route::post('change-password', [App\Http\Controllers\Manager\ProfileController::class, 'changePassword']);
        });

        // Gestion des livraisons (pas de delete)
        Route::prefix('livraisons')->group(function () {
            Route::get('/', [App\Http\Controllers\Manager\LivraisonController::class, 'index']);
            Route::get('search', [App\Http\Controllers\Manager\LivraisonController::class, 'search']);
            Route::get('status/{status}', [App\Http\Controllers\Manager\LivraisonController::class, 'byStatus']);
            Route::get('{id}', [App\Http\Controllers\Manager\LivraisonController::class, 'show']);
            Route::patch('{id}/status', [App\Http\Controllers\Manager\LivraisonController::class, 'updateStatus']);
        });

        // Gestion des livreurs (pas de delete)
        Route::prefix('livreurs')->group(function () {
            Route::get('/', [App\Http\Controllers\Manager\LivreurController::class, 'index']);
            Route::get('{id}', [App\Http\Controllers\Manager\LivreurController::class, 'show']);
            Route::patch('{id}/toggle-activation', [App\Http\Controllers\Manager\LivreurController::class, 'toggleActivation']);
        });

        // Gestion des codes promo (CRUD complet)
        Route::apiResource('codes-promo', App\Http\Controllers\Manager\CodePromoController::class);

        // Routes supplémentaires pour les codes promo
        Route::prefix('codes-promo/{id}')->group(function () {
            Route::post('add-livreurs', [App\Http\Controllers\Manager\CodePromoController::class, 'addLivreurs']);
            Route::delete('remove-livreurs', [App\Http\Controllers\Manager\CodePromoController::class, 'removeLivreurs']);
        });
    });
});

// ==================== ROUTES PUBLIQUES SUPPLÉMENTAIRES ====================
Route::get('livraisons/{id}/bordereau-pdf', [LivraisonController::class, 'generateBordereauPDF']);
Route::get('livraisons/{id}/print-html', [LivraisonController::class, 'generatePrintHTML']);
