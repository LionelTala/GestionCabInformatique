<?php
// app/Traits/HasCampusScope.php

namespace App\Traits;

trait HasCampusScope
{
    /**
     * ✅ Vérifie si l'utilisateur peut accéder à un campus donné.
     */
    protected function canAccessCampus($user, $campusId): bool
    {
        if (!$user) return false;

        // Super admin / admin global : accès total
        if (in_array($user->role, ['super_admin', 'admin_global'])) {
            return true;
        }

        // Admin campus / secrétaire : uniquement leur campus
        if (in_array($user->role, ['admin_campus', 'secretary'])) {
            if (!$user->campus_id) return false; // ✅ sécurité : pas de campus = pas d'accès
            return (int) $user->campus_id === (int) $campusId;
        }

        return false;
    }

    /**
     * ✅ Vérifie si l'utilisateur est le créateur d'une ressource.
     */
    protected function isCreator($user, $resource): bool
    {
        if (!$user || !$resource) return false;
        return (int) $resource->created_by === (int) $user->id;
    }

    /**
     * ✅ Règle combinée : accès campus + est propriétaire (pour secrétaire).
     * Utilisé pour les suppressions.
     */
    protected function canModifyResource($user, $resource): bool
    {
        if (!$user || !$resource) return false;

        // Super admin / admin global : toujours OK
        if (in_array($user->role, ['super_admin', 'admin_global'])) {
            return true;
        }

        // Admin campus : tout sur son campus
        if ($user->role === 'admin_campus') {
            return $this->canAccessCampus($user, $resource->campus_id);
        }

        // Secrétaire : uniquement SES ressources (créées par elle)
        if ($user->role === 'secretary') {
            return $this->isCreator($user, $resource)
                && $this->canAccessCampus($user, $resource->campus_id);
        }

        return false;
    }
}