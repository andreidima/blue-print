<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nomenclator_produse_custom', function (Blueprint $table) {
            if (!Schema::hasColumn('nomenclator_produse_custom', 'pret')) {
                $table->decimal('pret', 10, 2)->nullable()->after('descriere');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nomenclator_produse_custom', function (Blueprint $table) {
            if (Schema::hasColumn('nomenclator_produse_custom', 'pret')) {
                $table->dropColumn('pret');
            }
        });
    }
};
