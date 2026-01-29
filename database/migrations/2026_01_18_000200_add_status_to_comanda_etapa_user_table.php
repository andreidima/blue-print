<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comanda_etapa_user', function (Blueprint $table) {
            $table->string('status', 20)->default('approved')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('comanda_etapa_user', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
