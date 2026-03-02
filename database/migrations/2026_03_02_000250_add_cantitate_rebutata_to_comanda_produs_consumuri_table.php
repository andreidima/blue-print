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
            $table->decimal('cantitate_rebutata', 12, 4)->default(0)->after('cantitate_totala');
        });

        $sums = DB::table('comanda_produs_consum_rebuturi')
            ->select('comanda_produs_consum_id', DB::raw('SUM(cantitate) as total_rebut'))
            ->groupBy('comanda_produs_consum_id')
            ->get();

        foreach ($sums as $row) {
            DB::table('comanda_produs_consumuri')
                ->where('id', $row->comanda_produs_consum_id)
                ->update([
                    'cantitate_rebutata' => round((float) $row->total_rebut, 4),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('comanda_produs_consumuri', function (Blueprint $table) {
            $table->dropColumn('cantitate_rebutata');
        });
    }
};
