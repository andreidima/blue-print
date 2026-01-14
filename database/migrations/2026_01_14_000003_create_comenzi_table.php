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
        Schema::create('comenzi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clienti');
            $table->string('tip', 50)->index();
            $table->string('sursa', 50)->index();
            $table->string('status', 50)->index();
            $table->dateTime('timp_estimat_livrare')->index();
            $table->dateTime('finalizat_la')->nullable();
            $table->boolean('necesita_tipar_exemplu')->default(false);

            $table->foreignId('frontdesk_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supervizor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('grafician_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('executant_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('nota_frontdesk')->nullable();
            $table->text('nota_grafician')->nullable();
            $table->text('nota_executant')->nullable();

            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('total_platit', 10, 2)->default(0);
            $table->string('status_plata', 20)->default('neplatit')->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comenzi');
    }
};
