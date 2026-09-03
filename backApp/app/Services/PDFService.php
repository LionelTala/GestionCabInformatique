<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

class PDFService
{
    public function generateRegistrationForm($registration, $qrCodeBase64)
    {
        $student = $registration->student;
        $formation = $registration->formation;
        $campus = $registration->campus;
        $academicYear = $registration->academicYear;

        $amountPaid = $registration->amount_paid;
        $tuitionFees = $formation->tuition_fees;
        $remainingAmount = $tuitionFees - $amountPaid;

        $pdf = Pdf::loadView('pdfs.registration_form', [
            'registration' => $registration,
            'student' => $student,
            'formation' => $formation,
            'campus' => $campus,
            'academicYear' => $academicYear,
            'qrCodeBase64' => $qrCodeBase64, // Déjà au format "data:image/..."
            'amountPaid' => $amountPaid,
            'remainingAmount' => $remainingAmount,
        ]);

        $pdf->setPaper('a4', 'portrait');
        return $pdf->output();
    }
    public function generatePaymentReceipt($payment, $qrCodeBase64, $user) // ✅ AJOUTE $user ICI
    {
        $pdf = Pdf::loadView('pdfs.payment_receipt', [
            'payment' => $payment,
            'student' => $payment->registration->student,
            'formation' => $payment->registration->formation,
            'registration' => $payment->registration,
            'campus' => $payment->registration->campus,
            'qrCodeBase64' => $qrCodeBase64,
            'user' => $user, // ✅ UTILISE LE PARAMÈTRE PASSÉ
        ]);
        
        $pdf->setPaper('a4', 'portrait'); // ou 'a4' selon ta préférence
        return $pdf->output();
    }
}