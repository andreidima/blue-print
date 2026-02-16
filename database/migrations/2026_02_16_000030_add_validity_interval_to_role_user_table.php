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
        Schema::table('role_user', function (Blueprint $table) {
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->index('starts_at', 'role_user_starts_at_idx');
            $table->index('ends_at', 'role_user_ends_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            $table->dropIndex('role_user_starts_at_idx');
            $table->dropIndex('role_user_ends_at_idx');
            $table->dropColumn(['starts_at', 'ends_at']);
        });
    }
};

