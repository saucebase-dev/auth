# Saucebase Auth

<div align="center">

[![Tests](https://github.com/saucebase-dev/auth/actions/workflows/test.yml/badge.svg)](https://github.com/saucebase-dev/auth/actions/workflows/test.yml)
[![Release](https://img.shields.io/github/v/release/saucebase-dev/auth)](https://github.com/saucebase-dev/auth/releases)
[![Saucebase Core](https://img.shields.io/packagist/dependency-v/saucebase/auth/saucebase%2Fcore?label=Saucebase%20Core&color=FF6B35)](https://github.com/saucebase-dev/core)
[![License](https://img.shields.io/github/license/saucebase-dev/auth)](LICENSE)

</div>

Sign-in for Saucebase: login, registration, social login, magic links, password reset and user admin.

A module for [Saucebase](https://github.com/saucebase-dev/saucebase), the AI-ready Laravel SaaS kit. Modules are Composer packages you add to a Saucebase app; once installed, the code is yours to change.

## Features

- **Login and registration**, as a page or a modal over the current page
- **Social login** with Google and GitHub built in, and any other Socialite provider you add; users link and unlink accounts themselves
- **Magic links**: sign in from an emailed link, no password
- **Password reset and email verification**
- **Profile and security settings** in the settings modal
- **Login alerts**: an email when someone signs in to the account
- **Impersonation**: admins sign in as a user and switch back
- **User admin** in the admin panel
- **Vue and React**

## Install

**New to Saucebase?** Start a new app and pick **auth** when it asks for modules:

```bash
curl -fsSL https://install.saucebase.dev | bash
```

It installs PHP and Composer if they're missing. With the CLI already installed, run `saucebase new my-app` instead.

**Already have a Saucebase app?** Add it with:

```bash
php artisan saucebase:module-install auth
npm install && npm run build
```

Then make yourself an admin with `php artisan auth:make-admin`.

### Manual install

If a patch doesn't apply, because your file has changed, make its change by hand:

**`user.patch`** adds the `Sociable` trait to your user model, for social login and connected accounts:

```php
// app/Models/User.php
use Modules\Auth\Traits\Sociable;

class User extends Authenticatable
{
    use Sociable;
}
```

## Configuration

Most settings are in the admin panel, under **Settings → Authentication**: open or close registration, login as a modal, magic links and how long they last, login alerts, and which social providers are on.

Social login needs keys from [Google](https://console.cloud.google.com/apis/credentials) or [GitHub](https://github.com/settings/developers). Add them to `.env`:

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=

GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
```

Register `https://<your-app>/auth/socialite/<provider>/callback` as the redirect URL with each provider.

**Another provider?** Any [Laravel Socialite](https://laravel.com/docs/socialite) driver works, including the community ones at [Socialite Providers](https://socialiteproviders.com):

1. Install the driver and add its keys to `.env`.
2. In `modules/auth/config/services.php`, add its credentials next to `google` and `github`, and list it under `socialite_providers`.
3. Add its icon to `providerIcons.ts`.
4. Switch it on in the admin panel.

Emails (reset, verification, magic links, alerts) need a working mail setup.

## Demo data

```bash
php artisan modules:seed --module=auth --demo
```

Adds a user (`chef@saucebase.dev`) and a support staff account (`support@saucebase.dev`) that only sees user admin. Both use the `DEMO_PASSWORD` from `.env`.

## License

MIT. See [LICENSE](LICENSE).
