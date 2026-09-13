<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // ── 1. TABLE STUDENTS : Nouveaux champs ──
        Schema::table('students', function (Blueprint $table) {
            // Renommer address en residence (SQLite compatible)
            // On crée la nouvelle colonne et on migrera les données manuellement
            $table->string('residence')->nullable()->after('phone');
            
            // Nouveaux champs académiques
            $table->string('highest_diploma')->nullable()->after('date_of_birth');
            $table->integer('diploma_year')->nullable()->after('highest_diploma');
            $table->json('languages')->nullable()->after('diploma_year'); // Stocké en JSON: ["francais", "anglais"]
        });

        // ── 2. TABLE REGISTRATIONS : Montant promo ──
        Schema::table('registrations', function (Blueprint $table) {
            // Montant personnalisé (promo). Si null = prix par défaut de la formation
            $table->decimal('custom_tuition', 15, 2)->nullable()->after('initial_payment');
        });
    }

    public function down()
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['residence', 'highest_diploma', 'diploma_year', 'languages']);
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('custom_tuition');
        });
    }
};