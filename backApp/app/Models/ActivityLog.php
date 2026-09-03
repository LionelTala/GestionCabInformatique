<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Observers\ActivityLogObserver; // ← 1. Import de l'observer

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_role',
        'campus_id',
        'action',
        'target_type',
        'target_id',
        'target_name',
        'old_data',
        'new_data',
        'changes',
    ];

    protected function casts(): array
    {
        return [
            'old_data' => 'array',
            'new_data' => 'array',
        ];
    }

    // ═══ 2. CHARGEMENT AUTOMATIQUE DES RELATIONS ═══
    // Le front Angular recevra directement user et campus dans chaque log
    protected $with = ['user', 'campus'];

    // ═══ 3. ENREGISTREMENT DE L'OBSERVATEUR ═══
    protected static function booted(): void
    {
        static::observe(ActivityLogObserver::class);
    }

    // ═══ RELATIONS ═══
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }
}