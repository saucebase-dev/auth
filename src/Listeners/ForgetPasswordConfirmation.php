<?php

namespace Modules\Auth\Listeners;

use STS\FilamentImpersonate\Events\EnterImpersonation;
use STS\FilamentImpersonate\Events\LeaveImpersonation;

/**
 * Impersonation swaps the user inside one session, so a password confirmed as one
 * account would otherwise vouch for the other: the confirmation belongs to whoever
 * gave it.
 */
class ForgetPasswordConfirmation
{
    public function handle(EnterImpersonation|LeaveImpersonation $event): void
    {
        session()->forget('auth.password_confirmed_at');
    }
}
