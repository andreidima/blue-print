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
        Schema::create('wc_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wc_order_id')->constrained('wc_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('woocommerce_item_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variation_id')->nullable();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price', 15, 4)->default(0);
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('subtotal_tax', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->decimal('total_tax', 15, 4)->default(0);
            $table->json('taxes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wc_order_items');
    }
};
