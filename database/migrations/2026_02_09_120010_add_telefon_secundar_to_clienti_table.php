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
        Schema::table('clienti', function (Blueprint $table) {
            $table->string('telefon_secundar', 50)->nullable()->after('telefon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clienti', function (Blueprint $table) {
            $table->dropColumn('telefon_secundar');
        });
    }
};
