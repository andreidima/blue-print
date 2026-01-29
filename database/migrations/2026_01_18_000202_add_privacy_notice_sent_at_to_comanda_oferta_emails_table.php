<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comanda_oferta_emails', function (Blueprint $table) {
            $table->timestamp('privacy_notice_sent_at')->nullable()->after('pdf_name');
        });
    }

    public function down(): void
    {
        Schema::table('comanda_oferta_emails', function (Blueprint $table) {
            $table->dropColumn('privacy_notice_sent_at');
        });
    }
};
