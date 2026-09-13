<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'login_input' => 'required|string',
                'password' => 'required|string|min:6',
            ], [
                'login_input.required' => 'L\'identifiant (email ou nom d\'utilisateur) est obligatoire',
                'password.required' => 'Le mot de passe est obligatoire',
                'password.min' => 'Le mot de passe doit contenir au moins 6 caractères',
            ]);

            $ip = $request->ip();
            $lockKey = "login_locked_ip_{$ip}";
            $attemptsKey = "login_attempts_ip_{$ip}";

            if (Cache::has($lockKey)) {
                $ttl = Cache::get($lockKey); // 🚨 Correction TTL (voir explications)
                $minutes = is_numeric($ttl) ? ceil($ttl / 60) : 10;
                return response()->json([
                    'message' => "Trop de tentatives. Réessayez dans {$minutes} minute(s).",
                ], 429);
            }

            // DÉTECTION : Est-ce un email ou un username ?
            $loginInput = $request->login_input;
            $field = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

            // TENTATIVE DE CONNEXION DYNAMIQUE
            if (!Auth::attempt([$field => $loginInput, 'password' => $request->password])) {
                $attempts = Cache::get($attemptsKey, 0) + 1;
                Cache::put($attemptsKey, $attempts, 120);
                $remaining = 5 - $attempts;

                if ($attempts >= 5) {
                    Cache::put($lockKey, time() + 600, 600); // 🚨 Stocke le timestamp actuel + 10 min
                    Cache::forget($attemptsKey);
                    return response()->json(['message' => 'Adresse IP bloquée pendant 10 minutes.'], 429);
                }

                return response()->json([
                    'message' => "Identifiants incorrects. {$remaining} tentative(s) restante(s).",
                ], 401);
            }

            $user = Auth::user();

            if (!$user->is_active) {
                Auth::logout();
                return response()->json(['message' => 'Compte désactivé. Contactez l\'administrateur.'], 403);
            }

            // 🚨 INDISPENSABLE POUR COOKIES SANCTUM : Génère l'ID de session après un login réussi
            $request->session()->regenerate();

            Cache::forget($attemptsKey);
            Cache::forget($lockKey);

            // Charger la relation campus pour correspondre à votre structure
            $user->load('campus:id,name,city');

            return response()->json([
                'message' => 'Connexion réussie',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'username' => $user->username,
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
        $user = User::with('campus:id,name,city')->find($request->user()->id);

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'username' => $user->username,
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
        
        // 🚨 Correction : Invalider et régénérer le token CSRF pour détruire proprement le cookie de session
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Déconnexion réussie'
        ]);
    }
}
