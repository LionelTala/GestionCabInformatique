<?php
// app/Policies/CashMovementPolicy.php

namespace App\Policies;

use App\Models\CashMovement;
use App\Models\User;
use App\Traits\HasCampusScope;

class CashMovementPolicy
{
    use HasCampusScope;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'admin_global', 'admin_campus', 'secretary']);
    }

    /**
     * Voir un mouvement :
     * - Secrétaire : UNIQUEMENT les siens
     * - Admin campus : son campus
     * - Admin global : tout
     */
    public function view(User $user, CashMovement $movement): bool
    {
        if ($user->role === 'secretary') {
            return $this->isCreator($user, $movement)
                && $this->canAccessCampus($user, $movement->campus_id);
        }
        return $this->canAccessCampus($user, $movement->campus_id);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'admin_global', 'admin_campus', 'secretary']);
    }

    /**
     * ✅ Supprimer un mouvement :
     * - Secrétaire : UNIQUEMENT les siens (mais elle PEUT le faire)
     * - Admin campus : tout son campus
     * - Admin global : tout
     */
    public function delete(User $user, CashMovement $movement): bool
    {
        return $this->canModifyResource($user, $movement);
    }
}