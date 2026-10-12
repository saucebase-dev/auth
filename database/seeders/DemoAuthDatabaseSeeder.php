<?php

namespace Modules\Auth\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DemoAuthDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // A staff account that sees only this module's admin area.
        Role::findOrCreate('support')
            ->syncPermissions([
                'access admin panel',
                'manage users',
                'impersonate users',
            ]);

        $staff = User::firstOrCreate(
            ['email' => 'support@saucebase.dev'],
            ['name' => 'Support Agent', 'password' => bcrypt('secretsauce')],
        );
        $staff->syncRoles('support');

        $user = User::firstOrCreate(
            ['email' => 'chef@saucebase.dev'],
            [
                'name' => 'Admin Chef',
                'password' => bcrypt('secretsauce'),
            ]
        );

        $user->assignRole('user');
    }
}
