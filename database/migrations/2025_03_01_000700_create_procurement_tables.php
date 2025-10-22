<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
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

        $userIdColumnType = null;

        if (Schema::hasTable('users')) {
            $connection = Schema::getConnection();
            $driverName = $connection->getDriverName();

            if ($driverName === 'mysql') {
                $column = collect(DB::select("show columns from `{$connection->getTablePrefix()}users` where Field = 'id'"))->first();
                if ($column && isset($column->Type)) {
                    $userIdColumnType = strtolower($column->Type);
                }
            } elseif ($driverName === 'sqlite') {
                $column = collect(DB::select("PRAGMA table_info('users')"))->firstWhere('name', 'id');
                if ($column && isset($column->type)) {
                    $userIdColumnType = strtolower($column->type);
                }
            }
        }

        $userIdColumnLength = null;

        if ($userIdColumnType && preg_match('/\((\d+)\)/', $userIdColumnType, $lengthMatches)) {
            $userIdColumnLength = (int) $lengthMatches[1];
        }

        $normalizedUserIdType = match (true) {
            $userIdColumnType && str_contains($userIdColumnType, 'bigint') => 'bigint',
            $userIdColumnType && str_contains($userIdColumnType, 'int') => 'integer',
            $userIdColumnType && str_contains($userIdColumnType, 'binary') => 'binary',
            $userIdColumnType && (
                str_contains($userIdColumnType, 'char') ||
                str_contains($userIdColumnType, 'text') ||
                str_contains($userIdColumnType, 'string') ||
                str_contains($userIdColumnType, 'uuid') ||
                str_contains($userIdColumnType, 'var')
            ) => 'string',
            default => null,
        };

        Schema::create('procurement_purchase_orders', function (Blueprint $table) use ($normalizedUserIdType, $userIdColumnLength) {
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
            match ($normalizedUserIdType) {
                'integer' => $table->unsignedInteger('received_by')->nullable(),
                'bigint' => $table->unsignedBigInteger('received_by')->nullable(),
                'string' => $table->string('received_by', $userIdColumnLength ?: 191)->nullable(),
                'binary' => $table->binary('received_by')->nullable(),
                default => $table->unsignedBigInteger('received_by')->nullable(),
            };
            $table->timestamps();
        });

        if (in_array($normalizedUserIdType, ['integer', 'bigint'], true)) {
            Schema::table('procurement_purchase_orders', function (Blueprint $table) {
                $table->foreign('received_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        $productIdColumnType = null;
        $productIdColumnLength = null;

        if (Schema::hasTable('produse')) {
            $connection = Schema::getConnection();
            $driverName = $connection->getDriverName();

            if ($driverName === 'mysql') {
                $column = collect(DB::select("show columns from `{$connection->getTablePrefix()}produse` where Field = 'id'"))->first();
                if ($column && isset($column->Type)) {
                    $productIdColumnType = strtolower($column->Type);
                }
            } elseif ($driverName === 'sqlite') {
                $column = collect(DB::select("PRAGMA table_info('produse')"))->firstWhere('name', 'id');
                if ($column && isset($column->type)) {
                    $productIdColumnType = strtolower($column->type);
                }
            }
        }

        if ($productIdColumnType && preg_match('/\((\d+)\)/', $productIdColumnType, $lengthMatches)) {
            $productIdColumnLength = (int) $lengthMatches[1];
        }

        $normalizedProductIdType = match (true) {
            $productIdColumnType && str_contains($productIdColumnType, 'bigint') => 'bigint',
            $productIdColumnType && str_contains($productIdColumnType, 'int') => 'integer',
            $productIdColumnType && str_contains($productIdColumnType, 'binary') => 'binary',
            $productIdColumnType && (
                str_contains($productIdColumnType, 'char') ||
                str_contains($productIdColumnType, 'text') ||
                str_contains($productIdColumnType, 'string') ||
                str_contains($productIdColumnType, 'uuid') ||
                str_contains($productIdColumnType, 'var')
            ) => 'string',
            default => null,
        };

        Schema::create('procurement_purchase_order_items', function (Blueprint $table) use ($normalizedProductIdType, $productIdColumnLength) {
            $table->id();
            $table->foreignId('purchase_order_id')
                ->constrained('procurement_purchase_orders')
                ->cascadeOnDelete();
            match ($normalizedProductIdType) {
                'integer' => $table->unsignedInteger('produs_id')->nullable(),
                'bigint' => $table->unsignedBigInteger('produs_id')->nullable(),
                'string' => $table->string('produs_id', $productIdColumnLength ?: 191)->nullable(),
                'binary' => $table->binary('produs_id')->nullable(),
                default => $table->unsignedBigInteger('produs_id')->nullable(),
            };
            $table->string('description')->nullable();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->decimal('received_quantity', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        if (in_array($normalizedProductIdType, ['integer', 'bigint'], true)) {
            Schema::table('procurement_purchase_order_items', function (Blueprint $table) {
                $table->foreign('produs_id')
                    ->references('id')
                    ->on('produse')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_purchase_order_items');
        Schema::dropIfExists('procurement_purchase_orders');
        Schema::dropIfExists('procurement_suppliers');
    }
};
