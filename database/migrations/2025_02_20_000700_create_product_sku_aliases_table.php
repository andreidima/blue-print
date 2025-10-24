<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        Schema::create('product_sku_aliases', function (Blueprint $table) use ($normalizedProductIdType, $productIdColumnLength) {
            $table->id();

            match ($normalizedProductIdType) {
                'integer' => $table->unsignedInteger('produs_id'),
                'bigint' => $table->unsignedBigInteger('produs_id'),
                'string' => $table->string('produs_id', $productIdColumnLength ?: 191),
                'binary' => $table->binary('produs_id'),
                default => $table->unsignedBigInteger('produs_id'),
            };

            $table->string('sku', 100)->unique();
            $table->timestamps();
        });

        if (in_array($normalizedProductIdType, ['integer', 'bigint'], true)) {
            Schema::table('product_sku_aliases', function (Blueprint $table) {
                $table->foreign('produs_id')
                    ->references('id')
                    ->on('produse')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sku_aliases');
    }
};
