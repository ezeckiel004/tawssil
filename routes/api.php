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
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
});

Route::get('/login', function () {
    return response()->json(['message' => 'Unauthorized.'], 401);
})->name('login');


// Routes d'authentification protégées
Route::middleware('auth:sanctum')->group(function () {

    // Routes d'authentification
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::apiResource('users', UserController::class);
        Route::get('/all-users', [UserController::class, 'getAllUsers']);


        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/profile/photo', [AuthController::class, 'updatePhotoProfile']);
        Route::delete('/delete/{id}', [UserController::class, 'destroy']);

        Route::put('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::get('/verify-token', [AuthController::class, 'verifyToken']);
    });
    // Routes pour les demandes de livraison, livraisons, livreurs, clients, demandes d'adhésion, avis, réponses aux avis, commentaires et bordereaux
    Route::apiResource('demandes-livraison', DemandeLivraisonController::class);

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
    Route::get('users', [AuthController::class, 'getAllUsers']);

    Route::patch('user/position', [AuthController::class, 'updatePosition']);
    Route::patch('/update/photo', [AuthController::class, 'updatePhoto']);

    Route::post('/users/{userId}/fcm-token', [NotificationTokenController::class, 'store']);

    Route::get('/users/{user}/notifications', [NotificationHistoriqueController::class, 'index']);
    Route::post('/users/{user}/notifications/{notification}/read', [NotificationHistoriqueController::class, 'markAsRead']);
});



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