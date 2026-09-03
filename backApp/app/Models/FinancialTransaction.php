<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class FinancialTransaction extends Model
{
    use HasFactory , SoftDeletes;

    protected $fillable = [
        'registration_id',
        'campus_id',
        'type',
        'category',
        'amount',
        'description',
        'reference',
        'created_by',
    ];
    protected $dates = ['deleted_at'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}