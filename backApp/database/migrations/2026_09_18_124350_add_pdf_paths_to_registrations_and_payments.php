<?php
// database/migrations/xxxx_add_pdf_paths_to_registrations_and_payments.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('pdf_path')->nullable()->after('qr_signature');
            $table->timestamp('pdf_generated_at')->nullable()->after('pdf_path');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_path')->nullable()->after('status');
            $table->timestamp('receipt_generated_at')->nullable()->after('receipt_path');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['pdf_path', 'pdf_generated_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['receipt_path', 'receipt_generated_at']);
        });
    }
};