<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;

class CampusController extends Controller
{
    // Liste des campus
    public function index()
    {
        try {
            $campuses = Campus::orderBy('name')->get();

            return response()->json([
                'message' => 'Liste des campus récupérée avec succès',
                'data' => $campuses
            ]);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des campus',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Détails d'un campus
    public function show($id)
    {
        try {
            $campus = Campus::findOrFail($id);

            return response()->json([
                'message' => 'Campus récupéré avec succès',
                'data' => $campus
            ]);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Campus non trouvé',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    // Créer un campus
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:campuses,name',
                'city' => 'required|string|max:100',
                'address' => 'nullable|string',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
            ], [
                'name.required' => 'Le nom du campus est obligatoire',
                'name.string' => 'Le nom doit être une chaîne de caractères',
                'name.max' => 'Le nom ne doit pas dépasser 255 caractères',
                'name.unique' => 'Ce nom de campus existe déjà',
                'city.required' => 'La ville est obligatoire',
                'city.string' => 'La ville doit être une chaîne de caractères',
                'city.max' => 'La ville ne doit pas dépasser 100 caractères',
                'email.email' => 'Veuillez saisir une adresse email valide',
                'email.max' => 'L\'email ne doit pas dépasser 255 caractères',
                'phone.max' => 'Le téléphone ne doit pas dépasser 20 caractères',
            ]);

            DB::beginTransaction();

            $campus = Campus::create($validated);

            DB::commit();

            return response()->json([
                'message' => 'Campus créé avec succès',
                'data' => $campus
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la création du campus',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Modifier un campus
    public function update(Request $request, $id)
    {
        try {
            $campus = Campus::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:campuses,name,' . $id,
                'city' => 'required|string|max:100',
                'address' => 'nullable|string',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'is_active' => 'nullable|boolean',
            ], [
                'name.required' => 'Le nom du campus est obligatoire',
                'name.string' => 'Le nom doit être une chaîne de caractères',
                'name.max' => 'Le nom ne doit pas dépasser 255 caractères',
                'name.unique' => 'Ce nom de campus existe déjà',
                'city.required' => 'La ville est obligatoire',
                'city.string' => 'La ville doit être une chaîne de caractères',
                'city.max' => 'La ville ne doit pas dépasser 100 caractères',
                'email.email' => 'Veuillez saisir une adresse email valide',
                'email.max' => 'L\'email ne doit pas dépasser 255 caractères',
                'phone.max' => 'Le téléphone ne doit pas dépasser 20 caractères',
                'is_active.boolean' => 'Le statut doit être vrai ou faux',
            ]);

            DB::beginTransaction();

            $campus->update($validated);

            DB::commit();

            return response()->json([
                'message' => 'Campus modifié avec succès',
                'data' => $campus
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la modification du campus',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Supprimer un campus
    public function destroy($id)
    {
        try {
            $campus = Campus::findOrFail($id);

            DB::beginTransaction();

            $campus->delete();

            DB::commit();

            return response()->json([
                'message' => 'Campus supprimé avec succès'
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la suppression du campus',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Activer/Désactiver un campus
    public function toggleStatus($id)
    {
        try {
            $campus = Campus::findOrFail($id);

            DB::beginTransaction();

            $campus->is_active = !$campus->is_active;
            $campus->save();

            DB::commit();

            $status = $campus->is_active ? 'activé' : 'désactivé';

            return response()->json([
                'message' => "Campus {$status} avec succès",
                'data' => $campus
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors du changement de statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}