<?php
// app/Http/Controllers/Api/DocumentVerificationController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Models\Payment;
use Illuminate\Http\Request;

class DocumentVerificationController extends Controller
{
    public function verify(Request $request)
    {
        $encodedPayload = $request->query('q');

        if (!$encodedPayload) {
            return view('verification.result', [
                'status'  => 'error',
                'message' => 'Aucun code de vérification fourni dans l\'URL.',
                'data'    => null,
            ]);
        }

        try {
            // Décoder
            $decoded = base64_decode($encodedPayload);
            $payload = json_decode($decoded, true);

            if (!$payload || !isset($payload['type'], $payload['data'], $payload['sig'])) {
                throw new \Exception('Format de données invalide.');
            }

            // Vérifier signature
            if (!verifyDocumentSignature($payload['data'], $payload['sig'])) {
                return view('verification.result', [
                    'status'  => 'invalid',
                    'message' => '⚠️ Document falsifié ou altéré. La signature ne correspond pas.',
                    'data'    => null,
                ]);
            }

            // ✅ Router selon le type de document
            return match ($payload['type']) {
                'registration' => $this->verifyRegistration($payload['data']),
                'payment'      => $this->verifyPayment($payload['data']),
                default        => view('verification.result', [
                    'status'  => 'error',
                    'message' => 'Type de document non reconnu.',
                    'data'    => null,
                ]),
            };

        } catch (\Exception $e) {
            return view('verification.result', [
                'status'  => 'error',
                'message' => 'Erreur lors de la vérification : ' . $e->getMessage(),
                'data'    => null,
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ VÉRIFICATION D'UNE INSCRIPTION ═══
    // ═══════════════════════════════════════════════════════════
    private function verifyRegistration(array $qrData)
    {
        $registrationId = $qrData['registration_id'] ?? null;

        if (!$registrationId) {
            throw new \Exception('ID d\'inscription manquant.');
        }

        $registration = Registration::withTrashed()
            ->with(['student', 'formation', 'campus', 'academicYear', 'scolarity'])
            ->find($registrationId);

        if (!$registration) {
            return view('verification.result', [
                'status'  => 'not_found',
                'message' => '❌ Inscription introuvable dans le système.',
                'data'    => null,
            ]);
        }

        // ✅ CAS : inscription supprimée
        if ($registration->trashed()) {
            $student = $registration->student()->withTrashed()->first();

            return view('verification.result', [
                'status'  => 'annulled',
                'message' => 'Ce document a été annulé par l\'administration.',
                'data'    => [
                    'Matricule'          => $student?->registration_number ?? 'Non disponible',
                    'Étudiant'           => $student ? ($student->first_name . ' ' . $student->last_name) : 'Non disponible',
                    'Campus concerné'    => $registration->campus?->name ?? 'Non disponible',
                    'Date d\'annulation' => $registration->deleted_at->format('d/m/Y à H:i'),
                ],
                'campus' => $registration->campus,
            ]);
        }

        // ✅ CAS : inscription valide
        $formation = $registration->formation;
        $scolarity = $registration->scolarity;

        $tuitionFees = (float) (
            $registration->custom_tuition
            ?? $scolarity?->tuition_fees
            ?? $formation->tuition_fees
            ?? 0
        );

        $amountPaid      = (float) ($scolarity?->amount_paid ?? $registration->amount_paid ?? 0);
        $remainingAmount = (float) ($scolarity?->balance ?? max(0, $tuitionFees - $amountPaid));
        $paymentStatus   = $scolarity?->status ?? $registration->payment_status ?? 'unpaid';

        $cataloguePrice = (float) $formation->tuition_fees;
        $hasPromo       = $tuitionFees < $cataloguePrice;
        $discountAmount = $hasPromo ? $cataloguePrice - $tuitionFees : 0;

        $statusLabels = [
            'paid'    => 'Soldé ✅',
            'partial' => 'Partiel ⚠️',
            'unpaid'  => 'Non payé 🔴',
        ];

        return view('verification.result', [
            'status'  => 'valid',
            'message' => '✅ Document authentique et vérifié avec succès.',
            'data'    => [
                'Matricule'           => $registration->student->registration_number,
                'Étudiant'            => $registration->student->first_name . ' ' . $registration->student->last_name,
                'Formation'           => $formation->name,
                'Campus'              => $registration->campus->name,
                'Année scolaire'      => $registration->academicYear->label,
                'Date d\'inscription' => $registration->created_at->format('d/m/Y'),

                'Frais de scolarité'  => number_format($tuitionFees, 0, ',', ' ') . ' FCFA',
                'Montant versé'       => number_format($amountPaid, 0, ',', ' ') . ' FCFA',
                'Reste à payer'       => number_format($remainingAmount, 0, ',', ' ') . ' FCFA',
                'Statut financier'    => $statusLabels[$paymentStatus] ?? ucfirst($paymentStatus),

                'promo' => $hasPromo ? [
                    'catalogue' => number_format($cataloguePrice, 0, ',', ' ') . ' FCFA',
                    'applique'  => number_format($tuitionFees, 0, ',', ' ') . ' FCFA',
                    'economie'  => number_format($discountAmount, 0, ',', ' ') . ' FCFA',
                ] : null,
            ],
            'campus' => $registration->campus,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ VÉRIFICATION D'UN REÇU DE PAIEMENT ═══
    // ═══════════════════════════════════════════════════════════
    private function verifyPayment(array $qrData)
    {
        $paymentId = $qrData['payment_id'] ?? null;

        if (!$paymentId) {
            throw new \Exception('ID paiement manquant.');
        }

        // ✅ Récupérer avec trashed (détecter annulation)
        $payment = Payment::withTrashed()
            ->with(['registration.student', 'registration.formation', 'registration.campus'])
            ->find($paymentId);

        if (!$payment) {
            return view('verification.result', [
                'status'  => 'not_found',
                'message' => '❌ Reçu introuvable dans le système.',
                'data'    => null,
            ]);
        }

        // ✅ CAS : paiement annulé
        if ($payment->trashed()) {
            return view('verification.result', [
                'status'  => 'annulled',
                'message' => 'Ce reçu a été annulé. Le paiement associé n\'est plus valide.',
                'data'    => [
                    'N° Reçu'            => $qrData['reference'] ?? '-',
                    'Étudiant'           => $qrData['name'] ?? '-',
                    'Matricule'          => $qrData['matricule'] ?? '-',
                    'Formation'          => $qrData['formation_name'] ?? '-',
                    'Montant versé'      => number_format($qrData['payment_amount'] ?? 0, 0, ',', ' ') . ' FCFA',
                    'Date du paiement'   => $qrData['payment_date'] ?? '-',
                    'Date d\'annulation' => $payment->deleted_at?->format('d/m/Y à H:i') ?? '-',
                ],
                'campus' => $payment->registration->campus ?? null,
            ]);
        }

        // ✅ CAS : paiement valide → on affiche les INFOS FIGÉES
        $statusLabels = [
            'paid'    => 'Soldé ✅',
            'partial' => 'Partiel ⚠️',
            'unpaid'  => 'Non payé 🔴',
        ];

        return view('verification.result', [
            'status'  => 'valid',
            'message' => '✅ Reçu authentique et vérifié avec succès.',
            'data'    => [
                'N° Reçu'                    => $qrData['reference'] ?? '-',
                'Date du paiement'           => $qrData['payment_date'] ?? '-',
                'Étudiant'                   => $qrData['name'] ?? '-',
                'Matricule'                  => $qrData['matricule'] ?? '-',
                'Formation'                  => $qrData['formation_name'] ?? '-',

                // ✅ Montants FIGÉS (au moment du reçu)
                'Frais de scolarité'         => number_format($qrData['tuition_fees_at_payment'] ?? 0, 0, ',', ' ') . ' FCFA',
                'Montant versé (ce reçu)'    => number_format($qrData['payment_amount'] ?? 0, 0, ',', ' ') . ' FCFA',
                'Total versé à cette date'   => number_format($qrData['amount_paid_at_payment'] ?? 0, 0, ',', ' ') . ' FCFA',
                'Reste à payer à cette date' => number_format($qrData['balance_at_payment'] ?? 0, 0, ',', ' ') . ' FCFA',
                'Statut à cette date'        => $statusLabels[$qrData['status_at_payment'] ?? 'unpaid'] ?? '-',
            ],
            'campus'    => $payment->registration->campus ?? null,
            'is_frozen' => true,  // ✅ Indicateur pour signaler que les données sont historiques
        ]);
    }
}