<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('color', 20)->default('#6c757d');
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        $now = now();

        DB::table('roles')->insert([
            [
                'name' => 'Operator front-office',
                'slug' => 'operator-front-office',
                'color' => '#0d6efd',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Grafician',
                'slug' => 'grafician',
                'color' => '#d63384',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Operator Tipografie',
                'slug' => 'operator-tipografie',
                'color' => '#198754',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Supervizor',
                'slug' => 'supervizor',
                'color' => '#fd7e14',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'SuperAdmin',
                'slug' => 'superadmin',
                'color' => '#6f42c1',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};

