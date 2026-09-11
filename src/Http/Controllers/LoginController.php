<?php

namespace Modules\Auth\Http\Controllers;

use App\Helpers\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;
use Modules\Auth\Events\ReturningUserAuthenticated;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Settings\AuthSettings;
use Modules\Auth\Http\Requests\LoginRequest;

class LoginController extends Controller
{
    /**
     * Display the login view.
     *
     * The URL is the canonical page; a modal is the exception, and only when both
     * the site allows it and the caller explicitly asked. Deciding on the request
     * header rather than the referer matters: the package would otherwise treat
     * any ordinary in-app link as "open me over the previous page".
     */
    public function create(Request $request, AuthSettings $settings): Response|Modal
    {
        $props = [
            'status' => session('status'),
            'error' => session('error'),
        ];

        if (! $settings->modal_enabled || ! $request->hasHeader(Modal::HEADER_MODAL)) {
            return Inertia::render('Auth::Login', $props);
        }

        return Inertia::modal('Auth::Login', [...$props, 'modal' => true]);
    }

    /**
     * Handle an incoming authentication request.
     * Login
     */
    public function store(LoginRequest $request)
    {
        try {
            $user = $request->validateCredentials();
        } catch (AuthException $e) {
            return back()->with(['error' => $e->getMessage()]);
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        ReturningUserAuthenticated::dispatch(
            $user,
            now(),
            $request->ip(),
            $request->userAgent(),
        );

        Toast::default(
            __('auth::auth.welcome-back', ['name' => $user->name]),
        );

        if ($request->session()->has('url.intended')) {
            return Inertia::location(session('url.intended'));
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
