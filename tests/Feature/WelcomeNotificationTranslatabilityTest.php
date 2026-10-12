<?php

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Modules\Auth\Notifications\WelcomeNotification;
use Tests\TestCase;

/**
 * Every line of the welcome email has to reach the reader through the translator.
 *
 * Lives in the module that owns the notification, so a core installation without this
 * module never tries to load a class it does not have.
 */
class WelcomeNotificationTranslatabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The real JSON loading path, rather than Lang::addLines() — that helper routes
        // keys through Arr::set(), which splits on dots and mangles any string ending in
        // a full stop.
        Lang::addJsonPath(__DIR__.'/../fixtures/lang');
    }

    public function test_welcome_notification_translates_every_line(): void
    {
        $user = User::factory()->create(['name' => 'Ana']);

        App::setLocale('xx');

        $mail = (new WelcomeNotification)->toMail($user);

        $this->assertStringStartsWith('BEMVINDO', $mail->subject);
        $this->assertSame('PAINEL', $mail->actionText);
        $this->assertContains('CRIADA', $mail->introLines);
        $this->assertContains('EXPLORE', $mail->introLines);
        $this->assertContains('GRATO', $mail->outroLines);
    }

    public function test_the_recipient_name_is_a_placeholder_not_a_concatenation(): void
    {
        $user = User::factory()->create(['name' => 'Ana']);

        App::setLocale('xx');

        // Building the greeting with "." would leave "Hello" permanently English.
        $this->assertSame('OLA Ana,', (new WelcomeNotification)->toMail($user)->greeting);
    }
}
