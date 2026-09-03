<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Scolarity extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_id',
        'student_id',
        'campus_id',
        'tuition_fees',
        'amount_paid',
        'balance',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tuition_fees' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    // ═══ RELATIONS ═══
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }
}