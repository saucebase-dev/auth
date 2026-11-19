<?php

namespace Modules\Auth\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use InertiaUI\Modal\Modal;
use Modules\Auth\Http\Requests\RegisterRequest;
use Saucebase\Core\Facades\Home;
use Saucebase\Core\RegistrationInvite;
use Saucebase\Core\Toast;

class RegisterController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response|Modal
    {
        $invited = RegistrationInvite::email();

        return $this->pageOrModal('Auth::Register', [
            'by_invitation' => $invited === null ? null : ['email' => $invited],
        ]);
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

        // The invitation reached this inbox: the address is proven, and the invite is used up.
        if (RegistrationInvite::allows($user->email)) {
            $user->forceFill(['email_verified_at' => now()])->save();
            RegistrationInvite::forget();
        }

        event(new Registered($user));

        Auth::login($user);

        Toast::default(
            __('auth::auth.welcome', ['name' => $user->name]),
        );

        return redirect()->intended(Home::url($request, Home::REGISTERED));
    }
}
