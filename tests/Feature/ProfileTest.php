<?php

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_route_sends_the_user_to_the_settings_fragment(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get(route('settings.profile'))
            ->assertRedirect(route('dashboard').'#settings/profile');
    }

    public function test_guest_cannot_view_profile(): void
    {
        $this->get(route('settings.profile'))
            ->assertRedirect(route('login'));
    }

    public function test_guest_cannot_update_profile_info(): void
    {
        $this->patch(route('settings.profile.update-info'), [
            'name' => 'Hacker',
            'email' => 'hacker@example.com',
        ])->assertRedirect(route('login'));
    }

    public function test_user_can_update_name_and_email(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->patch(route('settings.profile.update-info'), [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('updated@example.com', $user->email);
    }

    public function test_user_can_keep_their_own_email(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->patch(route('settings.profile.update-info'), [
                'name' => 'Updated Name',
                'email' => $user->email,
            ])
            ->assertValid();

        $this->assertSame('Updated Name', $user->fresh()->name);
    }

    public function test_user_cannot_take_an_email_owned_by_another_user(): void
    {
        $user = $this->createUser();
        $other = User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($user)
            ->patch(route('settings.profile.update-info'), [
                'name' => 'Updated Name',
                'email' => $other->email,
            ])
            ->assertInvalid('email');

        $this->assertNotSame('taken@example.com', $user->fresh()->email);
    }

    public function test_profile_update_requires_name_and_valid_email(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->patch(route('settings.profile.update-info'), [
                'name' => '',
                'email' => 'not-an-email',
            ])
            ->assertInvalid(['name', 'email']);
    }
}
