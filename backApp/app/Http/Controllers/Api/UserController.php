<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;


use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = User::select('id', 'first_name', 'last_name', 'email', 'phone', 'role', 'campus_id', 'is_active', 'created_at','username')
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
        // 1. Validation : username est nullable (peut être null ou vide)
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:6',
            'username' => 'nullable|string|max:255|unique:users,username',
            'role' => 'required|in:super_admin,admin_global,admin_campus,secretary',
            'campus_id' => 'nullable|integer|exists:campuses,id',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        // 2. AUTO-GÉNÉRATION ROBUSTE
        // Si username est vide, null, ou n'existe pas, on le génère
        if (empty($validated['username'])) {
            $validated['username'] = $this->generateUniqueUsername($validated['first_name'], $validated['last_name']);
        }

        // 3. Hashage et création
        $validated['password'] = Hash::make($validated['password']);
        
        $user = User::create($validated);

        return response()->json([
            'message' => 'Utilisateur créé avec succès', 
            'data' => $user
        ], 201);
    }

    public function update(Request $request, int $id)
{
    $user = $request->user();
    $target = User::findOrFail($id);   // ✅ Récupère TOUTES les colonnes

    $this->checkEditAccess($user, $target);

    $validated = $request->validate([
        'first_name' => 'sometimes|required|string|max:100',
        'last_name'  => 'sometimes|required|string|max:100',
        'email'      => 'nullable|email|max:255|unique:users,email,' . $id,
        'phone'      => 'nullable|string|max:20',
        // ✅ FIX : exclure l'utilisateur actuel de la vérification unique
        'username'   => 'nullable|string|max:255|unique:users,username,' . $id,

        'role'       => 'sometimes|in:admin_global,admin_campus,secretary',
        'campus_id'  => 'nullable|exists:campuses,id',
        'is_active'  => 'nullable|boolean',
        'password'   => 'nullable|string|min:6',
    ]);

    // ✅ FIX : si email vide → null
    if (array_key_exists('email', $validated) && $validated['email'] === '') {
        $validated['email'] = null;
    }

    // ✅ FIX : si username vide → null (ou garder l'ancien)
    if (array_key_exists('username', $validated) && $validated['username'] === '') {
        $validated['username'] = $target->username; // garde l'ancien
    }

    // ✅ FIX : si password vide → ne pas modifier
    if (array_key_exists('password', $validated)) {
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }
    }

    // Restriction admin_campus
    if ($user->role === 'admin_campus') {
        unset($validated['role'], $validated['campus_id']);
    }

    // ✅ FIX : nettoyer $validated des champs non-fillable
    $target->update($validated);

    Log::info('Utilisateur modifié', [
        'id'      => $target->id,
        'by'      => $user->id,
        'changes' => array_keys($validated),   // ✅ On log juste les clés
    ]);

    return response()->json([
        'message' => 'Utilisateur modifié avec succès',
        'data'    => $target->fresh()->load('campus:id,name,city'),
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
     private function generateUniqueUsername(string $firstName, string $lastName): string
    {
        // Str::slug fait tout le travail sale : minuscules, suppression accents, espaces -> tirets
        // Ex: "Jean-Pierre" + "Dupont Martin" => "jean-pierre.dupont-martin"
        $base = Str::slug($firstName, '-') . '.' . Str::slug($lastName, '-');
        $username = $base;
        $counter = 1;

        // Boucle pour garantir l'unicité (ex: jean-pierre.dupont-martin1, 2, etc.)
        while (User::where('username', $username)->exists()) {
            $username = $base . $counter;
            $counter++;
        }

        return $username;
    }
}