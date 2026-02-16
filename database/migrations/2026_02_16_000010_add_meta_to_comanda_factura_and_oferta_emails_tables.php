<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comanda_factura_emails', function (Blueprint $table) {
            if (!Schema::hasColumn('comanda_factura_emails', 'meta')) {
                $table->json('meta')->nullable()->after('facturi');
            }
        });

        Schema::table('comanda_oferta_emails', function (Blueprint $table) {
            if (!Schema::hasColumn('comanda_oferta_emails', 'meta')) {
                $table->json('meta')->nullable()->after('privacy_notice_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('comanda_factura_emails', function (Blueprint $table) {
            if (Schema::hasColumn('comanda_factura_emails', 'meta')) {
                $table->dropColumn('meta');
            }
        });

        Schema::table('comanda_oferta_emails', function (Blueprint $table) {
            if (Schema::hasColumn('comanda_oferta_emails', 'meta')) {
                $table->dropColumn('meta');
            }
        });
    }
};
