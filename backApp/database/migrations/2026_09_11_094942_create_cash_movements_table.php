<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_cash_movements_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();

            // Rattachement
            $table->foreignId('campus_id')
                  ->constrained('campuses')
                  ->onDelete('cascade');

            // Type & catégorie
            $table->enum('type', ['income', 'expense']);
            $table->string('category', 50);

            // Détails
            $table->decimal('amount', 12, 2);
            $table->string('title', 200);
            $table->text('description')->nullable();

            // Référence unique générée
            $table->string('reference', 100)->unique();

            // Pièce jointe (nullable)
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime', 100)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();

            // Auteur
            $table->foreignId('created_by')
                  ->constrained('users')
                  ->onDelete('restrict');

            // Suppression traçable (soft delete)
            $table->softDeletes();
            $table->foreignId('deleted_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');

            $table->timestamps();

            // Index pour les filtres fréquents
            $table->index(['campus_id', 'type', 'created_at']);
            $table->index(['created_by']);
            $table->index(['type', 'category']);
            $table->index(['deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};