<?php

namespace Modules\Auth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Settings\ProfileSection;
use Modules\Auth\Settings\SecuritySection;
use Saucebase\Core\Http\Middleware\HandleInertiaRequests;
use Saucebase\Core\Settings\SettingsSection;
use STS\FilamentImpersonate\Events\EnterImpersonation;
use STS\FilamentImpersonate\Events\LeaveImpersonation;
use Tests\TestCase;

/**
 * Taking over an account from a stolen session means adding a way back in (a passkey)
 * or moving where the alerts go (the email). Both ask for fresh proof first: the
 * password, or one of the user's passkeys. Laravel's `password.confirm` keeps it for
 * `auth.password_timeout`, like GitHub's sudo mode.
 */
class ConfirmIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_right_password_confirms_the_session(): void
    {
        $this->actingAs($this->createUser())
            ->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertNotNull(session('auth.password_confirmed_at'));
    }

    public function test_a_wrong_password_does_not(): void
    {
        $this->actingAs($this->createUser())
            ->post(route('password.confirm.store'), ['password' => 'wrong'])
            ->assertSessionHasErrors('password');

        $this->assertNull(session('auth.password_confirmed_at'));
    }

    /** The dialog confirms over JSON: an Inertia visit would reload the panel mid-ceremony. */
    public function test_the_dialog_confirms_over_json(): void
    {
        $this->actingAs($this->createUser())
            ->postJson(route('password.confirm.store'), ['password' => 'password'])
            ->assertNoContent();

        $this->assertNotNull(session('auth.password_confirmed_at'));

        $this->postJson(route('password.confirm.store'), ['password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_the_confirm_page_sends_the_user_to_the_security_panel(): void
    {
        $this->actingAs($this->createUser())
            ->get(route('password.confirm'))
            ->assertRedirect(SettingsSection::url('security'));
    }

    public function test_changing_the_email_needs_a_recent_confirmation(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->patch(route('settings.profile.update-info'), ['name' => $user->name, 'email' => 'attacker@example.com'])
            ->assertSessionHasErrors(['email', 'confirm_identity']);

        $this->assertNotSame('attacker@example.com', $user->fresh()->email);
    }

    public function test_changing_only_the_name_needs_none(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->patch(route('settings.profile.update-info'), ['name' => 'New Name', 'email' => $user->email])
            ->assertSessionHasNoErrors();

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_a_confirmed_session_can_change_the_email(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('settings.profile.update-info'), ['name' => $user->name, 'email' => 'moved@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame('moved@example.com', $user->fresh()->email);
    }

    public function test_a_confirmation_expires(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time() - config('auth.password_timeout') - 1])
            ->patch(route('settings.profile.update-info'), ['name' => $user->name, 'email' => 'moved@example.com'])
            ->assertSessionHasErrors('email');
    }

    /**
     * Until when, not whether: a panel left open past the timeout must ask again
     * rather than trust what it was told when it loaded.
     */
    public function test_the_panels_say_until_when_the_session_is_confirmed(): void
    {
        $this->actingAs($this->createUser());

        foreach ([SecuritySection::class, ProfileSection::class] as $section) {
            $props = app($section)->props();

            $this->assertNull($props['password_confirmed_until']);
            $this->assertSame(config('auth.password_timeout'), $props['password_timeout']);
        }

        $confirmedAt = time() - 60;
        session(['auth.password_confirmed_at' => $confirmedAt]);

        foreach ([SecuritySection::class, ProfileSection::class] as $section) {
            $this->assertSame(
                $confirmedAt + config('auth.password_timeout'),
                app($section)->props()['password_confirmed_until'],
            );
        }
    }

    /**
     * Where `password.confirm` sends a stopped Inertia visit (removing a passkey):
     * back to the panel with an error it reopens the dialog on, not off the page.
     */
    public function test_a_stopped_visit_goes_back_asking_to_confirm(): void
    {
        $this->actingAs($this->createUser())
            ->from('/dashboard')
            ->get(route('password.confirm'), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrors('confirm_identity');
    }

    /** The email dialog offers "Use a passkey" only to someone who has one. */
    public function test_the_profile_panel_says_whether_the_user_has_passkeys(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->assertFalse(app(ProfileSection::class)->props()['has_passkeys']);

        $user->passkeys()->create(['name' => 'Laptop', 'credential_id' => 'abc', 'credential' => []]);

        $this->assertTrue(app(ProfileSection::class)->props()['has_passkeys']);
    }

    /**
     * The confirmation is a session timestamp, and impersonation swaps the user inside
     * the same session: a password confirmed as the impersonated user must not vouch
     * for the admin after leaving, nor the admin's for the user.
     */
    public function test_impersonating_forgets_the_confirmation(): void
    {
        [$admin, $user] = [$this->createUser(), $this->createUser()];

        session()->put('auth.password_confirmed_at', time());
        event(new EnterImpersonation($admin, $user));
        $this->assertFalse(session()->has('auth.password_confirmed_at'));

        session()->put('auth.password_confirmed_at', time());
        event(new LeaveImpersonation($admin, $user));
        $this->assertFalse(session()->has('auth.password_confirmed_at'));
    }
}
