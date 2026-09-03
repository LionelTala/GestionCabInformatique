<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AcademicYearController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'data' => AcademicYear::orderBy('label', 'desc')->get(),
            'current_year_id' => $request->user()->academic_year_id
        ]);
    }

    public function show(int $id)
    {
        return response()->json(['data' => AcademicYear::findOrFail($id)]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:20|unique:academic_years,label',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_current' => 'nullable|boolean',
        ]);

        if ($validated['is_current'] ?? false) {
            AcademicYear::where('is_current', true)->update(['is_current' => false]);
        }

        $year = AcademicYear::create($validated);

        Log::info('Année scolaire créée', ['id' => $year->id, 'label' => $year->label, 'by' => $request->user()->id]);

        return response()->json(['message' => 'Année scolaire créée', 'data' => $year], 201);
    }

    public function update(Request $request, int $id)
    {
        $year = AcademicYear::findOrFail($id);

        $validated = $request->validate([
            'label' => 'sometimes|required|string|max:20|unique:academic_years,label,' . $id,
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'is_current' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($validated['is_current']) && $validated['is_current']) {
            AcademicYear::where('is_current', true)->where('id', '!=', $id)->update(['is_current' => false]);
        }

        $year->update($validated);

        Log::info('Année scolaire modifiée', ['id' => $year->id, 'by' => $request->user()->id]);

        return response()->json(['message' => 'Année scolaire modifiée', 'data' => $year]);
    }

    public function destroy(Request $request, int $id)
    {
        $year = AcademicYear::findOrFail($id);

        if ($year->users()->exists()) {
            return response()->json(['message' => 'Des utilisateurs sont liés à cette année. Suppression impossible.'], 422);
        }

        $year->delete();

        Log::info('Année scolaire supprimée', ['id' => $year->id, 'label' => $year->label, 'by' => $request->user()->id]);

        return response()->json(['message' => 'Année scolaire supprimée']);
    }

    public function switchYear(Request $request)
    {
        $validated = $request->validate(['academic_year_id' => 'required|exists:academic_years,id']);

        $year = AcademicYear::findOrFail($validated['academic_year_id']);

        $request->user()->update(['academic_year_id' => $year->id]);

        return response()->json([
            'message' => 'Année scolaire changée',
            'current_year_id' => $year->id
        ]);
    }
}