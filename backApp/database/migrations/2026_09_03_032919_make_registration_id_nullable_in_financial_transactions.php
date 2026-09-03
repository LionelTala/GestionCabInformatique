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
        Schema::table('financial_transactions', function (Blueprint $table) {
            // Supprimer la contrainte foreign key existante
            $table->dropForeign(['registration_id']);
            
            // Rendre nullable
            $table->foreignId('registration_id')->nullable()->change();
            
            // Réajouter la contrainte avec onDelete set null
            $table->foreign('registration_id')->references('id')->on('registrations')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropForeign(['registration_id']);
            $table->foreignId('registration_id')->nullable(false)->change();
            $table->foreign('registration_id')->references('id')->on('registrations')->onDelete('cascade');
        });
    }
};
