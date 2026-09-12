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

    /**
     * Verification is earned by an address, not by the account: a new one starts
     * unconfirmed however long the old one had been trusted.
     */
    public function test_changing_the_email_clears_the_verified_timestamp(): void
    {
        $user = $this->createUser();
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($user)
            ->patch(route('settings.profile.update-info'), [
                'name' => $user->name,
                'email' => 'moved@example.com',
            ]);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_keeping_the_same_email_keeps_the_verified_timestamp(): void
    {
        $user = $this->createUser();
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($user)
            ->patch(route('settings.profile.update-info'), [
                'name' => 'Updated Name',
                'email' => $user->email,
            ]);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    /** The column is not mass assignable, so a payload cannot grant itself trust. */
    public function test_a_request_cannot_mark_its_own_email_verified(): void
    {
        $user = $this->createUser();
        $user->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($user)
            ->patch(route('settings.profile.update-info'), [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => now()->toDateTimeString(),
            ]);

        $this->assertNull($user->fresh()->email_verified_at);
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
