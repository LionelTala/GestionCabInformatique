<?php
// database/migrations/xxxx_create_attestations_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attestations', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 50)->unique();

            $table->foreignId('registration_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('campus_id')->constrained()->onDelete('cascade');

            $table->enum('status', ['pending', 'ready'])->default('pending');

 
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamp('requested_at');

            $table->foreignId('settled_by')->nullable()->constrained('users');
            $table->timestamp('settled_at')->nullable();

            $table->foreignId('cancelled_by')->nullable()->constrained('users');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['campus_id', 'status', 'created_at']);
            $table->index(['student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attestations');
    }
};