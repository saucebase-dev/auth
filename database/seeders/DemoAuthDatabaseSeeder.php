<?php

namespace Modules\Auth\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoAuthDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
