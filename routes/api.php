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
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\LivraisonController as AdminLivraisonController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Routes d'authentification publiques
Route::prefix('auth')->group(function () {
    // Authentification
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    // Mot de passe oublié (routes publiques)
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-reset-token', [AuthController::class, 'verifyResetToken']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Routes protégées (nécessitent authentification)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::get('/login', function () {
    return response()->json(['message' => 'Unauthorized.'], 401);
})->name('login');


Route::get('/livraisons/track/{colis_label}', [LivraisonController::class, 'trackByColisLabel']);

// Routes d'authentification protégées
Route::middleware('auth:sanctum')->group(function () {
    
    Route::apiResource('users', UserController::class);
    // Statistiques spécifiques par utilisateur
    Route::get('users/{id}/stats/client', [UserController::class, 'getClientStats']);
    Route::get('users/{id}/stats/livreur', [UserController::class, 'getLivreurStats']);
    
Route::prefix('admin')->group(function () {
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

Route::get('livraisons/{id}/bordereau-pdf', [AdminLivraisonController::class, 'generateBordereauPDF']);
Route::get('livraisons/{id}/print-html', [AdminLivraisonController::class, 'generatePrintHTML']);



Route::get('livraisons/getByClient/{id}', [AdminLivraisonController::class, 'getByClient']);
Route::get('livraisons/getByLivreur/{id}', [AdminLivraisonController::class, 'getByLivreur']);


Route::get('livraisons/en-cours', [AdminLivraisonController::class, 'livraisonsEnCours']);
});

Route::post('/logout-all', [AuthController::class, 'logoutAll']);  // NOUVELLE
Route::get('/verify-token', [AuthController::class, 'verifyToken']);  // NOUVELLE position

// Position GPS avec contrôleur différent
Route::patch('/update-position', [UserController::class, 'updatePosition']);  // Changé de AuthController à UserController


        Route::get('/all-users', [UserController::class, 'getAllUsers']);
    // Routes d'authentification
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        


        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/profile/photo', [AuthController::class, 'updatePhotoProfile']);
        Route::delete('/delete/{id}', [UserController::class, 'destroy']);

        Route::put('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::get('/verify-token', [AuthController::class, 'verifyToken']);
    });
    // Routes pour les demandes de livraison, livraisons, livreurs, clients, demandes d'adhésion, avis, réponses aux avis, commentaires et bordereaux
    Route::apiResource('demandes-livraison', DemandeLivraisonController::class);
    
Route::apiResource('livraisons', LivraisonController::class)->except(['store', 'update']);

// Changement de nom de méthode
Route::get('livraisons/en-cours', [LivraisonController::class, 'livraisonsEnCours']);  // Avant: 'toutesLivraisonsEnCours'
    // 🆕 ROUTES DYNAMIQUES DE STATUTS (Système de statuts dynamiques)
    Route::patch('livraisons/{id}/status-by-livreur', [LivreurCourseController::class, 'updateStatusByLivreurType']);
    Route::get('livraisons/{id}/valid-transitions', [LivreurCourseController::class, 'getValidTransitions']);
    Route::get('livraisons/by-status-and-type/{status}', [LivreurCourseController::class, 'getByStatusAndLivreurType']);
    Route::get('livraisons/statistics-by-status', [LivreurCourseController::class, 'getStatistiquesByStatus']);


    Route::apiResource('livraisons', LivraisonController::class);
    Route::patch('livraisons/{id}/status', [LivraisonController::class, 'updateStatus']);
    Route::patch('livraisons/{id}/assign-livreur', [LivraisonController::class, 'assignLivreur']);
    Route::patch('livraisons/{id}/destroy_by_client', [LivraisonController::class, 'destroyByClient']);
    Route::get('livraisons/{id}/statistiques', [LivraisonController::class, 'statistiquesClient']);
    Route::get('livraisons/{id}/statistiques/livreur', [LivraisonController::class, 'statistiquesLivreur']);

    Route::get('livraisons/getByClient/{id}', [LivraisonController::class, 'getByClient']);
    Route::get('livraisons/getByLivreur/{id}', [LivraisonController::class, 'getByLivreur']);

    Route::get('livraisons/client/{id}/en-cours', [LivraisonController::class, 'livraisonsClientEnCours']);
    Route::get('livraisons/livreur/{id}/en-cours', [LivraisonController::class, 'livraisonsLivreurEnCours']);
    Route::get('livraisons/en-cours', [LivraisonController::class, 'toutesLivraisonsEnCours']);


    Route::apiResource('livreurs', LivreurController::class);
    Route::patch('livreurs/{id}/toggle-activation', [LivreurController::class, 'toggleActivation']);
    Route::apiResource('clients', ClientController::class);


    Route::apiResource('demandes-adhesion', DemandeAdhesionController::class);
    Route::patch('demandes-adhesion/{id}/status', [DemandeAdhesionController::class, 'updateStatus']);
    Route::get('demandes-adhesion/by-status/{status}', [DemandeAdhesionController::class, 'getByStatus']);

    Route::apiResource('avis', AvisController::class);
    Route::apiResource('reponses-avis', ResponseAvisController::class);
    Route::apiResource('commentaires', CommentaireController::class);

    Route::apiResource('bordereaux', BordereauController::class);
    Route::get('all-users', [UserController::class, 'getAllUsers']);
    Route::get('users/stats', [UserController::class, 'stats']);
    Route::get('users/search', [UserController::class, 'search']);
    Route::patch('users/{id}/toggle-activation', [UserController::class, 'toggleActivation']);
    Route::get('/users/show/{id}', [UserController::class, 'show']);

    Route::patch('user/position', [AuthController::class, 'updatePosition']);
    Route::patch('/update/photo', [AuthController::class, 'updatePhoto']);

    Route::post('/users/{userId}/fcm-token', [NotificationTokenController::class, 'store']);

    Route::get('/users/{user}/notifications', [NotificationHistoriqueController::class, 'index']);
    Route::post('/users/{user}/notifications/{notification}/read', [NotificationHistoriqueController::class, 'markAsRead']);
    
    
});

Route::get('livraisons/{id}/bordereau-pdf', [LivraisonController::class, 'generateBordereauPDF']);
Route::get('livraisons/{id}/print-html', [LivraisonController::class, 'generatePrintHTML']);



Route::middleware('auth:sanctum')->prefix('livreur')->group(function () {
    Route::get('courses', [LivreurCourseController::class, 'index']);  // 1️⃣
    Route::get('courses/count', [LivreurCourseController::class, 'count']);  // 4️⃣ AVANT {id}
    Route::get('courses/status/{status}', [LivreurCourseController::class, 'byStatus']);  // 5️⃣
    Route::get('courses/colis', [LivreurCourseController::class, 'colis']);  // 6️⃣
    Route::get('courses/{id}', [LivreurCourseController::class, 'show']);  // 2️⃣ APRÈS les fixes
    Route::post('courses/{id}/complete', [LivreurCourseController::class, 'complete']);  // 3️⃣
    
   // 🆕 NOUVELLES ROUTES - Statistiques optimisées
    Route::get('stats/dashboard', [LivreurStatsController::class, 'dashboard']);  // Dashboard complet
    Route::get('stats/detailed', [LivreurStatsController::class, 'detailedStats']);  // Stats détaillées
});


Route::middleware('auth:sanctum')->post('/auth/delete-account', [AuthController::class, 'deleteAccount']);

