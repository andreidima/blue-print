<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comanda_produs_consumuri', function (Blueprint $table) {
            if (Schema::hasColumn('comanda_produs_consumuri', 'cantitate_per_unitate')) {
                $table->dropColumn('cantitate_per_unitate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('comanda_produs_consumuri', function (Blueprint $table) {
            if (!Schema::hasColumn('comanda_produs_consumuri', 'cantitate_per_unitate')) {
                $table->decimal('cantitate_per_unitate', 12, 4)->default(0)->after('material_denumire');
            }
        });
    }
};
