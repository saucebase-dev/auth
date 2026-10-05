<?php

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Database\Seeders\DatabaseSeeder;
use Modules\Auth\Database\Seeders\DemoAuthDatabaseSeeder;
use Tests\TestCase;

/**
 * The demo ships a staff account for this module's admin area, so visitors can try a
 * role that sees only part of the panel.
 */
class DemoStaffTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_adds_a_staff_account_for_this_module_only(): void
    {
        $this->seed([DatabaseSeeder::class, DemoAuthDatabaseSeeder::class]);

        $staff = User::where('email', 'support@saucebase.dev')->firstOrFail();

        $this->assertSame(['support'], $staff->getRoleNames()->all());
        $this->assertEqualsCanonicalizing(['access admin panel', 'manage users', 'impersonate users'], $staff->getAllPermissions()->pluck('name')->all());
    }
}
