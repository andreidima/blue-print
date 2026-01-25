<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etape', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('label', 150);
            $table->timestamps();
        });

        $now = now();

        DB::table('etape')->insert([
            [
                'slug' => 'preluare_comanda',
                'label' => 'Preluare comanda',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'concept_procesare_grafica',
                'label' => 'Concept/procesare grafica',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'executie',
                'label' => 'Executie',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'impachetare',
                'label' => 'Impachetare',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'expediere',
                'label' => 'Expediere',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::create('comanda_etapa_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comanda_id')->constrained('comenzi')->cascadeOnDelete();
            $table->foreignId('etapa_id')->constrained('etape')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['comanda_id', 'etapa_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comanda_etapa_user');
        Schema::dropIfExists('etape');
    }
};
