<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string|min:6',
            ], [
                'email.required' => 'L\'adresse email est obligatoire',
                'email.email' => 'Veuillez saisir une adresse email valide',
                'password.required' => 'Le mot de passe est obligatoire',
                'password.min' => 'Le mot de passe doit contenir au moins 6 caractères',
            ]);

            $ip = $request->ip();
            $lockKey = "login_locked_ip_{$ip}";
            $attemptsKey = "login_attempts_ip_{$ip}";

            // Vérifier si l'IP est bloquée
            if (Cache::has($lockKey)) {
                $ttl = Cache::get($lockKey);
                $minutes = ceil($ttl / 60);
                return response()->json([
                    'message' => "Trop de tentatives depuis cette adresse IP. Réessayez dans {$minutes} minute(s).",
                ], 429);
            }

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                $attempts = Cache::get($attemptsKey, 0) + 1;
                Cache::put($attemptsKey, $attempts, 120);

                $remaining = 5 - $attempts;

                if ($attempts >= 5) {
                    Cache::put($lockKey, 600, 600); // 10 minutes
                    Cache::forget($attemptsKey);
                    return response()->json([
                        'message' => 'Trop de tentatives. Cette adresse IP est bloquée pour 10 minutes pour des raisons de sécurité.',
                    ], 429);
                }

                return response()->json([
                    'message' => "Les identifiants saisis sont incorrects. Il vous reste {$remaining} tentative(s) avant blocage.",
                ], 401);
            }

            // Réinitialiser les tentatives (connexion réussie)
            Cache::forget($attemptsKey);
            Cache::forget($lockKey);

            if (!$user->is_active) {
                return response()->json([
                    'message' => 'Votre compte est désactivé. Veuillez contacter l\'administrateur.',
                ], 403);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Connexion réussie',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'role' => $user->role,
                    'campus_id' => $user->campus_id,
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Veuillez corriger les erreurs ci-dessous',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Vous avez été déconnecté avec succès'
        ]);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }
}