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
        Schema::create('comanda_note', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comanda_id')->constrained('comenzi')->cascadeOnDelete();
            $table->string('role', 32);
            $table->text('nota')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_label')->nullable();
            $table->timestamps();

            $table->index(['comanda_id', 'role']);
        });

        Schema::table('comenzi', function (Blueprint $table) {
            $table->dropColumn(['nota_frontdesk', 'nota_grafician', 'nota_executant']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comenzi', function (Blueprint $table) {
            $table->text('nota_frontdesk')->nullable()->after('executant_user_id');
            $table->text('nota_grafician')->nullable()->after('nota_frontdesk');
            $table->text('nota_executant')->nullable()->after('nota_grafician');
        });

        Schema::dropIfExists('comanda_note');
    }
};
