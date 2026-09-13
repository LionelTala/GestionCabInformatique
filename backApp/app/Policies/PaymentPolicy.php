<?php
// app/Policies/PaymentPolicy.php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use App\Traits\HasCampusScope;

class PaymentPolicy
{
    use HasCampusScope;

    /**
     * Voir la liste
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            'super_admin', 'admin_global', 'admin_campus', 'secretary'
        ]);
    }

    /**
     * ✅ Voir un paiement (et télécharger le reçu) :
     * - Secrétaire : TOUT son campus
     * - Admin campus : tout son campus
     * - Admin global : tout
     */
    public function view(User $user, Payment $payment): bool
    {
        return $this->canAccessCampus($user, $payment->campus_id);
    }

    /**
     * Créer un paiement
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [
            'super_admin', 'admin_global', 'admin_campus', 'secretary'
        ]);
    }

    /**
     * ✅ Supprimer un paiement :
     * - Secrétaire : UNIQUEMENT les paiements qu'ELLE a créés
     * - Admin campus : tous ceux de son campus
     * - Admin global : tout
     */
    public function delete(User $user, Payment $payment): bool
    {
        return $this->canModifyResource($user, $payment);
    }
}