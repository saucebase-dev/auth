<?php

namespace Modules\Auth\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;

/**
 * Signing in stores a fingerprint of the password in the session, and `AuthenticateSession`
 * ends the session once it stops matching. An account with no password (a social sign-up)
 * gets none, so a first password would leave its sessions open (sc-1259). This stores the
 * fingerprint of no password instead: setting one changes it like any other change.
 */
class FingerprintPasswordlessSession
{
    public function handle(Login $event): void
    {
        $guard = Auth::guard($event->guard);

        if (! $guard instanceof SessionGuard || $event->user->getAuthPassword()) {
            return;
        }

        session()->put('password_hash_'.$event->guard, $guard->hashPasswordForCookie(null));
    }
}
