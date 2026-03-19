<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clienti')->cascadeOnDelete();
            $table->string('email', 150);
            $table->string('type', 30)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['client_id', 'email']);
            $table->index('email');
            $table->index(['client_id', 'sort_order']);
        });

        DB::table('clienti')
            ->select(['id', 'email', 'created_at', 'updated_at'])
            ->whereNotNull('email')
            ->orderBy('id')
            ->chunkById(200, function ($clients) {
                $rows = [];

                foreach ($clients as $client) {
                    $email = strtolower(trim((string) $client->email));
                    if ($email === '') {
                        continue;
                    }

                    $rows[] = [
                        'client_id' => $client->id,
                        'email' => $email,
                        'type' => null,
                        'sort_order' => 0,
                        'created_at' => $client->created_at,
                        'updated_at' => $client->updated_at,
                    ];
                }

                if ($rows !== []) {
                    DB::table('client_emails')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_emails');
    }
};
