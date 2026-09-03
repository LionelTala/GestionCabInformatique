<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FormationController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Formation::orderBy('name')->get()
        ]);
    }

    public function show(int $id)
    {
        return response()->json([
            'data' => Formation::findOrFail($id)
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:formations,name',
            'abbreviation' => 'required|string|max:10|unique:formations,abbreviation',
            'tuition_fees' => 'required|numeric|min:0',
            'duration_months' => 'required|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $formation = Formation::create($validated);

        Log::info('Formation créée', ['id' => $formation->id, 'name' => $formation->name, 'by' => $request->user()->id]);

        return response()->json(['message' => 'Formation créée avec succès', 'data' => $formation], 201);
    }

    public function update(Request $request, int $id)
    {
        $formation = Formation::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:formations,name,' . $id,
            'abbreviation' => 'sometimes|required|string|max:10|unique:formations,abbreviation,' . $id,
            'tuition_fees' => 'sometimes|required|numeric|min:0',
            'duration_months' => 'sometimes|required|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $formation->update($validated);

        Log::info('Formation modifiée', ['id' => $formation->id, 'by' => $request->user()->id, 'changes' => array_keys($validated)]);

        return response()->json(['message' => 'Formation modifiée avec succès', 'data' => $formation]);
    }

    public function destroy(Request $request, int $id)
    {
        $formation = Formation::findOrFail($id);

        if ($formation->students()->exists()) {
            return response()->json(['message' => 'Impossible de supprimer : des étudiants sont inscrits à cette formation'], 422);
        }

        $formation->delete();

        Log::info('Formation supprimée', ['id' => $formation->id, 'name' => $formation->name, 'by' => $request->user()->id]);

        return response()->json(['message' => 'Formation supprimée avec succès']);
    }

    public function toggleStatus(Request $request, int $id)
    {
        $formation = Formation::findOrFail($id);
        $formation->update(['is_active' => !$formation->is_active]);

        Log::info('Formation ' . ($formation->is_active ? 'activée' : 'désactivée'), ['id' => $formation->id, 'by' => $request->user()->id]);

        return response()->json([
            'message' => "Formation " . ($formation->is_active ? 'activée' : 'désactivée'),
            'data' => $formation
        ]);
    }
}