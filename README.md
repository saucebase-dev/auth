# Auth Module

<div align="center">

[![Tests](https://github.com/saucebase-dev/auth/actions/workflows/test.yml/badge.svg)](https://github.com/saucebase-dev/auth/actions/workflows/test.yml)
[![Release](https://img.shields.io/github/v/release/saucebase-dev/auth)](https://github.com/saucebase-dev/auth/releases)
[![Saucebase](https://img.shields.io/badge/Saucebase-1.1+-FF6B35)](https://github.com/saucebase-dev/saucebase)
[![PHP](https://img.shields.io/badge/PHP-8.4+-777BB4?logo=php&logoColor=white)](https://php.net)

Works with:<br/>
[![Vue 3.5](https://img.shields.io/badge/Vue-3.5-4FC08D?logo=vue.js&logoColor=white)](https://vuejs.org) [![React 19](https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black)](https://react.dev)

</div>

Everything your users need to sign in, for [Saucebase](https://github.com/saucebase-dev/saucebase), a Laravel SaaS starter kit.

Login, registration, password reset, email verification, Google and GitHub sign-in, passwordless magic links, and profile settings. Plus user management in the admin.

**[Full documentation →](https://saucebase-dev.github.io/docs/modules/auth)**

## Features

- **Login and registration** — as a full page or a modal over whatever the user was reading
- **Social login** — Google and GitHub, with accounts linked by email
- **Magic links** — sign in from an emailed link, no password needed
- **Password reset** — the standard emailed reset flow
- **Email verification** — optional, with resend
- **Profile settings** — name, email, avatar and password, in the settings modal
- **Connected accounts** — users can link and unlink Google and GitHub themselves
- **Impersonation** — admins can sign in as a user to debug, and switch back
- **Registration switch** — close signups without touching code
- **Rate limiting** — on login, reset and magic link requests
- **Admin panel** — manage users and auth settings at `/admin`
- **Vue and React** — every screen works on both

## Requirements

| | |
| --- | --- |
| Saucebase core | `^1.1` |
| PHP packages | `laravel/socialite`, `stechstudio/filament-impersonate` |
| Mail | A working mail setup, for reset, verification and magic link emails |

For local development, [Mailpit](https://mailpit.axllent.org/) catches the emails. Docker users already have it.

## Installation

```bash
composer require saucebase/auth
php artisan migrate
npm run build
```

### 1. Add the Sociable trait

Required for social login and connected accounts.

```bash
git apply modules/auth/patches/user.patch
```

Or add it yourself in `app/Models/User.php`:

```php
use Modules\Auth\Traits\Sociable;

class User extends Authenticatable
{
    use Sociable;
}
```

### 2. Create your first admin

```bash
php artisan auth:make-admin
```

### 3. Social login (optional)

Skip this if you only want email and password.

Create an app in the [Google Cloud console](https://console.cloud.google.com/apis/credentials) or [GitHub developer settings](https://github.com/settings/developers), then add the keys to `.env`:

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=

GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
```

The redirect URL to register with each provider is:

```
https://your-app.com/auth/socialite/google/callback
https://your-app.com/auth/socialite/github/callback
```

Then turn the provider on at `/admin` → Settings → Authentication. Only providers with keys can be switched on.

### Sample data (optional)

```bash
php artisan modules:seed --module=auth --demo
```

Adds a demo account to sign in with. Do not run this in production.

## Extending

**Listen to events.** Laravel's own auth events all fire as normal (`Registered`, `Login`, `Logout`, `PasswordReset`), plus `ReturningUserAuthenticated` when someone signs in again after time away.

```php
use Illuminate\Auth\Events\Registered;

Event::listen(Registered::class, function (Registered $event) {
    // $event->user
});
```

**Change the screens.** Login, registration, password reset and the settings panels are normal Vue and React pages in `resources/js/`. Edit them like any other page.

**Add a settings panel.** Your own module can add a panel to the settings modal — see the core documentation on settings sections.

## Configuration

The switches are at **`/admin` → Settings → Authentication**: whether registration is open, whether login opens as a modal, whether magic links are on and how long they last, whether users get an email when someone signs in to their account, and which social providers are enabled.

Only your provider keys live in `.env`.

For everything else — customising the emails, the OAuth flow, rate limits — see the [documentation](https://saucebase-dev.github.io/docs/modules/auth).

## License

Proprietary. Part of [Saucebase](https://github.com/saucebase-dev/saucebase).
