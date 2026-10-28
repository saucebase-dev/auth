<?php

namespace Modules\Auth\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;
use Laravel\Passkeys\Support\WebAuthn;
use Saucebase\Core\Facades\Home;
use Webauthn\Exception\WebauthnException;

/**
 * Signs a returning user in with a passkey.
 *
 * Takes the place of the package's own login controller so a passkey sign-in goes
 * through `signIn()` like every other way in — the login notification and the
 * welcome-back toast included — and lands on Home rather than a fixed URL.
 *
 * Both endpoints answer JSON: `@laravel/passkeys` calls them with `fetch`, not
 * Inertia, and follows the `redirect` it is given.
 */
class PasskeyLoginController extends Controller
{
    /**
     * Start a sign-in ceremony.
     *
     * The challenge names no account (`allowCredentials` stays empty), so the
     * browser offers whichever passkeys it holds for this site and the response
     * never reveals whether an email is registered.
     */
    public function options(Request $request, GenerateVerificationOptions $generate): JsonResponse
    {
        $options = $generate();

        $request->session()->put('passkey.verification_options', WebAuthn::toJson($options));

        return response()->json([
            'options' => WebAuthn::toBrowserArray($options),
        ]);
    }

    /**
     * Verify the signed challenge and sign its owner in.
     *
     * The request pulls the challenge out of the session, so each one verifies
     * once. A passkey that is unknown or fails verification is a validation error
     * on `credential`, which the client shows as the message — including a bad
     * signature or origin, which the WebAuthn library reports as an exception the
     * package lets through as a 500.
     */
    public function store(PasskeyVerificationRequest $request, VerifyPasskey $verify): JsonResponse
    {
        try {
            $passkey = $verify($request->credential(), $request->verificationOptions());
        } catch (WebauthnException) {
            throw InvalidPasskeyException::make('Unable to verify passkey. Please try again.');
        }

        /** @var User $user */
        $user = $passkey->user;

        $this->signIn($request, $user, $request->remember());

        return response()->json([
            'redirect' => redirect()->intended(Home::url($request, Home::LOGIN))->getTargetUrl(),
        ]);
    }
}
