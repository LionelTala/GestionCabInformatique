<?php
// app/Policies/AttestationPolicy.php

namespace App\Policies;

use App\Models\Attestation;
use App\Models\User;
use App\Traits\HasCampusScope;

class AttestationPolicy
{
    use HasCampusScope;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'admin_global', 'admin_campus', 'secretary']);
    }

    public function view(User $user, Attestation $attestation): bool
    {
        return $this->canAccessCampus($user, $attestation->campus_id);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'admin_global', 'admin_campus', 'secretary']);
    }

    /**
     * Régler / Remettre en attente
     */
    public function settle(User $user, Attestation $attestation): bool
    {
        return $this->canAccessCampus($user, $attestation->campus_id);
    }
 
/**
 * Annuler une demande en attente
 */
public function cancel(User $user, Attestation $attestation): bool
{
    return $this->canAccessCampus($user, $attestation->campus_id);
}
}