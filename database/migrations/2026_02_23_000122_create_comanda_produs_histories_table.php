<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comanda_produs_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comanda_id')->constrained('comenzi')->cascadeOnDelete();
            $table->foreignId('comanda_produs_id')->nullable()->constrained('comanda_produse')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->index(['comanda_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comanda_produs_histories');
    }
};
