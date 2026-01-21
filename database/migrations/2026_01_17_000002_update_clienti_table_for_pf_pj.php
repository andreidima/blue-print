<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('clienti')) {
            Schema::create('clienti', function (Blueprint $table) {
                $table->id();
                $table->string('type', 2)->default('pf');
                $table->string('nume', 100);
                $table->string('adresa', 255)->nullable();
                $table->string('telefon', 50)->nullable();
                $table->string('email', 150)->nullable();

                $table->string('cnp', 13)->nullable();
                $table->string('sex', 1)->nullable();

                $table->string('reg_com', 50)->nullable();
                $table->string('cui', 20)->nullable();
                $table->string('iban', 50)->nullable();
                $table->string('banca', 100)->nullable();
                $table->string('reprezentant', 150)->nullable();
                $table->string('reprezentant_functie', 150)->nullable();

                $table->timestamps();
            });

            return;
        }

        Schema::table('clienti', function (Blueprint $table) {
            if (Schema::hasColumn('clienti', 'prenume')) {
                $table->dropColumn('prenume');
            }

            if (!Schema::hasColumn('clienti', 'type')) {
                $table->string('type', 2)->default('pf')->after('id');
            }

            if (!Schema::hasColumn('clienti', 'cnp')) {
                $table->string('cnp', 13)->nullable()->after('email');
            }
            if (!Schema::hasColumn('clienti', 'sex')) {
                $table->string('sex', 1)->nullable()->after('cnp');
            }

            if (!Schema::hasColumn('clienti', 'reg_com')) {
                $table->string('reg_com', 50)->nullable()->after('sex');
            }
            if (!Schema::hasColumn('clienti', 'cui')) {
                $table->string('cui', 20)->nullable()->after('reg_com');
            }
            if (!Schema::hasColumn('clienti', 'iban')) {
                $table->string('iban', 50)->nullable()->after('cui');
            }
            if (!Schema::hasColumn('clienti', 'banca')) {
                $table->string('banca', 100)->nullable()->after('iban');
            }
            if (!Schema::hasColumn('clienti', 'reprezentant')) {
                $table->string('reprezentant', 150)->nullable()->after('banca');
            }
            if (!Schema::hasColumn('clienti', 'reprezentant_functie')) {
                $table->string('reprezentant_functie', 150)->nullable()->after('reprezentant');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('clienti')) {
            return;
        }

        Schema::table('clienti', function (Blueprint $table) {
            if (!Schema::hasColumn('clienti', 'prenume')) {
                $table->string('prenume', 100)->nullable()->after('nume');
            }

            $dropColumns = array_values(array_filter([
                Schema::hasColumn('clienti', 'type') ? 'type' : null,
                Schema::hasColumn('clienti', 'cnp') ? 'cnp' : null,
                Schema::hasColumn('clienti', 'sex') ? 'sex' : null,
                Schema::hasColumn('clienti', 'reg_com') ? 'reg_com' : null,
                Schema::hasColumn('clienti', 'cui') ? 'cui' : null,
                Schema::hasColumn('clienti', 'iban') ? 'iban' : null,
                Schema::hasColumn('clienti', 'banca') ? 'banca' : null,
                Schema::hasColumn('clienti', 'reprezentant') ? 'reprezentant' : null,
                Schema::hasColumn('clienti', 'reprezentant_functie') ? 'reprezentant_functie' : null,
            ]));

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
