<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class FormationController extends Controller
{
    // Liste des formations (accessible à tous)
    public function index()
    {
        try {
            $formations = Formation::orderBy('name')->get();

            return response()->json([
                'message' => 'Liste des formations récupérée avec succès',
                'data' => $formations
            ]);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des formations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Détails d'une formation (accessible à tous)
    public function show($id)
    {
        try {
            $formation = Formation::findOrFail($id);

            return response()->json([
                'message' => 'Formation récupérée avec succès',
                'data' => $formation
            ]);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Formation non trouvée',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    // Créer une formation (Super Admin & Admin Global uniquement)
    public function store(Request $request)
    {
        try {
            $user = $request->user();

            if (!in_array($user->role, ['super_admin', 'admin_global'])) {
                return response()->json([
                    'message' => 'Accès non autorisé. Seul un Super Admin ou Admin Global peut créer une formation.'
                ], 403);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:formations,name',
                'abbreviation' => 'required|string|max:10|unique:formations,abbreviation',
                'tuition_fees' => 'required|numeric|min:0',
                'duration_months' => 'required|integer|min:1',
                'is_active' => 'nullable|boolean',
            ], [
                'name.required' => 'Le nom de la formation est obligatoire',
                'name.string' => 'Le nom doit être une chaîne de caractères',
                'name.max' => 'Le nom ne doit pas dépasser 255 caractères',
                'name.unique' => 'Cette formation existe déjà',
                'abbreviation.required' => 'L\'abréviation est obligatoire',
                'abbreviation.string' => 'L\'abréviation doit être une chaîne de caractères',
                'abbreviation.max' => 'L\'abréviation ne doit pas dépasser 10 caractères',
                'abbreviation.unique' => 'Cette abréviation est déjà utilisée',
                'tuition_fees.required' => 'Les frais de scolarité sont obligatoires',
                'tuition_fees.numeric' => 'Les frais de scolarité doivent être un nombre',
                'tuition_fees.min' => 'Les frais de scolarité ne peuvent pas être négatifs',
                'duration_months.required' => 'La durée est obligatoire',
                'duration_months.integer' => 'La durée doit être un nombre entier',
                'duration_months.min' => 'La durée doit être d\'au moins 1 mois',
                'is_active.boolean' => 'Le statut doit être vrai ou faux',
            ]);

            $formation = Formation::create($validated);

            return response()->json([
                'message' => 'Formation créée avec succès',
                'data' => $formation
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création de la formation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Modifier une formation (Super Admin & Admin Global uniquement)
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();

            if (!in_array($user->role, ['super_admin', 'admin_global'])) {
                return response()->json([
                    'message' => 'Accès non autorisé. Seul un Super Admin ou Admin Global peut modifier une formation.'
                ], 403);
            }

            $formation = Formation::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255|unique:formations,name,' . $id,
                'abbreviation' => 'sometimes|required|string|max:10|unique:formations,abbreviation,' . $id,
                'tuition_fees' => 'sometimes|required|numeric|min:0',
                'duration_months' => 'sometimes|required|integer|min:1',
                'is_active' => 'nullable|boolean',
            ], [
                'name.required' => 'Le nom de la formation est obligatoire',
                'name.string' => 'Le nom doit être une chaîne de caractères',
                'name.max' => 'Le nom ne doit pas dépasser 255 caractères',
                'name.unique' => 'Cette formation existe déjà',
                'abbreviation.required' => 'L\'abréviation est obligatoire',
                'abbreviation.string' => 'L\'abréviation doit être une chaîne de caractères',
                'abbreviation.max' => 'L\'abréviation ne doit pas dépasser 10 caractères',
                'abbreviation.unique' => 'Cette abréviation est déjà utilisée',
                'tuition_fees.required' => 'Les frais de scolarité sont obligatoires',
                'tuition_fees.numeric' => 'Les frais de scolarité doivent être un nombre',
                'tuition_fees.min' => 'Les frais de scolarité ne peuvent pas être négatifs',
                'duration_months.required' => 'La durée est obligatoire',
                'duration_months.integer' => 'La durée doit être un nombre entier',
                'duration_months.min' => 'La durée doit être d\'au moins 1 mois',
                'is_active.boolean' => 'Le statut doit être vrai ou faux',
            ]);

            $formation->update($validated);

            return response()->json([
                'message' => 'Formation modifiée avec succès',
                'data' => $formation
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la modification de la formation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Supprimer une formation (Super Admin & Admin Global uniquement)
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();

            if (!in_array($user->role, ['super_admin', 'admin_global'])) {
                return response()->json([
                    'message' => 'Accès non autorisé. Seul un Super Admin ou Admin Global peut supprimer une formation.'
                ], 403);
            }

            $formation = Formation::findOrFail($id);

            // Vérifier si des étudiants sont associés
            if ($formation->students()->count() > 0) {
                return response()->json([
                    'message' => 'Cette formation est utilisée par des étudiants. Impossible de la supprimer.'
                ], 422);
            }

            $formation->delete();

            return response()->json([
                'message' => 'Formation supprimée avec succès'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de la formation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Activer/Désactiver une formation (Super Admin & Admin Global uniquement)
    public function toggleStatus(Request $request, $id)
    {
        try {
            $user = $request->user();

            if (!in_array($user->role, ['super_admin', 'admin_global'])) {
                return response()->json([
                    'message' => 'Accès non autorisé. Seul un Super Admin ou Admin Global peut modifier le statut d\'une formation.'
                ], 403);
            }

            $formation = Formation::findOrFail($id);
            $formation->is_active = !$formation->is_active;
            $formation->save();

            $status = $formation->is_active ? 'activée' : 'désactivée';

            return response()->json([
                'message' => "Formation {$status} avec succès",
                'data' => $formation
            ]);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du changement de statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}