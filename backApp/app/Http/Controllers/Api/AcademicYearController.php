<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class AcademicYearController extends Controller
{
    // Liste des années
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            // Tout le monde peut voir les années (pour le dropdown)
            $years = AcademicYear::orderBy('label', 'desc')->get();

            return response()->json([
                'message' => 'Liste des années scolaires récupérée avec succès',
                'data' => $years,
                'current_year_id' => $user->academic_year_id
            ]);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des années scolaires',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Détails d'une année
    public function show($id)
    {
        try {
            $year = AcademicYear::findOrFail($id);

            return response()->json([
                'message' => 'Année scolaire récupérée avec succès',
                'data' => $year
            ]);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Année scolaire non trouvée',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    // Créer une année (seul Super Admin & Admin Global)
    public function store(Request $request)
    {
        try {
            $user = $request->user();

            if (!in_array($user->role, ['super_admin', 'admin_global'])) {
                return response()->json([
                    'message' => 'Accès non autorisé. Seul un Super Admin ou Admin Global peut créer une année scolaire.'
                ], 403);
            }

            $validated = $request->validate([
                'label' => 'required|string|max:20|unique:academic_years,label',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'is_current' => 'nullable|boolean',
            ], [
                'label.required' => 'Le libellé est obligatoire',
                'label.string' => 'Le libellé doit être une chaîne de caractères',
                'label.max' => 'Le libellé ne doit pas dépasser 20 caractères',
                'label.unique' => 'Cette année scolaire existe déjà',
                'start_date.required' => 'La date de début est obligatoire',
                'start_date.date' => 'Veuillez saisir une date valide',
                'end_date.required' => 'La date de fin est obligatoire',
                'end_date.date' => 'Veuillez saisir une date valide',
                'end_date.after' => 'La date de fin doit être postérieure à la date de début',
                'is_current.boolean' => 'Le statut doit être vrai ou faux',
            ]);

            // Si is_current est true, désactiver les autres années
            if ($validated['is_current'] ?? false) {
                AcademicYear::where('is_current', true)->update(['is_current' => false]);
            }

            $year = AcademicYear::create($validated);

            return response()->json([
                'message' => 'Année scolaire créée avec succès',
                'data' => $year
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création de l\'année scolaire',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Modifier une année (seul Super Admin & Admin Global)
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();

            if (!in_array($user->role, ['super_admin', 'admin_global'])) {
                return response()->json([
                    'message' => 'Accès non autorisé. Seul un Super Admin ou Admin Global peut modifier une année scolaire.'
                ], 403);
            }

            $year = AcademicYear::findOrFail($id);

            $validated = $request->validate([
                'label' => 'sometimes|required|string|max:20|unique:academic_years,label,' . $id,
                'start_date' => 'sometimes|required|date',
                'end_date' => 'sometimes|required|date|after:start_date',
                'is_current' => 'nullable|boolean',
                'is_active' => 'nullable|boolean',
            ], [
                'label.required' => 'Le libellé est obligatoire',
                'label.string' => 'Le libellé doit être une chaîne de caractères',
                'label.max' => 'Le libellé ne doit pas dépasser 20 caractères',
                'label.unique' => 'Cette année scolaire existe déjà',
                'start_date.required' => 'La date de début est obligatoire',
                'start_date.date' => 'Veuillez saisir une date valide',
                'end_date.required' => 'La date de fin est obligatoire',
                'end_date.date' => 'Veuillez saisir une date valide',
                'end_date.after' => 'La date de fin doit être postérieure à la date de début',
                'is_current.boolean' => 'Le statut doit être vrai ou faux',
                'is_active.boolean' => 'Le statut doit être vrai ou faux',
            ]);

            // Si is_current est true, désactiver les autres années
            if (isset($validated['is_current']) && $validated['is_current']) {
                AcademicYear::where('is_current', true)->where('id', '!=', $id)->update(['is_current' => false]);
            }

            $year->update($validated);

            return response()->json([
                'message' => 'Année scolaire modifiée avec succès',
                'data' => $year
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la modification de l\'année scolaire',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Supprimer une année (seul Super Admin & Admin Global)
    public function destroy($id)
    {
        try {
            $user = request()->user();

            if (!in_array($user->role, ['super_admin', 'admin_global'])) {
                return response()->json([
                    'message' => 'Accès non autorisé. Seul un Super Admin ou Admin Global peut supprimer une année scolaire.'
                ], 403);
            }

            $year = AcademicYear::findOrFail($id);

            // Vérifier si des données sont associées
            if ($year->users()->count() > 0) {
                return response()->json([
                    'message' => 'Cette année scolaire est utilisée par des utilisateurs. Impossible de la supprimer.'
                ], 422);
            }

            $year->delete();

            return response()->json([
                'message' => 'Année scolaire supprimée avec succès'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de l\'année scolaire',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Changer l'année active d'un utilisateur
    public function switchYear(Request $request)
    {
        try {
            $user = $request->user();
            $yearId = $request->input('academic_year_id');

            if (!$yearId) {
                return response()->json([
                    'message' => 'L\'année scolaire est obligatoire'
                ], 422);
            }

            $year = AcademicYear::findOrFail($yearId);

            $user->academic_year_id = $yearId;
            $user->save();

            return response()->json([
                'message' => 'Année scolaire changée avec succès',
                'data' => [
                    'user' => $user->load('academicYear'),
                    'current_year' => $year
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du changement d\'année scolaire',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}