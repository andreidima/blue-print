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
        Schema::table('produse', function (Blueprint $table) {
            if (! Schema::hasColumn('produse', 'sku')) {
                $table->string('sku')->nullable()->unique()->after('nume');
            }
        });

        Schema::table('miscari_stoc', function (Blueprint $table) {
            if (! Schema::hasColumn('miscari_stoc', 'wc_order_item_id')) {
                $table->unsignedBigInteger('wc_order_item_id')->nullable()->after('nr_comanda');
                $table->index('wc_order_item_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('miscari_stoc', function (Blueprint $table) {
            if (Schema::hasColumn('miscari_stoc', 'wc_order_item_id')) {
                $table->dropIndex('miscari_stoc_wc_order_item_id_index');
                $table->dropColumn('wc_order_item_id');
            }
        });

        Schema::table('produse', function (Blueprint $table) {
            if (Schema::hasColumn('produse', 'sku')) {
                $table->dropUnique(['sku']);
                $table->dropColumn('sku');
            }
        });
    }
};
