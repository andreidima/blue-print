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
        Schema::table('comenzi', function (Blueprint $table) {
            $table->text('adresa_facturare')->nullable()->after('cantitate');
            $table->text('adresa_livrare')->nullable()->after('adresa_facturare');
            $table->string('awb', 50)->nullable()->after('adresa_livrare');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comenzi', function (Blueprint $table) {
            $table->dropColumn(['adresa_facturare', 'adresa_livrare', 'awb']);
        });
    }
};
