<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comenzi', function (Blueprint $table) {
            if (!Schema::hasColumn('comenzi', 'livrator')) {
                $table->string('livrator', 100)->nullable()->after('awb');
            }

            if (!Schema::hasColumn('comenzi', 'afiseaza_detalii')) {
                $table->boolean('afiseaza_detalii')->default(true)->after('livrator');
            }
        });
    }

    public function down(): void
    {
        Schema::table('comenzi', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('comenzi', 'afiseaza_detalii')) {
                $columns[] = 'afiseaza_detalii';
            }

            if (Schema::hasColumn('comenzi', 'livrator')) {
                $columns[] = 'livrator';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
