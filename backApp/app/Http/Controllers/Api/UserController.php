<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Exception;

class UserController extends Controller
{
    // Liste des utilisateurs
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if (!in_array($user->role, ['super_admin', 'admin_global', 'admin_campus'])) {
                return response()->json([
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $query = User::with('campus');

            if ($user->role === 'super_admin') {
                // Tout voir
            } elseif ($user->role === 'admin_global') {
                $query->where('role', '!=', 'super_admin');
            } elseif ($user->role === 'admin_campus') {
                $query->where('campus_id', $user->campus_id)
                      ->whereIn('role', ['admin_campus', 'secretary']);
            }

            $users = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'message' => 'Liste des utilisateurs récupérée avec succès',
                'data' => $users
            ]);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des utilisateurs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Créer un utilisateur
    public function store(Request $request)
    {
        try {
            $user = $request->user();

            if (!in_array($user->role, ['super_admin', 'admin_global', 'admin_campus'])) {
                return response()->json([
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $validated = $request->validate([
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:6',
                'phone' => 'nullable|string|max:20',
                'role' => 'required|in:admin_global,admin_campus,secretary',
                'campus_id' => 'nullable|exists:campuses,id',
                'is_active' => 'nullable|boolean',
            ], [
                'first_name.required' => 'Le prénom est obligatoire',
                'last_name.required' => 'Le nom est obligatoire',
                'email.required' => 'L\'email est obligatoire',
                'email.email' => 'Veuillez saisir un email valide',
                'email.unique' => 'Cet email est déjà utilisé',
                'password.required' => 'Le mot de passe est obligatoire',
                'password.min' => 'Le mot de passe doit contenir au moins 6 caractères',
                'role.required' => 'Le rôle est obligatoire',
                'role.in' => 'Rôle invalide',
                'campus_id.exists' => 'Campus invalide',
            ]);

            // Admin Campus ne peut créer que des secrétaires
            if ($user->role === 'admin_campus') {
                if ($validated['role'] !== 'secretary') {
                    return response()->json([
                        'message' => 'Un admin campus ne peut créer que des secrétaires'
                    ], 403);
                }
                $validated['campus_id'] = $user->campus_id;
            }

            // Admin Global ne peut pas créer de Super Admin
            if ($user->role === 'admin_global' && $validated['role'] === 'super_admin') {
                return response()->json([
                    'message' => 'Vous ne pouvez pas créer un super admin'
                ], 403);
            }

            // Campus obligatoire pour admin_campus et secretary
            if (in_array($validated['role'], ['admin_campus', 'secretary']) && !$validated['campus_id']) {
                return response()->json([
                    'message' => 'Le campus est obligatoire pour ce rôle'
                ], 422);
            }

            $validated['password'] = Hash::make($validated['password']);
            $validated['is_active'] = $validated['is_active'] ?? true;

            $newUser = User::create($validated);

            return response()->json([
                'message' => 'Utilisateur créé avec succès',
                'data' => $newUser->load('campus')
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création de l\'utilisateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Modifier un utilisateur
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            $targetUser = User::findOrFail($id);

            if (!in_array($user->role, ['super_admin', 'admin_global', 'admin_campus'])) {
                return response()->json([
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            // Admin Campus
            if ($user->role === 'admin_campus') {
                if ($targetUser->campus_id !== $user->campus_id) {
                    return response()->json([
                        'message' => 'Vous ne pouvez modifier que les utilisateurs de votre campus'
                    ], 403);
                }
                if ($targetUser->role !== 'secretary') {
                    return response()->json([
                        'message' => 'Un admin campus ne peut modifier que des secrétaires'
                    ], 403);
                }
            }

            // Admin Global
            if ($user->role === 'admin_global' && $targetUser->role === 'super_admin') {
                return response()->json([
                    'message' => 'Vous ne pouvez pas modifier un super admin'
                ], 403);
            }

            $validated = $request->validate([
                'first_name' => 'sometimes|required|string|max:100',
                'last_name' => 'sometimes|required|string|max:100',
                'email' => 'sometimes|required|email|max:255|unique:users,email,' . $id,
                'phone' => 'nullable|string|max:20',
                'role' => 'sometimes|required|in:admin_global,admin_campus,secretary',
                'campus_id' => 'nullable|exists:campuses,id',
                'is_active' => 'nullable|boolean',
            ], [
                'first_name.required' => 'Le prénom est obligatoire',
                'last_name.required' => 'Le nom est obligatoire',
                'email.required' => 'L\'email est obligatoire',
                'email.email' => 'Veuillez saisir un email valide',
                'email.unique' => 'Cet email est déjà utilisé',
                'role.in' => 'Rôle invalide',
                'campus_id.exists' => 'Campus invalide',
            ]);

            // Admin Campus ne peut pas changer le rôle
            if ($user->role === 'admin_campus') {
                unset($validated['role']);
                unset($validated['campus_id']);
            }

            $targetUser->update($validated);

            return response()->json([
                'message' => 'Utilisateur modifié avec succès',
                'data' => $targetUser->load('campus')
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la modification de l\'utilisateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Supprimer un utilisateur
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $targetUser = User::findOrFail($id);

            if (!in_array($user->role, ['super_admin', 'admin_global', 'admin_campus'])) {
                return response()->json([
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            // Admin Campus
            if ($user->role === 'admin_campus') {
                if ($targetUser->campus_id !== $user->campus_id) {
                    return response()->json([
                        'message' => 'Vous ne pouvez supprimer que les utilisateurs de votre campus'
                    ], 403);
                }
                if ($targetUser->role !== 'secretary') {
                    return response()->json([
                        'message' => 'Un admin campus ne peut supprimer que des secrétaires'
                    ], 403);
                }
            }

            // Admin Global
            if ($user->role === 'admin_global' && $targetUser->role === 'super_admin') {
                return response()->json([
                    'message' => 'Vous ne pouvez pas supprimer un super admin'
                ], 403);
            }

            // Ne pas se supprimer soi-même
            if ($user->id === $targetUser->id) {
                return response()->json([
                    'message' => 'Vous ne pouvez pas vous supprimer vous-même'
                ], 403);
            }

            $targetUser->delete();

            return response()->json([
                'message' => 'Utilisateur supprimé avec succès'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de l\'utilisateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Activer/Désactiver un utilisateur
    public function toggleStatus(Request $request, $id)
    {
        try {
            $user = $request->user();
            $targetUser = User::findOrFail($id);

            if (!in_array($user->role, ['super_admin', 'admin_global', 'admin_campus'])) {
                return response()->json([
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            // Admin Campus
            if ($user->role === 'admin_campus') {
                if ($targetUser->campus_id !== $user->campus_id) {
                    return response()->json([
                        'message' => 'Vous ne pouvez modifier que les utilisateurs de votre campus'
                    ], 403);
                }
                if ($targetUser->role !== 'secretary') {
                    return response()->json([
                        'message' => 'Un admin campus ne peut modifier que des secrétaires'
                    ], 403);
                }
            }

            // Admin Global
            if ($user->role === 'admin_global' && $targetUser->role === 'super_admin') {
                return response()->json([
                    'message' => 'Vous ne pouvez pas modifier un super admin'
                ], 403);
            }

            $targetUser->is_active = !$targetUser->is_active;
            $targetUser->save();

            $status = $targetUser->is_active ? 'activé' : 'désactivé';

            return response()->json([
                'message' => "Utilisateur {$status} avec succès",
                'data' => $targetUser
            ]);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du changement de statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}