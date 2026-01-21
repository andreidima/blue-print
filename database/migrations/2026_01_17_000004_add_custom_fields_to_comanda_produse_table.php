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
        Schema::table('comanda_produse', function (Blueprint $table) {
            $table->string('custom_denumire')->nullable()->after('produs_id');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->rebuildForSqlite(true, true);
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE comanda_produse ALTER COLUMN produs_id DROP NOT NULL');
            return;
        }

        DB::statement('ALTER TABLE comanda_produse MODIFY produs_id BIGINT UNSIGNED NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->rebuildForSqlite(false, false);
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE comanda_produse ALTER COLUMN produs_id SET NOT NULL');
        } else {
            DB::statement('ALTER TABLE comanda_produse MODIFY produs_id BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('comanda_produse', function (Blueprint $table) {
            $table->dropColumn('custom_denumire');
        });
    }

    private function rebuildForSqlite(bool $allowNullable, bool $includeCustom): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::rename('comanda_produse', 'comanda_produse_old');

        Schema::create('comanda_produse', function (Blueprint $table) use ($allowNullable, $includeCustom) {
            $table->id();
            $table->foreignId('comanda_id')->constrained('comenzi')->cascadeOnDelete();
            $produsColumn = $table->foreignId('produs_id')->constrained('produse');
            if ($allowNullable) {
                $produsColumn->nullable();
            }
            if ($includeCustom) {
                $table->string('custom_denumire')->nullable();
            }
            $table->unsignedInteger('cantitate');
            $table->decimal('pret_unitar', 10, 2);
            $table->decimal('total_linie', 10, 2)->default(0);
            $table->timestamps();
        });

        if ($includeCustom) {
            if ($allowNullable) {
                DB::statement('INSERT INTO comanda_produse (id, comanda_id, produs_id, custom_denumire, cantitate, pret_unitar, total_linie, created_at, updated_at) SELECT id, comanda_id, produs_id, NULL, cantitate, pret_unitar, total_linie, created_at, updated_at FROM comanda_produse_old');
            } else {
                DB::statement('INSERT INTO comanda_produse (id, comanda_id, produs_id, custom_denumire, cantitate, pret_unitar, total_linie, created_at, updated_at) SELECT id, comanda_id, produs_id, custom_denumire, cantitate, pret_unitar, total_linie, created_at, updated_at FROM comanda_produse_old');
            }
        } else {
            DB::statement('INSERT INTO comanda_produse (id, comanda_id, produs_id, cantitate, pret_unitar, total_linie, created_at, updated_at) SELECT id, comanda_id, produs_id, cantitate, pret_unitar, total_linie, created_at, updated_at FROM comanda_produse_old');
        }

        Schema::drop('comanda_produse_old');

        Schema::enableForeignKeyConstraints();
    }
};
