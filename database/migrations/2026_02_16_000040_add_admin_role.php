<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        if (!$adminRoleId) {
            DB::table('roles')->insert([
                'name' => 'Admin',
                'slug' => 'admin',
                'color' => '#0b7285',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        }

        if (!$adminRoleId) {
            return;
        }

        $rolesBySlug = DB::table('roles')->pluck('id', 'slug')->all();
        $superAdminRoleId = $rolesBySlug['superadmin'] ?? null;
        $supervizorRoleId = $rolesBySlug['supervizor'] ?? null;

        $permissionIds = [];
        if ($superAdminRoleId) {
            $permissionIds = DB::table('permission_role')
                ->where('role_id', $superAdminRoleId)
                ->pluck('permission_id')
                ->all();
        }

        if (empty($permissionIds) && $supervizorRoleId) {
            $permissionIds = DB::table('permission_role')
                ->where('role_id', $supervizorRoleId)
                ->pluck('permission_id')
                ->all();
        }

        if (empty($permissionIds)) {
            $permissionIds = DB::table('permissions')->pluck('id')->all();
        }

        $rows = collect($permissionIds)
            ->map(fn ($permissionId) => (int) $permissionId)
            ->filter()
            ->unique()
            ->map(function (int $permissionId) use ($adminRoleId) {
                return [
                    'permission_id' => $permissionId,
                    'role_id' => (int) $adminRoleId,
                ];
            })
            ->values()
            ->all();

        foreach ($rows as $row) {
            $exists = DB::table('permission_role')
                ->where('permission_id', $row['permission_id'])
                ->where('role_id', $row['role_id'])
                ->exists();

            if (!$exists) {
                DB::table('permission_role')->insert($row);
            }
        }
    }

    public function down(): void
    {
        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        if (!$adminRoleId) {
            return;
        }

        DB::table('permission_role')->where('role_id', $adminRoleId)->delete();
        DB::table('role_user')->where('role_id', $adminRoleId)->delete();
        DB::table('roles')->where('id', $adminRoleId)->delete();
    }
};
