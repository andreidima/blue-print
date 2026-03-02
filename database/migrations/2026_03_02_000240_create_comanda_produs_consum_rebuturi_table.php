<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comanda_produs_consum_rebuturi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comanda_produs_consum_id')->constrained('comanda_produs_consumuri')->cascadeOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('nomenclator_materiale')->nullOnDelete();
            $table->string('material_denumire', 150)->nullable();
            $table->decimal('cantitate', 12, 4);
            $table->string('unitate_masura', 30);
            $table->foreignId('echipament_id')->nullable()->constrained('nomenclator_echipamente')->nullOnDelete();
            $table->string('echipament_denumire', 150)->nullable();
            $table->text('observatii')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comanda_produs_consum_rebuturi');
    }
};
