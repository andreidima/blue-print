<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clienti') && !Schema::hasColumn('clienti', 'deleted_at')) {
            Schema::table('clienti', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('comenzi') && !Schema::hasColumn('comenzi', 'deleted_at')) {
            Schema::table('comenzi', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clienti') && Schema::hasColumn('clienti', 'deleted_at')) {
            Schema::table('clienti', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('comenzi') && Schema::hasColumn('comenzi', 'deleted_at')) {
            Schema::table('comenzi', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
