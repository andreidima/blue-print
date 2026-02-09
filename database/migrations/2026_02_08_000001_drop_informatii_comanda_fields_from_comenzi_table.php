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
        if (Schema::hasColumn('comenzi', 'solicitare_client') || Schema::hasColumn('comenzi', 'cantitate')) {
            Schema::table('comenzi', function (Blueprint $table) {
                if (Schema::hasColumn('comenzi', 'solicitare_client')) {
                    $table->dropColumn('solicitare_client');
                }
                if (Schema::hasColumn('comenzi', 'cantitate')) {
                    $table->dropColumn('cantitate');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comenzi', function (Blueprint $table) {
            if (!Schema::hasColumn('comenzi', 'solicitare_client')) {
                $table->text('solicitare_client')->nullable()->after('necesita_tipar_exemplu');
            }
            if (!Schema::hasColumn('comenzi', 'cantitate')) {
                $table->unsignedInteger('cantitate')->nullable()->after('solicitare_client');
            }
        });
    }
};
