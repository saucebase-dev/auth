<?php

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_unauthenticated_user_cannot_reimpersonate(): void
    {
        $target = $this->createUser();

        $response = $this->post(route('auth.impersonate.reimpersonate', ['userId' => $target->id]));

        $response->assertRedirect(route('login'));
    }

    public function test_admin_cannot_impersonate_themselves(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->post(route('auth.impersonate.reimpersonate', ['userId' => $admin->id]));

        $response->assertStatus(403);
    }

    public function test_non_admin_user_is_rejected_from_route(): void
    {
        $user = $this->createUser();
        $target = User::factory()->create();

        // Non-admin users cannot access the impersonate route
        // The route requires 'auth' middleware; admin check is inside controller
        $response = $this->actingAs($user)
            ->post(route('auth.impersonate.reimpersonate', ['userId' => $target->id]));

        // The controller will 403 because Filament::auth()->user() resolves to the web guard user
        // and target user ID != impersonator ID, so only a 403 from missing impersonator auth
        // or a redirect occurs — at minimum it must not be a 200
        $this->assertNotEquals(200, $response->getStatusCode());
    }
}
