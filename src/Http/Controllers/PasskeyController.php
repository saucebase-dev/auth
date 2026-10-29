<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Http\Requests\PasskeyRegistrationRequest;
use Laravel\Passkeys\Support\WebAuthn;
use Modules\Auth\Notifications\PasskeyAddedNotification;
use Saucebase\Core\Toast;
use STS\FilamentImpersonate\ImpersonateManager;
use Webauthn\Exception\WebauthnException;

/**
 * The signed-in user's own passkeys, managed from the Security panel.
 *
 * Adding one is refused while an admin is impersonating: a passkey outlives the
 * impersonation, so it would be a standing way back into somebody else's account.
 * Removing one is allowed — it only ever takes access away.
 */
class PasskeyController extends Controller
{
    /**
     * Start a registration ceremony for the signed-in user.
     */
    public function options(Request $request, GenerateRegistrationOptions $generate): JsonResponse
    {
        $this->ensureNotImpersonating();

        $options = $generate($request->user());

        $request->session()->put('passkey.registration_options', WebAuthn::toJson($options));

        return response()->json([
            'options' => WebAuthn::toBrowserArray($options),
        ]);
    }

    /**
     * Store the passkey the browser just created.
     *
     * Answers JSON because `@laravel/passkeys` posts it with `fetch`; the panel
     * reloads itself afterwards. The owner is emailed, so a passkey added from a
     * stolen session does not go unnoticed. An attestation the WebAuthn library
     * rejects is a validation error, not the 500 the package would let through.
     */
    public function store(PasskeyRegistrationRequest $request, StorePasskey $store): JsonResponse
    {
        $this->ensureNotImpersonating();

        $user = $request->user();

        // One transaction: if the alert can't be queued, the passkey is not kept
        // either, so a passkey never exists without its owner being told. Queued
        // before the commit, even on a connection set to wait for it.
        $passkey = DB::transaction(function () use ($request, $store, $user) {
            try {
                $passkey = $store(
                    $user,
                    $request->string('name')->toString(),
                    $request->credential(),
                    $request->registrationOptions(),
                );
            } catch (WebauthnException) {
                throw InvalidPasskeyException::make('Unable to register passkey. Please try again.');
            }

            $user->notify((new PasskeyAddedNotification($passkey->name))->beforeCommit());

            return $passkey;
        });

        Toast::success(__('auth::auth.passkeys.added'));

        return response()->json([
            'id' => (string) $passkey->id,
            'name' => $passkey->name,
        ]);
    }

    /**
     * Remove one of the signed-in user's passkeys.
     *
     * Looked up through the user's own relation, so another user's passkey is a
     * 404 rather than a 403 that confirms it exists. The parameter is not called
     * `passkey`: the package binds that name globally to any user's passkey.
     */
    public function destroy(Request $request, int $passkeyId, DeletePasskey $delete): RedirectResponse
    {
        $user = $request->user();

        $delete($user, $user->passkeys()->findOrFail($passkeyId));

        Toast::success(__('auth::auth.passkeys.removed'));

        return back();
    }

    private function ensureNotImpersonating(): void
    {
        abort_if(app(ImpersonateManager::class)->isImpersonating(), 403, __('auth::auth.passkeys.impersonating'));
    }
}
