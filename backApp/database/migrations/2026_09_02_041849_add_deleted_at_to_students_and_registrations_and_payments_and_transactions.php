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
        Schema::table('students_and_registrations_and_payments_and_transactions', function (Blueprint $table) {
            Schema::table('students', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->softDeletes();
        });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students_and_registrations_and_payments_and_transactions', function (Blueprint $table) {
            Schema::table('students', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        });
    }
};
