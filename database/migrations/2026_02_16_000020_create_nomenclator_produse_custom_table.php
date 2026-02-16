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
        Schema::create('nomenclator_produse_custom', function (Blueprint $table) {
            $table->id();
            $table->string('denumire', 150);
            $table->string('lookup_key', 191)->unique();
            $table->string('canonical_key', 191)->index();
            $table->foreignId('canonical_id')
                ->nullable()
                ->constrained('nomenclator_produse_custom')
                ->nullOnDelete();
            $table->boolean('is_canonical')->default(true)->index();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['is_canonical', 'denumire']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomenclator_produse_custom');
    }
};

