<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nomenclator_materiale', function (Blueprint $table) {
            $table->id();
            $table->string('denumire', 150);
            $table->string('unitate_masura', 30);
            $table->text('descriere')->nullable();
            $table->boolean('activ')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomenclator_materiale');
    }
};
