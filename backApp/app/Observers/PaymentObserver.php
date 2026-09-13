<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\Registration;
use App\Models\Scolarity;

class PaymentObserver
{
    /**
     * Synchronise la Scolarity après tout changement de Payment.
     * 
     * amount_paid = initial_payment (figé) + SUM(payments confirmés non supprimés)
     * tuition_fees = registration.custom_tuition (SOURCE DE VÉRITÉ)
     * 
     * ⚠️ Ordre de priorité pour le prix :
     *   1. registration.custom_tuition  ← toujours rempli depuis le fix
     *   2. formation.tuition_fees       ← fallback pour anciennes lignes
     */
    private function syncScolarity(Payment $payment): void
    {
        $registration = Registration::with(['formation', 'scolarity'])->find($payment->registration_id);

        if (!$registration) return;

        /** @var Scolarity|null $scolarity */
        $scolarity = $registration->scolarity;
        if (!$scolarity) return;

        // ─── 1. Montant payé ───
        $initialPayment = (float) $registration->initial_payment;
        $paymentsSum = (float) $registration->payments()
            ->where('status', 'confirmed')
            ->sum('amount');

        $newAmountPaid = $initialPayment + $paymentsSum;

        // ─── 2. ✅ Prix réel = registration.custom_tuition (source de vérité) ───
        $tuitionFees = (float) (
            $registration->custom_tuition
            ?? $registration->formation->tuition_fees
        );

        // ─── 3. Balance & statut ───
        $newBalance = max(0, $tuitionFees - $newAmountPaid);

        $newStatus = 'unpaid';
        if ($newAmountPaid <= 0) {
            $newStatus = 'unpaid';
        } elseif ($newAmountPaid >= $tuitionFees) {
            $newStatus = 'paid';
        } else {
            $newStatus = 'partial';
        }

        // ─── 4. Mise à jour ───
        // On met à jour scolarity.tuition_fees aussi (pour rester synchronisé)
        if (
            (float) $scolarity->amount_paid   !== $newAmountPaid ||
            (float) $scolarity->balance       !== $newBalance ||
            (float) $scolarity->tuition_fees  !== $tuitionFees ||
            $scolarity->status                !== $newStatus
        ) {
            $scolarity->update([
                'tuition_fees' => $tuitionFees,
                'amount_paid'  => $newAmountPaid,
                'balance'      => $newBalance,
                'status'       => $newStatus,
            ]);
        }
    }

    public function created(Payment $payment): void
    {
        $this->syncScolarity($payment);
    }

    public function updated(Payment $payment): void
    {
        if ($payment->isDirty('amount') || $payment->isDirty('status')) {
            $this->syncScolarity($payment);
        }
    }

    public function deleted(Payment $payment): void
    {
        // Soft delete : le paiement est exclu de la somme (grâce au global scope)
        $this->syncScolarity($payment);
    }

    public function restored(Payment $payment): void
    {
        // Restauration : le paiement réintègre la somme
        $this->syncScolarity($payment);
    }
}