<?php
// app/Policies/RegistrationPolicy.php

namespace App\Policies;

use App\Models\Registration;
use App\Models\User;
use App\Traits\HasCampusScope;
use Illuminate\Auth\Access\Response;

class RegistrationPolicy
{
    use HasCampusScope;

    /**
     * Voir la liste / détails
     */
    public function view(User $user, Registration $registration): bool
    {
        return $this->canAccessCampus($user, $registration->campus_id);
    }

    /**
     * Voir la liste sans instance
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'admin_global', 'admin_campus', 'secretary']);
    }

    /**
     * Créer
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'admin_global', 'admin_campus', 'secretary']);
    }

    /**
     * Modifier
     */
    public function update(User $user, Registration $registration): bool
    {
        return $this->canAccessCampus($user, $registration->campus_id);
    }

    /**
     * ✅ Supprimer :
     * - Secrétaire : uniquement les inscriptions qu'ELLE a créées
     * - Admin campus : toutes celles de son campus
     * - Admin global : tout
     */
    public function delete(User $user, Registration $registration): bool
    {
        return $this->canModifyResource($user, $registration);
    }
}