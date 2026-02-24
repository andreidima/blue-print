<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comanda_etapa_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comanda_id')->constrained('comenzi')->cascadeOnDelete();
            $table->foreignId('etapa_id')->nullable()->constrained('etape')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->string('status_before', 32)->nullable();
            $table->string('status_after', 32)->nullable();
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->index(['comanda_id', 'etapa_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comanda_etapa_histories');
    }
};
