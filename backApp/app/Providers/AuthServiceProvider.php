<?php
// app/Providers/AuthServiceProvider.php

namespace App\Providers;

use App\Models\CashMovement;
use App\Models\FinancialTransaction;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Student;
use App\Policies\CashMovementPolicy;
use App\Policies\FinancialTransactionPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\RegistrationPolicy;
use App\Policies\StudentPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * ✅ Enregistrement des Policies
     */
    protected $policies = [
        Registration::class         => RegistrationPolicy::class,
        Payment::class              => PaymentPolicy::class,
        Student::class              => StudentPolicy::class,
        CashMovement::class         => CashMovementPolicy::class,
        FinancialTransaction::class => FinancialTransactionPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}