<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'email' => env('SUPER_ADMIN_EMAIL'),
            'password' => Hash::make(env('SUPER_ADMIN_PASSWORD')),
            'first_name' => env('SUPER_ADMIN_FIRST_NAME', 'System'),
            'last_name' => env('SUPER_ADMIN_LAST_NAME', 'Administrator'),
            'role' => 'super_admin',
            'campus_id' => null,
            'is_active' => true,
        ]);
    }
}