<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comanda_gdpr_consents', function (Blueprint $table) {
            if (!Schema::hasColumn('comanda_gdpr_consents', 'consent_media_marketing')) {
                $table->boolean('consent_media_marketing')
                    ->default(false)
                    ->after('consent_marketing');
            }
        });
    }

    public function down(): void
    {
        Schema::table('comanda_gdpr_consents', function (Blueprint $table) {
            if (Schema::hasColumn('comanda_gdpr_consents', 'consent_media_marketing')) {
                $table->dropColumn('consent_media_marketing');
            }
        });
    }
};
