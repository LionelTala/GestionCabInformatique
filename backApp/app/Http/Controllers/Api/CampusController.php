<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CampusController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Campus::orderBy('name')->get()
        ]);
    }

    public function show(int $id)
    {
        return response()->json([
            'data' => Campus::findOrFail($id)
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:campuses,name',
            'city' => 'required|string|max:100',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ]);

        return response()->json([
            'message' => 'Campus créé avec succès',
            'data' => Campus::create($validated)
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $campus = Campus::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:campuses,name,' . $id,
            'city' => 'required|string|max:100',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $campus->update($validated);

        return response()->json([
            'message' => 'Campus modifié avec succès',
            'data' => $campus
        ]);
    }

    public function destroy(int $id)
    {
        Campus::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Campus supprimé avec succès'
        ]);
    }

    public function toggleStatus(int $id)
    {
        $campus = Campus::findOrFail($id);
        $campus->is_active = !$campus->is_active;
        $campus->save();

        return response()->json([
            'message' => "Campus " . ($campus->is_active ? 'activé' : 'désactivé') . " avec succès",
            'data' => $campus
        ]);
    }
}