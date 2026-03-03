<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->rebuildComandaProduseTable();
            $this->rebuildComandaSolicitariTable();

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE comanda_produse ALTER COLUMN cantitate TYPE NUMERIC(12,4)');
            DB::statement('ALTER TABLE comanda_solicitari ALTER COLUMN cantitate TYPE NUMERIC(12,4)');

            return;
        }

        DB::statement('ALTER TABLE comanda_produse MODIFY cantitate DECIMAL(12,4) NOT NULL');
        DB::statement('ALTER TABLE comanda_solicitari MODIFY cantitate DECIMAL(12,4) NULL');
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->rebuildComandaProduseTable(true);
            $this->rebuildComandaSolicitariTable(true);

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE comanda_produse ALTER COLUMN cantitate TYPE INTEGER USING ROUND(cantitate)');
            DB::statement('ALTER TABLE comanda_solicitari ALTER COLUMN cantitate TYPE INTEGER USING ROUND(cantitate)');

            return;
        }

        DB::statement('ALTER TABLE comanda_produse MODIFY cantitate INT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE comanda_solicitari MODIFY cantitate INT UNSIGNED NULL');
    }

    private function rebuildComandaProduseTable(bool $useInteger = false): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::rename('comanda_produse', 'comanda_produse_old');

        Schema::create('comanda_produse', function (Blueprint $table) use ($useInteger) {
            $table->id();
            $table->foreignId('comanda_id')->constrained('comenzi')->cascadeOnDelete();
            $table->foreignId('produs_id')->nullable()->constrained('produse');
            $table->string('custom_denumire')->nullable();
            $table->text('descriere')->nullable();
            if ($useInteger) {
                $table->unsignedInteger('cantitate');
            } else {
                $table->decimal('cantitate', 12, 4);
            }
            $table->decimal('pret_unitar', 10, 2);
            $table->decimal('total_linie', 10, 2)->default(0);
            $table->timestamps();
        });

        DB::statement('
            INSERT INTO comanda_produse (
                id, comanda_id, produs_id, custom_denumire, descriere, cantitate, pret_unitar, total_linie, created_at, updated_at
            )
            SELECT
                id, comanda_id, produs_id, custom_denumire, descriere, cantitate, pret_unitar, total_linie, created_at, updated_at
            FROM comanda_produse_old
        ');

        Schema::drop('comanda_produse_old');
        Schema::enableForeignKeyConstraints();
    }

    private function rebuildComandaSolicitariTable(bool $useInteger = false): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::rename('comanda_solicitari', 'comanda_solicitari_old');

        Schema::create('comanda_solicitari', function (Blueprint $table) use ($useInteger) {
            $table->id();
            $table->foreignId('comanda_id')->constrained('comenzi')->cascadeOnDelete();
            $table->text('solicitare_client')->nullable();
            if ($useInteger) {
                $table->unsignedInteger('cantitate')->nullable();
            } else {
                $table->decimal('cantitate', 12, 4)->nullable();
            }
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_label')->nullable();
            $table->timestamps();
        });

        DB::statement('
            INSERT INTO comanda_solicitari (
                id, comanda_id, solicitare_client, cantitate, created_by, created_by_label, created_at, updated_at
            )
            SELECT
                id, comanda_id, solicitare_client, cantitate, created_by, created_by_label, created_at, updated_at
            FROM comanda_solicitari_old
        ');

        Schema::drop('comanda_solicitari_old');
        Schema::enableForeignKeyConstraints();
    }
};
