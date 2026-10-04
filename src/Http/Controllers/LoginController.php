<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Http\Requests\LoginRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LoginController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response|Modal
    {
        return $this->pageOrModal('Auth::Login', [
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): SymfonyResponse
    {
        try {
            $user = $request->validateCredentials();
        } catch (AuthException $e) {
            // A field error, not a flash: in the modal, "back" is the page behind it,
            // where a flashed message is never shown.
            throw ValidationException::withMessages(['email' => $e->getMessage()]);
        }

        $this->signIn($request, $user, $request->boolean('remember'));

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
