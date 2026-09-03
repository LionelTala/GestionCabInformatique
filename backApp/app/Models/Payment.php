<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Observers\PaymentObserver; // ← 1. Import de l'observer

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'registration_id',
        'campus_id',
        'student_id',
        'amount',
        'payment_date',
        'reference',
        'status',
        'created_by',
    ];

    // Modernisation optionnelle : $dates est déprécié au profit de casts
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'deleted_at' => 'datetime', 
        ];
    }

    // ═══ 2. ENREGISTREMENT DE L'OBSERVATEUR ═══
    protected static function booted(): void
    {
        static::observe(PaymentObserver::class);
    }

    // ═══ RELATIONS ═══
    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}