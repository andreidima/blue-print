<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        $now = now();
        $permissions = [
            ['name' => 'Clienti view', 'slug' => 'clienti.view', 'description' => 'View clients'],
            ['name' => 'Clienti write', 'slug' => 'clienti.write', 'description' => 'Create/update/delete clients'],
            ['name' => 'Produse view', 'slug' => 'produse.view', 'description' => 'View products'],
            ['name' => 'Produse write', 'slug' => 'produse.write', 'description' => 'Create/update/delete products'],
            ['name' => 'Comenzi view', 'slug' => 'comenzi.view', 'description' => 'View orders'],
            ['name' => 'Comenzi write', 'slug' => 'comenzi.write', 'description' => 'Create/update/delete orders'],
            ['name' => 'Comenzi produse write', 'slug' => 'comenzi.produse.write', 'description' => 'Manage order products'],
            ['name' => 'Comenzi atasamente write', 'slug' => 'comenzi.atasamente.write', 'description' => 'Manage order attachments'],
            ['name' => 'Comenzi mockupuri write', 'slug' => 'comenzi.mockupuri.write', 'description' => 'Manage order mockups'],
            ['name' => 'Comenzi plati write', 'slug' => 'comenzi.plati.write', 'description' => 'Manage order payments'],
            ['name' => 'Comenzi etape write', 'slug' => 'comenzi.etape.write', 'description' => 'Manage order assignments'],
            ['name' => 'Comenzi sms send', 'slug' => 'comenzi.sms.send', 'description' => 'Send order SMS'],
            ['name' => 'Comenzi email send', 'slug' => 'comenzi.email.send', 'description' => 'Send order emails'],
            ['name' => 'Facturi view', 'slug' => 'facturi.view', 'description' => 'View/download invoices'],
            ['name' => 'Facturi write', 'slug' => 'facturi.write', 'description' => 'Upload/delete invoices'],
            ['name' => 'Facturi email send', 'slug' => 'facturi.email.send', 'description' => 'Send invoice emails'],
            ['name' => 'Sms templates view', 'slug' => 'sms-templates.view', 'description' => 'View SMS templates'],
            ['name' => 'Sms templates write', 'slug' => 'sms-templates.write', 'description' => 'Create/update/delete SMS templates'],
            ['name' => 'Users view', 'slug' => 'users.view', 'description' => 'View users'],
            ['name' => 'Users write', 'slug' => 'users.write', 'description' => 'Create/update/delete users'],
        ];

        $permissions = array_map(function (array $permission) use ($now) {
            $permission['created_at'] = $now;
            $permission['updated_at'] = $now;
            return $permission;
        }, $permissions);

        DB::table('permissions')->insert($permissions);

        $roles = DB::table('roles')->pluck('id', 'slug')->all();
        if (!isset($roles['financiar'])) {
            DB::table('roles')->insert([
                'name' => 'Financiar',
                'slug' => 'financiar',
                'color' => '#0dcaf0',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $roles = DB::table('roles')->pluck('id', 'slug')->all();
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'slug')->all();

        $commonViewPermissions = [
            'clienti.view',
            'produse.view',
            'comenzi.view',
            'sms-templates.view',
        ];
        $facturiViewPermissions = [
            'facturi.view',
        ];
        $usersViewPermissions = [
            'users.view',
        ];

        $commonWritePermissions = [
            'clienti.write',
            'produse.write',
            'comenzi.write',
            'comenzi.produse.write',
            'comenzi.atasamente.write',
            'comenzi.mockupuri.write',
            'comenzi.plati.write',
            'comenzi.etape.write',
            'comenzi.sms.send',
            'comenzi.email.send',
            'sms-templates.write',
        ];
        $facturiWritePermissions = [
            'facturi.write',
            'facturi.email.send',
        ];
        $usersWritePermissions = [
            'users.write',
        ];

        $rolePermissionMap = [
            'operator-front-office' => array_merge($commonViewPermissions, $commonWritePermissions),
            'grafician' => array_merge($commonViewPermissions, $commonWritePermissions),
            'operator-tipografie' => array_merge($commonViewPermissions, $commonWritePermissions),
            'supervizor' => array_merge(
                $commonViewPermissions,
                $facturiViewPermissions,
                $usersViewPermissions,
                $commonWritePermissions,
                $facturiWritePermissions,
                $usersWritePermissions
            ),
            'superadmin' => array_keys($permissionIds),
            'financiar' => array_merge($commonViewPermissions, $facturiViewPermissions),
        ];

        $rolePermissionRows = [];
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
                $rolePermissionRows[] = [
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ];
            }
        }

        if (!empty($rolePermissionRows)) {
            DB::table('permission_role')->insert($rolePermissionRows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');

        DB::table('roles')->where('slug', 'financiar')->delete();
    }
};
