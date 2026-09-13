<?php
// app/Http/Controllers/Api/ProfileController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    /**
     * Récupérer les infos du profil
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $user->load('campus:id,name,city');

        return response()->json([
            'data' => [
                'id'         => $user->id,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => $user->email,
                'phone'      => $user->phone,
                'username'   => $user->username,
                'role'       => $user->role,
                'campus_id'  => $user->campus_id,
                'campus'     => $user->campus,
                'is_active'  => $user->is_active,
                'created_at' => $user->created_at,
            ],
        ]);
    }

    /**
     * Mettre à jour les infos du profil
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'nullable|email|max:255|unique:users,email,' . $user->id,
            'phone'      => 'nullable|string|max:20',
        ]);

        // ✅ Transforme email vide en null
        if (isset($validated['email']) && $validated['email'] === '') {
            $validated['email'] = null;
        }

        $user->update($validated);

        Log::info('Profil modifié', ['user_id' => $user->id]);

        return response()->json([
            'message' => 'Profil mis à jour avec succès',
            'data'    => $user->fresh()->load('campus:id,name,city'),
        ]);
    }

    /**
     * Changer le mot de passe
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password'         => ['required', 'string', 'confirmed', Password::min(6)],
        ]);

        // Vérifier le mot de passe actuel
        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Le mot de passe actuel est incorrect.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        Log::info('Mot de passe modifié', ['user_id' => $user->id]);

        return response()->json([
            'message' => 'Mot de passe modifié avec succès',
        ]);
    }
}