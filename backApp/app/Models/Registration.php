<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Registration extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'campus_id',
        'formation_id',
        'academic_year_id',
        'initial_payment', // ← RENOMMÉ
        'status',
        'qr_code_hash',
        'created_by',
        'qr_signature',
    ];

    protected function casts(): array
    {
        return [
            'initial_payment' => 'decimal:2',
        ];
    }

    // ═══ RELATIONS ═══
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    public function scolarity(): HasOne
    {
        return $this->hasOne(Scolarity::class);
    }

    // ═══ ACCESSEURS ═══
    
    /**
     * Montant total payé = initial_payment + somme des paiements confirmés
     */
    public function getAmountPaidAttribute(): float
    {
        $initialPayment = (float) $this->initial_payment;
        $paymentsSum = $this->payments()
            ->where('status', 'confirmed')
            ->sum('amount');
        
        return $initialPayment + (float) $paymentsSum;
    }

    /**
     * Reste à payer = prix formation - montant payé
     */
    public function getBalanceAttribute(): float
    {
        $tuitionFees = $this->formation->tuition_fees ?? 0;
        return $tuitionFees - $this->amount_paid;
    }

    /**
     * Statut de paiement dynamique
     */
    public function getPaymentStatusAttribute(): string
    {
        $paid = $this->amount_paid;
        $total = $this->formation->tuition_fees ?? 0;

        if ($paid <= 0) return 'unpaid';
        if ($paid >= $total) return 'paid';
        return 'partial';
    }
}