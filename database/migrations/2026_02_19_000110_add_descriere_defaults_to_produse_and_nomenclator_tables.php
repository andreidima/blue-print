<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produse', function (Blueprint $table) {
            if (!Schema::hasColumn('produse', 'descriere')) {
                $table->text('descriere')->nullable()->after('denumire');
            }
        });

        Schema::table('nomenclator_produse_custom', function (Blueprint $table) {
            if (!Schema::hasColumn('nomenclator_produse_custom', 'descriere')) {
                $table->text('descriere')->nullable()->after('denumire');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nomenclator_produse_custom', function (Blueprint $table) {
            if (Schema::hasColumn('nomenclator_produse_custom', 'descriere')) {
                $table->dropColumn('descriere');
            }
        });

        Schema::table('produse', function (Blueprint $table) {
            if (Schema::hasColumn('produse', 'descriere')) {
                $table->dropColumn('descriere');
            }
        });
    }
};
