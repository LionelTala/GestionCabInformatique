<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
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

            if (Cache::has($lockKey)) {
                $ttl = Cache::get($lockKey);
                $minutes = ceil($ttl / 60);
                return response()->json([
                    'message' => "Trop de tentatives. Réessayez dans {$minutes} minute(s).",
                ], 429);
            }

            if (!Auth::attempt($request->only('email', 'password'))) {
                $attempts = Cache::get($attemptsKey, 0) + 1;
                Cache::put($attemptsKey, $attempts, 120);
                $remaining = 5 - $attempts;

                if ($attempts >= 5) {
                    Cache::put($lockKey, 600, 600);
                    Cache::forget($attemptsKey);
                    return response()->json([
                        'message' => 'Adresse IP bloquée pendant 10 minutes.',
                    ], 429);
                }

                return response()->json([
                    'message' => "Identifiants incorrects. {$remaining} tentative(s) restante(s).",
                ], 401);
            }

            Cache::forget($attemptsKey);
            Cache::forget($lockKey);

            $user = Auth::user();

            if (!$user->is_active) {
                Auth::logout();
                return response()->json([
                    'message' => 'Compte désactivé. Contactez l\'administrateur.',
                ], 403);
            }

            $campus = $user->campus;

            return response()->json([
                'message' => 'Connexion réussie',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'role' => $user->role,
                    'campus_id' => $user->campus_id,
                    'campus' => $user->campus,
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Veuillez corriger les erreurs',
                'errors' => $e->errors(),
            ], 422);
        }
    }

        public function me(Request $request)
        {
            $user = User::with('campus:id,nom,ville')->find($request->user()->id);

            return response()->json([
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'role' => $user->role,
                'campus_id' => $user->campus_id,
                'campus' => $user->campus,
            ]);
        }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Déconnexion réussie'
        ]);
    }
}