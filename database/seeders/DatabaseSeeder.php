<?php

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    /**
     * The Users admin page's permissions, granted to nobody: the app's roles seeder
     * decides who gets them, and `admin` passes every check anyway.
     */
    public function run(): void
    {
        Permission::findOrCreate('manage users');
        Permission::findOrCreate('impersonate users');
    }
}
