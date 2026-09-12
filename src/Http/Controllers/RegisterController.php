<?php

namespace Modules\Auth\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use InertiaUI\Modal\Modal;
use Modules\Auth\Http\Requests\RegisterRequest;
use Saucebase\Core\Helpers\Toast;

class RegisterController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response|Modal
    {
        return $this->pageOrModal('Auth::Register');
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
