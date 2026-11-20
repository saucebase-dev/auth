<?php

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Modules\Auth\Notifications\LoginNotification;
use Modules\Auth\Notifications\WelcomeNotification;
use Modules\Auth\Settings\AuthSettings;
use Saucebase\Core\RegistrationInvite;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_renders_for_guests(): void
    {
        $response = $this->get(route('register'));

        $response->assertStatus(200);
    }

    public function test_register_page_is_not_found_when_registration_is_disabled(): void
    {
        $this->disableRegistration();

        $this->get(route('register'))->assertNotFound();
    }

    public function test_user_cannot_register_when_registration_is_disabled(): void
    {
        $this->disableRegistration();

        $this->post(route('register'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => true,
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    // ── Invited while sign-up is closed (RegistrationInvite, set by e.g. tenancy) ──

    /** @return array<string, mixed> */
    /** A malformed address is a validation error, not a 500 from the invitation check. */
    public function test_an_email_that_is_not_a_string_is_a_validation_error(): void
    {
        $this->post(route('register'), [...$this->signUp(''), 'email' => ['ana@example.com']])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    private function signUp(string $email): array
    {
        return ['name' => 'Ana', 'email' => $email, 'password' => 'password123', 'terms' => true];
    }

    public function test_an_invited_guest_reaches_the_closed_register_page_with_their_address(): void
    {
        $this->disableRegistration();

        $this->withSession(['saucebase.registration_invite' => 'ana@example.com'])
            ->get(route('register'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('by_invitation.email', 'ana@example.com'));
    }

    /** The invitation came to that inbox, so the account starts verified and the invite is used up. */
    public function test_an_invited_guest_registers_their_address_verified(): void
    {
        $this->disableRegistration();
        RegistrationInvite::allow('ana@example.com');

        $this->post(route('register'), $this->signUp('Ana@example.com'))->assertRedirect();

        $this->assertNotNull(User::where('email', 'ana@example.com')->sole()->email_verified_at);
        $this->assertNull(RegistrationInvite::email());
    }

    /** An invitation for one address opens sign-up for that address only. */
    public function test_an_invited_guest_cannot_register_another_address(): void
    {
        $this->disableRegistration();
        RegistrationInvite::allow('ana@example.com');

        $this->post(route('register'), $this->signUp('someone@else.com'))->assertInvalid('email');

        $this->assertDatabaseMissing('users', ['email' => 'someone@else.com']);
    }

    /** With sign-up open, an invited address still starts verified; anyone else signs up as usual. */
    public function test_with_sign_up_open_only_the_invited_address_starts_verified(): void
    {
        RegistrationInvite::allow('ana@example.com');

        $this->post(route('register'), $this->signUp('bob@example.com'))->assertRedirect();

        $this->assertNull(User::where('email', 'bob@example.com')->sole()->email_verified_at);
    }

    public function test_the_register_page_has_no_invitation_without_one(): void
    {
        $this->get(route('register'))->assertInertia(fn ($page) => $page->where('by_invitation', null));
    }

    private function disableRegistration(): void
    {
        $settings = app(AuthSettings::class);
        $settings->registration_enabled = false;
        $settings->save();
    }

    public function test_user_can_register_with_valid_data(): void
    {
        Notification::fake();

        $response = $this->post(route('register'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => true,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_registered_user_is_assigned_user_role(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => true,
        ]);

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('user'));
    }

    public function test_welcome_notification_is_sent_on_registration(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => true,
        ]);

        $user = User::where('email', 'test@example.com')->firstOrFail();
        Notification::assertSentTo($user, WelcomeNotification::class);
    }

    public function test_registration_does_not_send_login_notification(): void
    {
        Notification::fake();

        $settings = app(AuthSettings::class);
        $settings->login_notification_enabled = true;
        $settings->save();

        $this->post(route('register'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => true,
        ]);

        $user = User::where('email', 'test@example.com')->sole();

        Notification::assertNotSentTo($user, LoginNotification::class);
    }

    public function test_password_is_hashed_on_registration(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => true,
        ]);

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $user->getAuthPassword()));
        $this->assertNotEquals('password123', $user->getAuthPassword());
    }

    public function test_register_validates_name_is_required(): void
    {
        $response = $this->post(route('register'), [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertInvalid('name');
    }

    public function test_register_validates_email_is_required(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Test User',
            'password' => 'password123',
        ]);

        $response->assertInvalid('email');
    }

    public function test_register_validates_email_is_unique(): void
    {
        $existing = User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->post(route('register'), [
            'name' => 'Test User',
            'email' => 'taken@example.com',
            'password' => 'password123',
        ]);

        $response->assertInvalid('email');
    }

    public function test_register_validates_email_format(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Test User',
            'email' => 'not-an-email',
            'password' => 'password123',
        ]);

        $response->assertInvalid('email');
    }

    public function test_register_validates_password_is_required(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $response->assertInvalid('password');
    }

    public function test_register_validates_terms_must_be_accepted(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertInvalid('terms');
    }
}
