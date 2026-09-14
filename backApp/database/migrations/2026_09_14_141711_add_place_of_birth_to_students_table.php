<?php
// database/migrations/xxxx_add_place_of_birth_to_students_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // ✅ Lieu de naissance (nullable, texte court)
            $table->string('place_of_birth', 255)->nullable()->after('date_of_birth');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('place_of_birth');
        });
    }
};