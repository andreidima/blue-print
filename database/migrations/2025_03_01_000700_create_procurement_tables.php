<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('procurement_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained('procurement_suppliers')
                ->nullOnDelete();
            $table->string('po_number')->unique();
            $table->enum('status', ['draft', 'pending', 'sent', 'partial', 'received', 'cancelled'])->default('draft');
            $table->date('expected_at')->nullable();
            $table->decimal('total_value', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('procurement_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')
                ->constrained('procurement_purchase_orders')
                ->cascadeOnDelete();
            $table->foreignId('produs_id')
                ->nullable()
                ->constrained('produse')
                ->nullOnDelete();
            $table->string('description')->nullable();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->decimal('received_quantity', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_purchase_order_items');
        Schema::dropIfExists('procurement_purchase_orders');
        Schema::dropIfExists('procurement_suppliers');
    }
};
