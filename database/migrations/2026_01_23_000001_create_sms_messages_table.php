<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comanda_id')->constrained('comenzi')->cascadeOnDelete();
            $table->foreignId('sms_template_id')->nullable()->constrained('sms_templates')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient', 100);
            $table->text('message');
            $table->text('message_sent')->nullable();
            $table->string('status', 20);
            $table->string('provider', 30)->default('smslink');
            $table->string('gateway_level', 30)->nullable();
            $table->string('gateway_code', 30)->nullable();
            $table->string('gateway_message', 255)->nullable();
            $table->string('gateway_message_id', 100)->nullable();
            $table->text('gateway_raw')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['comanda_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
