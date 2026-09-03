<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use Illuminate\Http\Request;

class DocumentVerificationController extends Controller
{
    public function verify(Request $request)
    {
        $encodedPayload = $request->query('q');

        // 1. Si pas de paramètre, afficher une erreur
        if (!$encodedPayload) {
            return view('verification.result', [
                'status' => 'error',
                'message' => 'Aucun code de vérification fourni dans l\'URL.',
                'data' => null
            ]);
        }

        try {
            // 2. Décoder le payload
            $decoded = base64_decode($encodedPayload);
            $payload = json_decode($decoded, true);

            if (!$payload || !isset($payload['type'], $payload['data'], $payload['sig'])) {
                throw new \Exception('Format de données invalide.');
            }

            // 3. Vérifier la signature cryptographique
            $isValidSignature = verifyDocumentSignature($payload['data'], $payload['sig']);

            if (!$isValidSignature) {
                return view('verification.result', [
                    'status' => 'invalid',
                    'message' => '⚠️ Document falsifié ou altéré. La signature ne correspond pas.',
                    'data' => null
                ]);
            }

            // 4. Vérifier l'existence en base de données
            $registrationId = $payload['data']['registration_id'] ?? null;
            
            if (!$registrationId) {
                throw new \Exception('ID d\'inscription manquant dans le QR code.');
            }

            $registration = Registration::withTrashed()
                ->with(['student', 'formation', 'campus', 'academicYear'])
                ->find($registrationId);

            if (!$registration) {
                return view('verification.result', [
                    'status' => 'not_found',
                    'message' => '❌ Inscription introuvable dans le système.',
                    'data' => null
                ]);
            }

            // 5. Vérifier si le document est caduc (supprimé)
            if ($registration->trashed()) {
                // ✅ Charger le student avec withTrashed() car lui aussi est soft-deleted
                $student = $registration->student()->withTrashed()->first();
                
                return view('verification.result', [
                    'status' => 'annulled',
                    'message' => 'Ce document a été annulé par l\'administration.',
                    'data' => [
                        'Matricule' => $student?->registration_number ?? 'Non disponible',
                        'Étudiant' => $student ? ($student->first_name . ' ' . $student->last_name) : 'Non disponible',
                        'Campus concerné' => $registration->campus?->name ?? 'Non disponible',
                        'Date d\'annulation' => $registration->deleted_at->format('d/m/Y à H:i'),
                    ],
                    'campus' => $registration->campus, // ✅ On passe le campus pour l'afficher en bas
                ]);
            }

            // 6. Document valide ! On renvoie les données ACTUELLES de la base
            return view('verification.result', [
                'status' => 'valid',
                'message' => '✅ Document authentique et vérifié avec succès.',
                'data' => [
                    'Matricule' => $registration->student->registration_number,
                    'Étudiant' => $registration->student->first_name . ' ' . $registration->student->last_name,
                    'Formation' => $registration->formation->name,
                    'Campus' => $registration->campus->name,
                    'Année scolaire' => $registration->academicYear->label,
                    'Date d\'inscription' => $registration->created_at->format('d/m/Y'),
                    'Frais de scolarité' => number_format($registration->formation->tuition_fees, 0, ',', ' ') . ' FCFA',
                    'Montant versé' => number_format($registration->amount_paid, 0, ',', ' ') . ' FCFA',
                    'Reste à payer' => number_format($registration->balance, 0, ',', ' ') . ' FCFA',
                    'Statut financier' => ucfirst($registration->payment_status),
                    'campus' => $registration->campus,
                ]
            ]);

        } catch (\Exception $e) {
            return view('verification.result', [
                'status' => 'error',
                'message' => 'Erreur lors de la vérification : ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }
}