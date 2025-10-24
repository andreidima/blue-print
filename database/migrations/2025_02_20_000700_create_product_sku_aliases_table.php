<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_sku_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produs_id')->constrained('produse')->cascadeOnDelete();
            $table->string('sku', 100)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sku_aliases');
    }
};
