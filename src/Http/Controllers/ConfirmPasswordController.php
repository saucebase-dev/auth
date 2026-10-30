<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Saucebase\Core\Settings\SettingsSection;

/**
 * Fresh proof before an account-changing action: adding or removing a passkey, or
 * changing the email. The Security and Profile panels ask for it in a dialog; this
 * marks the session the way Laravel's `password.confirm` middleware expects.
 */
class ConfirmPasswordController extends Controller
{
    /**
     * Where `password.confirm` sends a request it stopped. The dialog lives in the
     * panels, so there is no page of its own: an Inertia visit goes back with an
     * error the panel reopens the dialog on, anything else to the Security panel.
     */
    public function show(Request $request): RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            return back()->withErrors(['confirm_identity' => __('Confirm it\'s you to continue.')]);
        }

        return redirect()->to(SettingsSection::url('security'));
    }

    /**
     * The dialog posts over JSON: an Inertia visit would reload the panel and race
     * the passkey ceremony's challenge in the session.
     */
    public function store(Request $request): RedirectResponse|Response
    {
        $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        $request->session()->passwordConfirmed();

        return $request->wantsJson() ? response()->noContent() : back();
    }
}
