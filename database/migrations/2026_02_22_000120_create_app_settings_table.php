<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('label', 150);
            $table->string('key', 150)->unique();
            $table->text('value')->nullable();
            $table->string('type', 30)->default('text');
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('app_settings')->insert([
            'label' => 'Link review Google',
            'key' => 'google_review_url',
            'value' => 'https://search.google.com/local/writereview?placeid=ChIJUX9uSpHpNEcR7b7ZPJi1C4E',
            'type' => 'url',
            'description' => 'Link extern pentru formularul de review Google.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
