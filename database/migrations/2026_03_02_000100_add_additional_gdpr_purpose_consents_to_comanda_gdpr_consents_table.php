<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comanda_gdpr_consents', function (Blueprint $table) {
            if (!Schema::hasColumn('comanda_gdpr_consents', 'consent_research_statistics')) {
                $table->boolean('consent_research_statistics')
                    ->default(false)
                    ->after('consent_media_marketing');
            }

            if (!Schema::hasColumn('comanda_gdpr_consents', 'consent_online_communications')) {
                $table->boolean('consent_online_communications')
                    ->default(false)
                    ->after('consent_research_statistics');
            }
        });
    }

    public function down(): void
    {
        Schema::table('comanda_gdpr_consents', function (Blueprint $table) {
            if (Schema::hasColumn('comanda_gdpr_consents', 'consent_online_communications')) {
                $table->dropColumn('consent_online_communications');
            }

            if (Schema::hasColumn('comanda_gdpr_consents', 'consent_research_statistics')) {
                $table->dropColumn('consent_research_statistics');
            }
        });
    }
};
