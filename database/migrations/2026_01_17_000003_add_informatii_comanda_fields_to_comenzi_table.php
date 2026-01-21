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
            $table->text('solicitare_client')->nullable()->after('necesita_tipar_exemplu');
            $table->unsignedInteger('cantitate')->nullable()->after('solicitare_client');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comenzi', function (Blueprint $table) {
            $table->dropColumn(['solicitare_client', 'cantitate']);
        });
    }
};

