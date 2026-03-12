<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ROLE_SLUG = 'financiar';
    private const PERMISSION_SLUG = 'facturi.write';

    public function up(): void
    {
        $roleId = DB::table('roles')->where('slug', self::ROLE_SLUG)->value('id');
        $permissionId = DB::table('permissions')->where('slug', self::PERMISSION_SLUG)->value('id');

        if (!$roleId || !$permissionId) {
            return;
        }

        $exists = DB::table('permission_role')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('permission_role')->insert([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ]);
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('slug', self::ROLE_SLUG)->value('id');
        $permissionId = DB::table('permissions')->where('slug', self::PERMISSION_SLUG)->value('id');

        if (!$roleId || !$permissionId) {
            return;
        }

        DB::table('permission_role')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->delete();
    }
};
