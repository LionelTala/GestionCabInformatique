<?php
// app/Models/CashMovement.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'campus_id',
        'type',
        'category',
        'amount',
        'title',
        'description',
        'reference',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'created_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'amount'          => 'decimal:2',
            'attachment_size' => 'integer',
        ];
    }

    // ═══ RELATIONS ═══
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // ═══ SCOPES ═══
    public function scopeIncome($query)
    {
        return $query->where('type', 'income');
    }

    public function scopeExpense($query)
    {
        return $query->where('type', 'expense');
    }

    public function scopeForCampus($query, int $campusId)
    {
        return $query->where('campus_id', $campusId);
    }

    // ═══ ACCESSORS ═══
    public function getHasAttachmentAttribute(): bool
    {
        return !empty($this->attachment_path);
    }

    public function getSignedAmountAttribute(): float
    {
        return $this->type === 'income'
            ? (float) $this->amount
            : -(float) $this->amount;
    }
}