<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->onDelete('cascade');
            $table->foreignId('campus_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date')->default(now());
            $table->string('reference')->unique();
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('confirmed');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
