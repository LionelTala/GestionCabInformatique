<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Formation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'abbreviation',
        'tuition_fees',
        'duration_months',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tuition_fees' => 'decimal:2',
            'duration_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // public function students()
    // {
    //     return $this->hasMany(Student::class);
    // }
}