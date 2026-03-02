<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comanda_produs_consumuri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comanda_produs_id')->constrained('comanda_produse')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('nomenclator_materiale')->restrictOnDelete();
            $table->decimal('cantitate_per_unitate', 12, 4);
            $table->string('unitate_masura', 30);
            $table->decimal('cantitate_totala', 12, 4)->default(0);
            $table->foreignId('echipament_id')->nullable()->constrained('nomenclator_echipamente')->nullOnDelete();
            $table->text('observatii')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comanda_produs_consumuri');
    }
};
