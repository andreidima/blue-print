<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('comenzi', function (Blueprint $table) {
            $table->date('data_solicitarii')
                ->nullable()
                ->index()
                ->after('status');
        });

        DB::table('comenzi')->update([
            'data_solicitarii' => DB::raw('DATE(created_at)'),
        ]);

        DB::statement('ALTER TABLE `comenzi` MODIFY `data_solicitarii` DATE NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comenzi', function (Blueprint $table) {
            $table->dropColumn('data_solicitarii');
        });
    }
};
