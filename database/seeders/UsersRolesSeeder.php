<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersRolesSeeder extends Seeder
{
    public function run(): void
    {
        $rolesBySlug = Role::whereIn('slug', [
            'operator-front-office',
            'grafician',
            'operator-tipografie',
        ])->get()->keyBy('slug');

        foreach (['operator-front-office', 'grafician', 'operator-tipografie'] as $requiredSlug) {
            if (!$rolesBySlug->has($requiredSlug)) {
                throw new \RuntimeException("Missing role with slug: {$requiredSlug}");
            }
        }

        $this->seedUsersForRole(
            $rolesBySlug['operator-front-office'],
            'Frontdesk',
            ['frontdesk1@demo.local', 'frontdesk2@demo.local'],
        );

        $this->seedUsersForRole(
            $rolesBySlug['grafician'],
            'Grafician',
            ['grafician1@demo.local', 'grafician2@demo.local'],
        );

        $this->seedUsersForRole(
            $rolesBySlug['operator-tipografie'],
            'Executant',
            ['executant1@demo.local', 'executant2@demo.local'],
        );
    }

    private function seedUsersForRole(Role $role, string $label, array $emails): void
    {
        foreach (array_values($emails) as $index => $email) {
            $user = User::firstOrNew(['email' => $email]);

            if (!$user->exists) {
                $user->fill([
                    'name' => "{$label} " . ($index + 1),
                    'email' => $email,
                    'password' => Hash::make('password'),
                    'activ' => true,
                ]);

                $user->forceFill([
                    'email_verified_at' => now(),
                ]);

                $user->save();
            }

            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    }
}

