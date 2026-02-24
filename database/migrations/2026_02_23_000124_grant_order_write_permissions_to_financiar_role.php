<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ROLE_SLUG = 'financiar';
    private const PERMISSION_SLUGS = [
        'comenzi.write',
        'comenzi.produse.write',
        'comenzi.atasamente.write',
        'comenzi.mockupuri.write',
        'comenzi.plati.write',
        'comenzi.etape.write',
        'comenzi.sms.send',
        'comenzi.email.send',
    ];

    public function up(): void
    {
        $roleId = DB::table('roles')->where('slug', self::ROLE_SLUG)->value('id');
        if (!$roleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', self::PERMISSION_SLUGS)
            ->pluck('id', 'slug');

        $now = now();
        foreach (self::PERMISSION_SLUGS as $slug) {
            $permissionId = $permissionIds[$slug] ?? null;
            if (!$permissionId) {
                continue;
            }

            $exists = DB::table('permission_role')
                ->where('permission_id', $permissionId)
                ->where('role_id', $roleId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('permission_role')->insert([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('slug', self::ROLE_SLUG)->value('id');
        if (!$roleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', self::PERMISSION_SLUGS)
            ->pluck('id')
            ->all();

        if ($permissionIds === []) {
            return;
        }

        DB::table('permission_role')
            ->where('role_id', $roleId)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }
};
