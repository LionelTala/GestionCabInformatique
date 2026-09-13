<?php
// app/Policies/FinancialTransactionPolicy.php

namespace App\Policies;

use App\Models\FinancialTransaction;
use App\Models\User;
use App\Traits\HasCampusScope;

class FinancialTransactionPolicy
{
    use HasCampusScope;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'admin_global', 'admin_campus', 'secretary']);
    }

    public function view(User $user, FinancialTransaction $transaction): bool
    {
        return $this->canAccessCampus($user, $transaction->campus_id);
    }
}