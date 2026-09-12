<?php

namespace Modules\Auth\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use InertiaUI\Modal\Modal;
use Modules\Auth\Events\ReturningUserAuthenticated;
use Modules\Auth\Http\Middleware\EnsureMagicLinkEnabled;
use Modules\Auth\Models\MagicLinkToken;
use Modules\Auth\Notifications\MagicLinkNotification;
use Modules\Auth\Settings\AuthSettings;
use Saucebase\Core\Helpers\Toast;

class MagicLinkController extends Controller implements HasMiddleware
{
    public function __construct(private readonly AuthSettings $settings) {}

    public static function middleware(): array
    {
        return [
            new Middleware(EnsureMagicLinkEnabled::class),
        ];
    }

    /**
     * Display the magic link request form.
     */
    public function create(): Response|Modal
    {
        return $this->pageOrModal('Auth::MagicLink', [
            'status' => session('status'),
        ]);
    }

    /**
     * Send a magic link to the given email address.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            $plainToken = Str::random(64);

            MagicLinkToken::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'token' => hash('sha256', $plainToken),
                    'expires_at' => now()->addMinutes($this->settings->magic_link_expiry),
                    'used_at' => null,
                ]
            );

            $url = route('magic-link.authenticate', ['token' => $plainToken]);

            if ($request->session()->has('url.intended')) {
                $url .= '?intended='.urlencode($request->session()->get('url.intended'));
            }

            $user->notify(new MagicLinkNotification(
                url: $url,
                expiry: $this->settings->magic_link_expiry,
            ));
        }

        return back()->with('status', __('auth::auth.magic-link-sent'));
    }

    /**
     * Authenticate the user via a magic link token.
     */
    public function authenticate(Request $request, string $token): \Symfony\Component\HttpFoundation\Response
    {
        $hashed = hash('sha256', $token);

        $now = now();

        $consumed = MagicLinkToken::where('token', $hashed)
            ->whereNull('used_at')
            ->where('expires_at', '>', $now)
            ->update(['used_at' => $now]);

        if (! $consumed) {
            return redirect()->route('login')->with('error', __('auth::auth.magic-link-expired'));
        }

        $record = MagicLinkToken::where('token', $hashed)->with('user')->first();
        $user = $record?->user;

        if ($user === null) {
            return redirect()->route('login')->with('error', __('auth::auth.magic-link-expired'));
        }

        Auth::login($user);

        $request->session()->regenerate();

        ReturningUserAuthenticated::dispatch(
            $user,
            now(),
            $request->ip(),
            $request->userAgent(),
        );

        Toast::default(__('auth::auth.welcome-back', ['name' => $user->name]));

        $intended = $request->query('intended');

        // Host check prevents open redirect — mismatches silently fall through to dashboard.
        if ($intended) {
            $decoded = urldecode($intended);
            if (parse_url($decoded, PHP_URL_HOST) === $request->getHost()) {
                return Inertia::location($decoded);
            }
        }

        return redirect()->intended(route('dashboard'));
    }
}
