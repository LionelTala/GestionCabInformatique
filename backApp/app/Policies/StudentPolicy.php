<?php
// app/Policies/StudentPolicy.php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;
use App\Traits\HasCampusScope;

class StudentPolicy
{
    use HasCampusScope;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'admin_global', 'admin_campus', 'secretary']);
    }

    public function view(User $user, Student $student): bool
    {
        return $this->canAccessCampus($user, $student->campus_id);
    }

    /**
     * ✅ Tout le monde peut modifier (secrétaire incluse) sur son campus
     */
    public function update(User $user, Student $student): bool
    {
        return $this->canAccessCampus($user, $student->campus_id);
    }

    /**
     * ✅ Suppression : uniquement admin (pas la secrétaire)
     */
    public function delete(User $user, Student $student): bool
    {
        if (!in_array($user->role, ['super_admin', 'admin_global', 'admin_campus'])) {
            return false;
        }
        return $this->canAccessCampus($user, $student->campus_id);
    }
}