<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            ['name' => 'App settings view', 'slug' => 'app-settings.view', 'description' => 'View app settings'],
            ['name' => 'App settings write', 'slug' => 'app-settings.write', 'description' => 'Create/update/delete app settings'],
        ];

        foreach ($permissions as $permission) {
            $exists = DB::table('permissions')->where('slug', $permission['slug'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('permissions')->insert([
                'name' => $permission['name'],
                'slug' => $permission['slug'],
                'description' => $permission['description'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'slug')->all();
        $roles = DB::table('roles')->pluck('id', 'slug')->all();

        $rolePermissionMap = [
            'supervizor' => ['app-settings.view', 'app-settings.write'],
            'admin' => ['app-settings.view', 'app-settings.write'],
            'superadmin' => ['app-settings.view', 'app-settings.write'],
        ];

        $rows = [];
        foreach ($rolePermissionMap as $roleSlug => $permissionSlugs) {
            $roleId = $roles[$roleSlug] ?? null;
            if (!$roleId) {
                continue;
            }

            foreach ($permissionSlugs as $permissionSlug) {
                $permissionId = $permissionIds[$permissionSlug] ?? null;
                if (!$permissionId) {
                    continue;
                }

                $exists = DB::table('permission_role')
                    ->where('permission_id', $permissionId)
                    ->where('role_id', $roleId)
                    ->exists();

                if (!$exists) {
                    $rows[] = [
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ];
                }
            }
        }

        if (!empty($rows)) {
            DB::table('permission_role')->insert($rows);
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', ['app-settings.view', 'app-settings.write'])
            ->pluck('id')
            ->all();

        if (!empty($permissionIds)) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }
};
