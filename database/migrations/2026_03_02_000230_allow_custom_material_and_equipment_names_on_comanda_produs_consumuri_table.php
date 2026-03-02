<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comanda_produs_consumuri', function (Blueprint $table) {
            if (!Schema::hasColumn('comanda_produs_consumuri', 'material_denumire')) {
                $table->string('material_denumire', 150)->nullable()->after('material_id');
            }

            if (!Schema::hasColumn('comanda_produs_consumuri', 'echipament_denumire')) {
                $table->string('echipament_denumire', 150)->nullable()->after('echipament_id');
            }
        });

        DB::statement('ALTER TABLE comanda_produs_consumuri MODIFY material_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE comanda_produs_consumuri MODIFY material_id BIGINT UNSIGNED NOT NULL');

        Schema::table('comanda_produs_consumuri', function (Blueprint $table) {
            if (Schema::hasColumn('comanda_produs_consumuri', 'echipament_denumire')) {
                $table->dropColumn('echipament_denumire');
            }

            if (Schema::hasColumn('comanda_produs_consumuri', 'material_denumire')) {
                $table->dropColumn('material_denumire');
            }
        });
    }
};
