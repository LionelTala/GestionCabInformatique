<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

class PDFService
{
    // ═══════════════════════════════════════════════════════════
    // ✅ FICHE D'INSCRIPTION (avec promo)
    // ═══════════════════════════════════════════════════════════
    public function generateRegistrationForm($registration, $qrCodeBase64)
    {
        // S'assurer que les relations sont chargées
        $registration->load(['student', 'formation', 'campus', 'academicYear', 'scolarity']);

        $student      = $registration->student;
        $formation    = $registration->formation;
        $campus       = $registration->campus;
        $academicYear = $registration->academicYear;
        $scolarity    = $registration->scolarity;

        // ✅ SOURCE DE VÉRITÉ : registration.custom_tuition (avec fallback)
        $tuitionFees  = (float) (
            $registration->custom_tuition
            ?? $scolarity?->tuition_fees
            ?? $formation->tuition_fees
            ?? 0
        );

        $amountPaid       = (float) ($scolarity?->amount_paid ?? $registration->amount_paid ?? 0);
        $remainingAmount  = max(0, $tuitionFees - $amountPaid);

        // ✅ Détection promo
        $hasPromo = $tuitionFees < (float) $formation->tuition_fees;

        $pdf = Pdf::loadView('pdfs.registration_form', [
            'registration'    => $registration,
            'student'         => $student,
            'formation'       => $formation,
            'campus'          => $campus,
            'academicYear'    => $academicYear,
            'qrCodeBase64'    => $qrCodeBase64,
            'amountPaid'      => $amountPaid,
            'tuitionFees'     => $tuitionFees,
            'remainingAmount' => $remainingAmount,
            'hasPromo'        => $hasPromo,
        ]);

        $pdf->setPaper('a4', 'portrait');
        return $pdf->output();
    }

    // ═══════════════════════════════════════════════════════════
    // ✅ REÇU DE PAIEMENT (avec promo + cumul)
    // ═══════════════════════════════════════════════════════════
    public function generatePaymentReceipt($payment, $qrCodeBase64, $user)
    {
        // S'assurer que toutes les relations sont chargées
        $payment->load([
            'registration.student',
            'registration.formation',
            'registration.campus',
            'registration.academicYear',
            'registration.scolarity',
        ]);

        $registration = $payment->registration;
        $student      = $registration->student;
        $formation    = $registration->formation;
        $campus       = $registration->campus;
        $academicYear = $registration->academicYear;
        $scolarity    = $registration->scolarity;

        // ✅ SOURCE DE VÉRITÉ : registration.custom_tuition (avec fallback)
        $tuitionFees  = (float) (
            $registration->custom_tuition
            ?? $scolarity?->tuition_fees
            ?? $formation->tuition_fees
            ?? 0
        );

        // ✅ Montants financiers
        $amountPaid       = (float) ($scolarity?->amount_paid ?? 0);       // total versé à ce jour
        $remainingAmount  = (float) ($scolarity?->balance ?? 0);           // reste à payer actuel
        $currentPayment   = (float) $payment->amount;                       // ce versement
        $balanceBefore    = $amountPaid - $currentPayment;                  // solde avant ce versement
        $paymentStatus    = $scolarity?->status ?? 'unpaid';

        // ✅ Détection promo
        $hasPromo = $tuitionFees < (float) $formation->tuition_fees;
        $discountAmount = $hasPromo 
            ? (float) $formation->tuition_fees - $tuitionFees 
            : 0;

        // ✅ Pourcentage de progression
        $progressPercent = $tuitionFees > 0 
            ? min(100, round(($amountPaid / $tuitionFees) * 100, 1)) 
            : 0;

        $pdf = Pdf::loadView('pdfs.payment_receipt', [
            'payment'         => $payment,
            'registration'    => $registration,
            'student'         => $student,
            'formation'       => $formation,
            'campus'          => $campus,
            'academicYear'    => $academicYear,
            'scolarity'       => $scolarity,
            'qrCodeBase64'    => $qrCodeBase64,
            'user'            => $user,

            // ✅ Montants clés
            'tuitionFees'     => $tuitionFees,        // frais totaux (avec promo)
            'amountPaid'      => $amountPaid,         // total versé
            'remainingAmount' => $remainingAmount,    // reste à payer
            'currentPayment'  => $currentPayment,     // ce versement
            'balanceBefore'   => $balanceBefore,      // solde avant ce versement

            // ✅ Promo
            'hasPromo'        => $hasPromo,
            'discountAmount'  => $discountAmount,
            'cataloguePrice'  => (float) $formation->tuition_fees,

            // ✅ Statut
            'paymentStatus'   => $paymentStatus,
            'progressPercent' => $progressPercent,
        ]);

        $pdf->setPaper('a4', 'portrait');
        return $pdf->output();
    }
}