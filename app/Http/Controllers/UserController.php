<?php
namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use Illuminate\Http\Request;
class UserController extends Controller
{
    public function parse_api_response($data, $message, $error, $code = 200)
    {
        return response()->json([
            'message' => $message,
            'error'   => $error,
            'data'    => $data,
        ], $code);
    }

    /**
     * Récupérer tous les utilisateurs du système.
     */
    public function getAllUsers(): JsonResponse
    {
        try {
            // Récupérer tous les utilisateurs avec leurs relations
            $users = User::with(['client', 'livreur.demandeAdhesion'])->get();

            return response()->json($users, 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des utilisateurs',
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
            $user = $request->user(); // Récupérer l'utilisateur authentifié

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

    public function destroy($id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        //Desactiver le compte 
        $user->update([
            'actif' => 0,
        ]);

        //$user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur desactivé avec succès',
        ], 200);
    }



}