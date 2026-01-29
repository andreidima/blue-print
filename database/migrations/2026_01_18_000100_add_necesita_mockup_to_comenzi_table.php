<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comenzi', function (Blueprint $table) {
            $table->boolean('necesita_mockup')->default(false)->after('necesita_tipar_exemplu');
        });
    }

    public function down(): void
    {
        Schema::table('comenzi', function (Blueprint $table) {
            $table->dropColumn('necesita_mockup');
        });
    }
};
