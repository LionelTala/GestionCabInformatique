<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\Registration;
use App\Models\Scolarity;

class PaymentObserver
{
    /**
     * Méthode centrale pour synchroniser la Scolarity.
     * amount_paid = initial_payment (figé) + SUM(payments confirmés)
     */
    private function syncScolarity(Payment $payment): void
    {
        $registration = Registration::with('formation')->find($payment->registration_id);
        
        if (!$registration) return;

        $scolarity = Scolarity::where('registration_id', $registration->id)->first();
        
        if (!$scolarity) return;

        // Calcul du nouveau montant payé
        $initialPayment = (float) $registration->initial_payment;
        $paymentsSum = (float) $registration->payments()
            ->where('status', 'confirmed')
            ->sum('amount');
        
        $newAmountPaid = $initialPayment + $paymentsSum;
        $tuitionFees = (float) $registration->formation->tuition_fees;
        $newBalance = $tuitionFees - $newAmountPaid;
        
        // Détermination du statut
        $newStatus = 'unpaid';
        if ($newAmountPaid <= 0) {
            $newStatus = 'unpaid';
        } elseif ($newAmountPaid >= $tuitionFees) {
            $newStatus = 'paid';
        } else {
            $newStatus = 'partial';
        }

        // Mise à jour si changement
        if ($scolarity->amount_paid != $newAmountPaid || 
            $scolarity->balance != $newBalance || 
            $scolarity->status !== $newStatus) {
            
            $scolarity->update([
                'amount_paid' => $newAmountPaid,
                'balance' => $newBalance,
                'status' => $newStatus,
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
        // Soft delete : le paiement est exclu de la somme
        $this->syncScolarity($payment);
    }

    public function restored(Payment $payment): void
    {
        // Restauration : le paiement réintègre la somme
        $this->syncScolarity($payment);
    }
}