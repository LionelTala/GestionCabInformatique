<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
 use Illuminate\Database\Eloquent\SoftDeletes;


class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'campus_id',
        'formation_id',
        'academic_year_id',
        'registration_number',
        'first_name',
        'last_name',
        'date_of_birth',
        'email',
        'phone',
        'address',
        'parent_name',
        'parent_phone',
        'photo',
        'is_active',
        'created_by',
    ];
    protected $dates = ['deleted_at'];
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function formation()
    {
        return $this->belongsTo(Formation::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function registration()
    {
        return $this->hasOne(Registration::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
        public function registrations()
    {
        return $this->hasMany(Registration::class);
    }
}