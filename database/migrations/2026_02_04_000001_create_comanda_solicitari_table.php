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
        Schema::create('comanda_solicitari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comanda_id')->constrained('comenzi')->cascadeOnDelete();
            $table->text('solicitare_client')->nullable();
            $table->unsignedInteger('cantitate')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_label')->nullable();
            $table->timestamps();
        });

        Schema::table('comenzi', function (Blueprint $table) {
            $table->dropColumn(['solicitare_client', 'cantitate']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comenzi', function (Blueprint $table) {
            $table->text('solicitare_client')->nullable()->after('necesita_tipar_exemplu');
            $table->unsignedInteger('cantitate')->nullable()->after('solicitare_client');
        });

        Schema::dropIfExists('comanda_solicitari');
    }
};
