<?php

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Auth adds the columns it needs to the application's own users table.
 */
class UserColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_adds_its_columns_to_users(): void
    {
        $this->assertTrue(Schema::hasColumns('users', ['avatar', 'last_login_at']));
    }

    /** A user who signed up with a provider has no password. */
    public function test_a_user_may_have_no_password(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->assertNull($user->fresh()->password);
    }
}
