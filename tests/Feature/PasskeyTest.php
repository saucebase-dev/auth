<?php

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\TextStringObject;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Queue\SyncQueue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Support\WebAuthn;
use Modules\Auth\Events\ReturningUserAuthenticated;
use Modules\Auth\Notifications\PasskeyAddedNotification;
use Modules\Auth\Settings\AuthSettings;
use Modules\Auth\Settings\SecuritySection;
use ParagonIE\ConstantTime\Base64UrlSafe;
use RuntimeException;
use Saucebase\Core\Facades\Home;
use STS\FilamentImpersonate\ImpersonateManager;
use Tests\TestCase;
use Webauthn\AuthenticatorData;
use Webauthn\Exception\AuthenticatorResponseVerificationException;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\U2FPublicKey;

/**
 * The browser ceremony itself is the package's to verify; these tests stub
 * `VerifyPasskey` and `StorePasskey` and cover what the module adds around them:
 * the setting, the sign-in, ownership, impersonation and the notification.
 */
class PasskeyTest extends TestCase
{
    use RefreshDatabase;

    /** Passkeys ship switched off; every test here but the disabled ones needs them on. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->setPasskeysEnabled(true);

        // Managing passkeys needs a recent confirmation; the gate has its own tests.
        $this->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_passkey_routes_return_404_while_the_feature_is_off(): void
    {
        $this->setPasskeysEnabled(false);

        $this->getJson(route('auth.passkeys.options'))->assertNotFound();
        $this->postJson(route('auth.passkeys.login'))->assertNotFound();

        $this->actingAs($this->createUser());

        $this->getJson(route('settings.passkeys.options'))->assertNotFound();
        $this->postJson(route('settings.passkeys.store'))->assertNotFound();
    }

    public function test_passkey_sign_in_has_its_own_rate_limit(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->getJson(route('auth.passkeys.options'))->assertOk();
        }

        $this->getJson(route('auth.passkeys.options'))->assertTooManyRequests();

        // Its own counter: a run of passkey attempts leaves other sign-in routes alone.
        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertStatus(302);
    }

    public function test_the_package_routes_are_not_registered(): void
    {
        $this->getJson('/passkeys/login/options')->assertNotFound();
        $this->getJson('/user/passkeys/options')->assertNotFound();
    }

    public function test_login_options_name_no_account(): void
    {
        $response = $this->getJson(route('auth.passkeys.options'))
            ->assertOk()
            ->assertJsonStructure(['options' => ['challenge', 'rpId']]);

        $this->assertEmpty($response->json('options.allowCredentials') ?? []);
        $this->assertNotNull(session('passkey.verification_options'));
    }

    public function test_signed_in_users_cannot_request_login_options(): void
    {
        $this->actingAs($this->createUser())
            ->getJson(route('auth.passkeys.options'))
            ->assertRedirect();
    }

    public function test_a_verified_passkey_signs_its_owner_in_and_lands_on_home(): void
    {
        Home::using(fn (Request $request, string $reason): string => url("/landing/{$reason}"));
        Event::fake([ReturningUserAuthenticated::class]);

        $user = $this->createUser();
        $passkey = $this->createPasskey($user);

        $this->mock(VerifyPasskey::class)
            ->shouldReceive('__invoke')
            ->once()
            ->andReturn($passkey);

        $this->withSession(['passkey.verification_options' => WebAuthn::toJson($this->requestOptions())])
            ->postJson(route('auth.passkeys.login'), [
                'credential' => $this->assertionCredential(),
                'remember' => true,
            ])
            ->assertOk()
            ->assertExactJson(['redirect' => url('/landing/login')]);

        $this->assertAuthenticatedAs($user);
        Event::assertDispatched(
            ReturningUserAuthenticated::class,
            fn (ReturningUserAuthenticated $event): bool => $event->user->is($user),
        );
    }

    public function test_a_passkey_sign_in_returns_to_the_intended_url(): void
    {
        $user = $this->createUser();

        $this->mock(VerifyPasskey::class)
            ->shouldReceive('__invoke')
            ->andReturn($this->createPasskey($user));

        $this->withSession([
            'url.intended' => url('/somewhere'),
            'passkey.verification_options' => WebAuthn::toJson($this->requestOptions()),
        ])
            ->postJson(route('auth.passkeys.login'), ['credential' => $this->assertionCredential()])
            ->assertOk()
            ->assertExactJson(['redirect' => url('/somewhere')]);
    }

    public function test_a_passkey_that_fails_verification_does_not_sign_in(): void
    {
        $this->mock(VerifyPasskey::class)
            ->shouldReceive('__invoke')
            ->andThrow(InvalidPasskeyException::make('Passkey not recognized.'));

        $this->withSession(['passkey.verification_options' => WebAuthn::toJson($this->requestOptions())])
            ->postJson(route('auth.passkeys.login'), ['credential' => $this->assertionCredential()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('credential');

        $this->assertGuest();
    }

    public function test_a_signature_the_webauthn_library_rejects_is_a_validation_error(): void
    {
        $this->mock(VerifyPasskey::class)
            ->shouldReceive('__invoke')
            ->andThrow(AuthenticatorResponseVerificationException::create('Invalid signature.'));

        $this->withSession(['passkey.verification_options' => WebAuthn::toJson($this->requestOptions())])
            ->postJson(route('auth.passkeys.login'), ['credential' => $this->assertionCredential()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('credential');

        $this->assertGuest();
    }

    public function test_an_attestation_the_webauthn_library_rejects_is_a_validation_error(): void
    {
        $user = $this->createUser();

        $this->mock(StorePasskey::class)
            ->shouldReceive('__invoke')
            ->andThrow(AuthenticatorResponseVerificationException::create('Invalid origin.'));

        $this->actingAs($user)
            ->withSession(['passkey.registration_options' => WebAuthn::toJson($this->creationOptions($user))])
            ->postJson(route('settings.passkeys.store'), [
                'name' => 'Work laptop',
                'credential' => $this->attestationCredential(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('credential');

        $this->assertSame(0, $user->passkeys()->count());
    }

    public function test_a_sign_in_without_a_challenge_is_refused(): void
    {
        $this->postJson(route('auth.passkeys.login'), ['credential' => $this->assertionCredential()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('credential');

        $this->assertGuest();
    }

    public function test_registration_options_require_a_signed_in_user(): void
    {
        $this->getJson(route('settings.passkeys.options'))->assertUnauthorized();
    }

    public function test_registration_options_are_issued_for_the_signed_in_user(): void
    {
        $this->actingAs($this->createUser())
            ->getJson(route('settings.passkeys.options'))
            ->assertOk()
            ->assertJsonStructure(['options' => ['challenge', 'rp', 'user']]);

        $this->assertNotNull(session('passkey.registration_options'));
    }

    public function test_adding_a_passkey_stores_it_and_emails_the_owner(): void
    {
        Notification::fake();

        $user = $this->createUser();

        $this->mock(StorePasskey::class)
            ->shouldReceive('__invoke')
            ->once()
            ->andReturnUsing(fn (User $owner, string $name): Passkey => $this->createPasskey($owner, $name));

        $this->actingAs($user)
            ->withSession(['passkey.registration_options' => WebAuthn::toJson($this->creationOptions($user))])
            ->postJson(route('settings.passkeys.store'), [
                'name' => 'Work laptop',
                'credential' => $this->attestationCredential(),
            ])
            ->assertOk()
            ->assertJson(['name' => 'Work laptop']);

        $this->assertSame(['Work laptop'], $user->passkeys()->pluck('name')->all());
        Notification::assertSentTo(
            $user,
            PasskeyAddedNotification::class,
            fn (PasskeyAddedNotification $notification): bool => $notification->passkeyName === 'Work laptop',
        );
    }

    public function test_managing_passkeys_needs_a_recent_confirmation(): void
    {
        session()->forget('auth.password_confirmed_at');

        $user = $this->createUser();
        $passkey = $this->createPasskey($user);

        $this->actingAs($user)->getJson(route('settings.passkeys.options'))->assertStatus(423);
        $this->actingAs($user)->postJson(route('settings.passkeys.store'))->assertStatus(423);
        $this->actingAs($user)
            ->delete(route('settings.passkeys.destroy', $passkey->id))
            ->assertRedirect(route('password.confirm'));

        $this->assertModelExists($passkey);
    }

    public function test_one_of_the_users_passkeys_confirms_the_session(): void
    {
        session()->forget('auth.password_confirmed_at');

        $user = $this->createUser();
        $passkey = $this->createPasskey($user);

        $this->actingAs($user)
            ->getJson(route('settings.passkeys.confirm.options'))
            ->assertOk()
            ->assertJsonStructure(['options' => ['challenge', 'allowCredentials']]);

        $this->mock(VerifyPasskey::class)
            ->shouldReceive('__invoke')
            ->once()
            ->withArgs(fn ($credential, $options, $owner): bool => $owner->is($user))
            ->andReturn($passkey);

        $this->withSession(['passkey.verification_options' => WebAuthn::toJson($this->requestOptions())])
            ->postJson(route('settings.passkeys.confirm'), ['credential' => $this->assertionCredential()])
            ->assertSuccessful();

        $this->assertNotNull(session('auth.password_confirmed_at'));
    }

    public function test_a_failed_alert_leaves_no_passkey_behind(): void
    {
        $user = $this->createUser();

        $this->mock(StorePasskey::class)
            ->shouldReceive('__invoke')
            ->andReturnUsing(fn (User $owner, string $name): Passkey => $this->createPasskey($owner, $name));

        $this->mock(Dispatcher::class)
            ->shouldReceive('send')
            ->andThrow(new RuntimeException('Queue is down'));

        $this->actingAs($user)
            ->withSession(['passkey.registration_options' => WebAuthn::toJson($this->creationOptions($user))])
            ->postJson(route('settings.passkeys.store'), [
                'name' => 'Work laptop',
                'credential' => $this->attestationCredential(),
            ])
            ->assertServerError();

        $this->assertSame(0, $user->passkeys()->count());
    }

    /**
     * With `after_commit` on the queue connection the alert would be pushed only after
     * the passkey had committed, so a queue failure there left a passkey behind.
     */
    public function test_a_failed_alert_leaves_no_passkey_behind_when_queues_wait_for_commit(): void
    {
        $user = $this->createUser();

        $this->mock(StorePasskey::class)
            ->shouldReceive('__invoke')
            ->andReturnUsing(fn (User $owner, string $name): Passkey => $this->createPasskey($owner, $name));

        Queue::extend('failing', fn () => new class implements ConnectorInterface
        {
            public function connect(array $config)
            {
                return new class($config['after_commit']) extends SyncQueue
                {
                    protected function executeJob($job, $data = '', $queue = null)
                    {
                        throw new RuntimeException('Queue is down');
                    }
                };
            }
        });
        config()->set('queue.connections.failing', ['driver' => 'failing', 'after_commit' => true]);
        config()->set('queue.default', 'failing');

        $this->actingAs($user)
            ->withSession(['passkey.registration_options' => WebAuthn::toJson($this->creationOptions($user))])
            ->postJson(route('settings.passkeys.store'), [
                'name' => 'Work laptop',
                'credential' => $this->attestationCredential(),
            ])
            ->assertServerError();

        $this->assertSame(0, $user->passkeys()->count());
    }

    public function test_the_alert_retries_with_backoff(): void
    {
        $notification = new PasskeyAddedNotification('Work laptop');

        $this->assertSame(3, $notification->tries);
        $this->assertSame([60, 300], $notification->backoff());
    }

    public function test_a_passkey_cannot_be_added_while_impersonating(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->withSession([ImpersonateManager::SESSION_KEY => $this->createUser()->id])
            ->getJson(route('settings.passkeys.options'))
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession([
                ImpersonateManager::SESSION_KEY => $this->createUser()->id,
                'passkey.registration_options' => WebAuthn::toJson($this->creationOptions($user)),
            ])
            ->postJson(route('settings.passkeys.store'), [
                'name' => 'Planted',
                'credential' => $this->attestationCredential(),
            ])
            ->assertForbidden();

        $this->assertSame(0, $user->passkeys()->count());
    }

    public function test_a_user_can_remove_their_own_passkey(): void
    {
        $user = $this->createUser();
        $passkey = $this->createPasskey($user);

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->delete(route('settings.passkeys.destroy', $passkey->id))
            ->assertRedirect(route('dashboard'));

        $this->assertModelMissing($passkey);
    }

    public function test_a_passkey_can_be_removed_after_the_feature_is_switched_off(): void
    {
        $user = $this->createUser();
        $passkey = $this->createPasskey($user);

        $this->setPasskeysEnabled(false);

        $this->actingAs($user)
            ->delete(route('settings.passkeys.destroy', $passkey->id))
            ->assertRedirect();

        $this->assertModelMissing($passkey);
    }

    public function test_a_user_cannot_remove_another_users_passkey(): void
    {
        $passkey = $this->createPasskey($this->createUser());

        $this->actingAs($this->createUser())
            ->delete(route('settings.passkeys.destroy', $passkey->id))
            ->assertNotFound();

        $this->assertModelExists($passkey);
    }

    public function test_the_security_panel_lists_passkeys_without_their_credentials(): void
    {
        $user = $this->createUser();
        $this->createPasskey($user, 'Phone');

        $this->actingAs($user);

        $props = app(SecuritySection::class)->props();

        $this->assertCount(1, $props['passkeys']);
        $this->assertSame('Phone', $props['passkeys'][0]['name']);
        $this->assertArrayNotHasKey('credential', $props['passkeys'][0]);
        $this->assertArrayNotHasKey('credential_id', $props['passkeys'][0]);
        $this->assertTrue($props['can_add_passkeys']);
    }

    public function test_the_security_panel_offers_no_new_passkey_while_impersonating(): void
    {
        $this->actingAs($this->createUser());

        session([ImpersonateManager::SESSION_KEY => $this->createUser()->id]);

        $this->assertFalse(app(SecuritySection::class)->props()['can_add_passkeys']);
    }

    public function test_deleting_a_user_deletes_their_passkeys(): void
    {
        $user = $this->createUser();
        $passkey = $this->createPasskey($user);

        $user->delete();

        $this->assertModelMissing($passkey);
    }

    private function setPasskeysEnabled(bool $enabled): void
    {
        $settings = app(AuthSettings::class);
        $settings->passkeys_enabled = $enabled;
        $settings->save();
    }

    private function createPasskey(User $user, string $name = 'My passkey'): Passkey
    {
        return $user->passkeys()->create([
            'name' => $name,
            'credential_id' => Base64UrlSafe::encodeUnpadded(random_bytes(16)),
            'credential' => [],
        ]);
    }

    private function requestOptions(): PublicKeyCredentialRequestOptions
    {
        return PublicKeyCredentialRequestOptions::create(
            challenge: random_bytes(32),
            rpId: 'localhost',
        );
    }

    private function creationOptions(User $user): PublicKeyCredentialCreationOptions
    {
        return PublicKeyCredentialCreationOptions::create(
            rp: PublicKeyCredentialRpEntity::create('Test App', 'localhost'),
            user: PublicKeyCredentialUserEntity::create($user->email, (string) $user->id, $user->name),
            challenge: random_bytes(32),
        );
    }

    /**
     * An assertion that survives deserialization but signs nothing; only
     * reaches a stubbed `VerifyPasskey`.
     *
     * @return array<string, mixed>
     */
    private function assertionCredential(): array
    {
        $rawId = random_bytes(16);
        $authData = hash('sha256', 'localhost', binary: true).chr(AuthenticatorData::FLAG_UP).pack('N', 0);

        return [
            'id' => Base64UrlSafe::encodeUnpadded($rawId),
            'type' => 'public-key',
            'rawId' => Base64UrlSafe::encodeUnpadded($rawId),
            'response' => [
                'clientDataJSON' => Base64UrlSafe::encodeUnpadded((string) json_encode([
                    'type' => 'webauthn.get',
                    'challenge' => Base64UrlSafe::encodeUnpadded(random_bytes(32)),
                    'origin' => 'https://localhost',
                ])),
                'authenticatorData' => Base64UrlSafe::encodeUnpadded($authData),
                'signature' => Base64UrlSafe::encodeUnpadded(random_bytes(64)),
            ],
        ];
    }

    /**
     * A "none" attestation that survives deserialization but attests nothing;
     * only reaches a stubbed `StorePasskey`.
     *
     * @return array<string, mixed>
     */
    private function attestationCredential(): array
    {
        $credentialId = random_bytes(16);

        $key = openssl_pkey_get_details(openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]))['ec'];
        $coseKey = U2FPublicKey::convertToCoseKey("\x04".str_pad($key['x'], 32, "\0", STR_PAD_LEFT).str_pad($key['y'], 32, "\0", STR_PAD_LEFT));

        $authData = hash('sha256', 'localhost', binary: true)
            .chr(AuthenticatorData::FLAG_UP | AuthenticatorData::FLAG_AT)
            .pack('N', 0)
            .str_repeat("\0", 16)
            .pack('n', strlen($credentialId)).$credentialId
            .$coseKey;

        $attestationObject = MapObject::create()
            ->add(TextStringObject::create('fmt'), TextStringObject::create('none'))
            ->add(TextStringObject::create('attStmt'), MapObject::create())
            ->add(TextStringObject::create('authData'), ByteStringObject::create($authData));

        return [
            'id' => Base64UrlSafe::encodeUnpadded($credentialId),
            'rawId' => Base64UrlSafe::encodeUnpadded($credentialId),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => Base64UrlSafe::encodeUnpadded((string) json_encode([
                    'type' => 'webauthn.create',
                    'challenge' => Base64UrlSafe::encodeUnpadded(random_bytes(32)),
                    'origin' => 'https://localhost',
                ])),
                'attestationObject' => Base64UrlSafe::encodeUnpadded((string) $attestationObject),
            ],
        ];
    }
}
