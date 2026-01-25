<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name', 150);
            $table->text('body');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        DB::table('sms_templates')->insert([
            [
                'key' => 'comanda_finalizata',
                'name' => 'Comanda finalizata',
                'body' => 'Buna {client}, comanda #{comanda_id} a fost finalizata. Total: {total} lei. Multumim!',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'intarziata',
                'name' => 'Intarziata',
                'body' => 'Buna {client}, comanda #{comanda_id} este intarziata. Estimare livrare: {livrare}.',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'personalizat',
                'name' => 'Personalizat',
                'body' => 'Buna {client},',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_templates');
    }
};
