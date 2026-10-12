<?php

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Auth\Models\MagicLinkToken;
use Modules\Auth\Notifications\WelcomeNotification;
use Modules\Auth\Settings\AuthSettings;
use Saucebase\Core\Facades\Home;
use Tests\TestCase;

/**
 * Every way in lands where the application says, and says which way it was: a new
 * account can be sent to onboarding while a returning one goes straight to work.
 */
class HomeRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Home::using(fn (Request $request, string $reason): string => url("/landing/{$reason}"));
    }

    public function test_signing_in_lands_on_the_applications_home(): void
    {
        $user = $this->createUser();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(url('/landing/login'));
    }

    public function test_a_signed_in_visitor_to_a_guest_page_is_sent_home(): void
    {
        $this->actingAs($this->createUser())->get(route('login'))
            ->assertRedirect(url('/landing/visit'));
    }

    public function test_signing_up_lands_on_the_applications_home_for_new_accounts(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => true,
        ])->assertRedirect(url('/landing/registered'));
    }

    public function test_a_magic_link_lands_on_the_applications_home(): void
    {
        $settings = app(AuthSettings::class);
        $settings->magic_link_enabled = true;
        $settings->save();

        $user = $this->createUser();
        $plainToken = Str::random(64);

        MagicLinkToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->get(route('magic-link.authenticate', $plainToken))
            ->assertRedirect(url('/landing/login'));
    }

    public function test_verifying_an_address_lands_on_the_applications_home(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)->get($verificationUrl)
            ->assertRedirect(url('/landing/verified?verified=1'));
    }

    /** Asked when clicked, not when sent: the email may be opened long after. */
    public function test_the_welcome_email_links_to_home(): void
    {
        $mail = (new WelcomeNotification)->toMail($this->createUser());

        $this->assertSame(route('home'), $mail->actionUrl);
    }
}
