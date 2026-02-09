<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            ['name' => 'Email templates view', 'slug' => 'email-templates.view', 'description' => 'View email templates'],
            ['name' => 'Email templates write', 'slug' => 'email-templates.write', 'description' => 'Create/update/delete email templates'],
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

        $viewPermissions = [
            'email-templates.view',
        ];

        $writePermissions = [
            'email-templates.write',
        ];

        $rolePermissionMap = [
            'operator-front-office' => array_merge($viewPermissions, $writePermissions),
            'grafician' => array_merge($viewPermissions, $writePermissions),
            'operator-tipografie' => array_merge($viewPermissions, $writePermissions),
            'supervizor' => array_merge($viewPermissions, $writePermissions),
            'superadmin' => array_merge($viewPermissions, $writePermissions),
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
            ->whereIn('slug', ['email-templates.view', 'email-templates.write'])
            ->pluck('id')
            ->all();

        if (!empty($permissionIds)) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }
};
