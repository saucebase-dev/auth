<?php

namespace Modules\Auth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_renders(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertStatus(200);
    }

    public function test_reset_link_is_sent_for_registered_email(): void
    {
        $user = $this->createUser();

        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status');
    }

    /**
     * Without a limit this endpoint mails anybody on demand. The response stays
     * the same either way, so the limit cannot be used to probe for accounts.
     */
    public function test_reset_link_requests_are_rate_limited(): void
    {
        $user = $this->createUser();

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post(route('password.email'), ['email' => $user->email])
                ->assertSessionHasNoErrors();
        }

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertStatus(429);
    }

    public function test_unknown_emails_are_throttled_on_the_same_counter(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post(route('password.email'), ['email' => 'nobody@example.com']);
        }

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertStatus(429);
    }

    public function test_reset_password_page_renders_with_valid_token(): void
    {
        $user = $this->createUser();
        $token = Password::createToken($user);

        $response = $this->get(route('password.reset', ['token' => $token]));

        $response->assertStatus(200);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = $this->createUser();
        $token = Password::createToken($user);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->getAuthPassword()));
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = $this->createUser();

        $response = $this->post(route('password.store'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHas('error');
    }

    public function test_reset_password_validates_password_confirmation(): void
    {
        $user = $this->createUser();
        $token = Password::createToken($user);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertInvalid('password');
    }
}
