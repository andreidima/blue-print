<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'role')) {
            return;
        }

        $roleIdsBySlug = DB::table('roles')->pluck('id', 'slug');

        $defaultRoleId = $roleIdsBySlug['operator-front-office'] ?? null;
        $supervizorRoleId = $roleIdsBySlug['supervizor'] ?? null;
        $superAdminRoleId = $roleIdsBySlug['superadmin'] ?? null;

        if (!$defaultRoleId || !$supervizorRoleId || !$superAdminRoleId) {
            throw new RuntimeException('Missing required default roles.');
        }

        $users = DB::table('users')->select('id', 'role')->get();

        foreach ($users as $user) {
            $role = strtolower(trim((string) ($user->role ?? '')));

            $targetRoleId = match ($role) {
                'superadmin' => $superAdminRoleId,
                'admin', 'supervizor' => $supervizorRoleId,
                'operator' => $defaultRoleId,
                'user', '' => $defaultRoleId,
                default => $defaultRoleId,
            };

            DB::table('role_user')->updateOrInsert([
                'role_id' => $targetRoleId,
                'user_id' => $user->id,
            ], []);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'role')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->default('User')->index();
        });

        $roleIdBySlug = DB::table('roles')->pluck('id', 'slug');
        $superAdminRoleId = $roleIdBySlug['superadmin'] ?? null;
        $supervizorRoleId = $roleIdBySlug['supervizor'] ?? null;

        if (!$superAdminRoleId || !$supervizorRoleId) {
            return;
        }

        $userIds = DB::table('users')->pluck('id');

        foreach ($userIds as $userId) {
            $hasSuperAdmin = DB::table('role_user')
                ->where('user_id', $userId)
                ->where('role_id', $superAdminRoleId)
                ->exists();

            if ($hasSuperAdmin) {
                DB::table('users')->where('id', $userId)->update(['role' => 'SuperAdmin']);
                continue;
            }

            $hasSupervizor = DB::table('role_user')
                ->where('user_id', $userId)
                ->where('role_id', $supervizorRoleId)
                ->exists();

            DB::table('users')->where('id', $userId)->update(['role' => $hasSupervizor ? 'Admin' : 'Operator']);
        }
    }
};
