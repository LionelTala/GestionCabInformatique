<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = User::select('id', 'first_name', 'last_name', 'email', 'phone', 'role', 'campus_id', 'is_active', 'created_at')
            ->with('campus:id,name,city');

        match ($user->role) {
            'admin_global' => $query->where('role', '!=', 'super_admin'),
            'admin_campus' => $query->where('campus_id', $user->campus_id)
                ->whereIn('role', ['admin_campus', 'secretary']),
            default => null,
        };

        // Recherche
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filtre rôle
        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        // Filtre campus
        if ($campusId = $request->query('campus_id')) {
            $query->where('campus_id', $campusId);
        }

        // Filtre statut
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return response()->json([
            'data' => $query->orderBy('created_at', 'desc')->paginate($request->integer('per_page', 15))
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:admin_global,admin_campus,secretary',
            'campus_id' => 'nullable|exists:campuses,id',
            'is_active' => 'nullable|boolean',
        ]);

        // Restrictions par rôle
        if ($user->role === 'admin_campus') {
            if ($validated['role'] !== 'secretary') {
                return response()->json(['message' => 'Un admin campus ne peut créer que des secrétaires'], 403);
            }
            $validated['campus_id'] = $user->campus_id;
        }

        if ($user->role === 'admin_global' && $validated['role'] === 'super_admin') {
            return response()->json(['message' => 'Impossible de créer un super admin'], 403);
        }

        if (in_array($validated['role'], ['admin_campus', 'secretary']) && empty($validated['campus_id'])) {
            return response()->json(['message' => 'Le campus est obligatoire pour ce rôle'], 422);
        }

        $validated['password'] = bcrypt($validated['password']);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $newUser = User::create($validated);

        Log::info('Utilisateur créé', [
            'id' => $newUser->id,
            'email' => $newUser->email,
            'role' => $newUser->role,
            'by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Utilisateur créé avec succès',
            'data' => $newUser->load('campus:id,name,city')
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $user = $request->user();
        $target = User::select('id', 'first_name', 'last_name', 'email', 'phone', 'role', 'campus_id', 'is_active')
            ->findOrFail($id);

        $this->checkEditAccess($user, $target);

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:100',
            'last_name' => 'sometimes|required|string|max:100',
            'email' => 'sometimes|required|email|max:255|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'role' => 'sometimes|in:admin_global,admin_campus,secretary',
            'campus_id' => 'nullable|exists:campuses,id',
            'is_active' => 'nullable|boolean',
            'password' => 'nullable|string|min:6',
        ]);

        if ($user->role === 'admin_campus') {
            unset($validated['role'], $validated['campus_id']);
        }

        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        $target->update($validated);

        Log::info('Utilisateur modifié', [
            'id' => $target->id,
            'by' => $user->id,
            'changes' => array_keys($validated),
        ]);

        return response()->json([
            'message' => 'Utilisateur modifié avec succès',
            'data' => $target->load('campus:id,name,city')
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $user = $request->user();
        $target = User::findOrFail($id);

        $this->checkEditAccess($user, $target);

        if ($user->id === $target->id) {
            return response()->json(['message' => 'Vous ne pouvez pas vous supprimer'], 403);
        }

        $target->delete();

        Log::info('Utilisateur supprimé', [
            'id' => $target->id,
            'email' => $target->email,
            'by' => $user->id,
        ]);

        return response()->json(['message' => 'Utilisateur supprimé avec succès']);
    }

    public function toggleStatus(Request $request, int $id)
    {
        $user = $request->user();
        $target = User::select('id', 'is_active', 'role', 'campus_id')->findOrFail($id);

        $this->checkEditAccess($user, $target);

        $target->update(['is_active' => !$target->is_active]);

        Log::info('Utilisateur ' . ($target->is_active ? 'activé' : 'désactivé'), [
            'id' => $target->id,
            'by' => $user->id,
        ]);

        return response()->json([
            'message' => "Utilisateur " . ($target->is_active ? 'activé' : 'désactivé'),
            'data' => $target
        ]);
    }

    // === PRIVE ===

    private function checkEditAccess(User $user, User $target): void
    {
        if ($user->role === 'admin_global' && $target->role === 'super_admin') {
            abort(403, 'Impossible de modifier un super admin');
        }

        if ($user->role === 'admin_campus') {
            if ($target->campus_id !== $user->campus_id) {
                abort(403, 'Pas accès à ce campus');
            }
            if (!in_array($target->role, ['admin_campus', 'secretary'])) {
                abort(403, 'Pas accès à ce rôle');
            }
        }
    }
}