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
        Schema::create('wc_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('woocommerce_id')->unique();
            $table->foreignId('wc_customer_id')->nullable()->constrained('wc_customers')->nullOnDelete();
            $table->string('status')->index();
            $table->string('currency', 10)->nullable();
            $table->decimal('total', 15, 4)->default(0);
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('total_tax', 15, 4)->default(0);
            $table->decimal('shipping_total', 15, 4)->default(0);
            $table->decimal('discount_total', 15, 4)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_method_title')->nullable();
            $table->timestamp('date_created')->nullable();
            $table->timestamp('date_modified')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wc_orders');
    }
};
