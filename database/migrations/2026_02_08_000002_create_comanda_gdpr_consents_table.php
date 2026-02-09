<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comanda_gdpr_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comanda_id')->constrained('comenzi')->cascadeOnDelete();
            $table->string('method', 20)->default('signature');
            $table->boolean('consent_processing')->default(false);
            $table->boolean('consent_marketing')->default(false);
            $table->string('signature_path')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->json('client_snapshot')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comanda_gdpr_consents');
    }
};
