<?php

namespace Modules\Auth\Http\Controllers;

use App\Helpers\Toast;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;
use Modules\Auth\Settings\AuthSettings;
use Modules\Auth\Http\Requests\RegisterRequest;

class RegisterController extends Controller
{
    /**
     * Display the registration view.
     *
     * The URL is the canonical page; a modal is the exception, and only when both
     * the site allows it and the caller explicitly asked. Deciding on the request
     * header rather than the referer matters: the package would otherwise treat
     * any ordinary in-app link as "open me over the previous page".
     */
    public function create(Request $request, AuthSettings $settings): Response|Modal
    {
        if (! $settings->modal_enabled || ! $request->hasHeader(Modal::HEADER_MODAL)) {
            return Inertia::render('Auth::Register');
        }

        return Inertia::modal('Auth::Register', ['modal' => true]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->only(['name', 'email', 'password']);
        $user = User::create($data);

        event(new Registered($user));

        Auth::login($user);

        Toast::default(
            __('auth::auth.welcome', ['name' => $user->name]),
        );

        return redirect()->intended(route('dashboard'));
    }
}
