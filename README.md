# Auth Module

Authentication module for [Sauce Base](https://github.com/sauce-base/saucebase) with social login support.

## Installation

Install via Composer:

```bash
composer require saucebase/auth
docker compose exec workspace php artisan module:enable Auth
docker compose exec workspace php artisan module:migrate Auth --seed
npm run build

```

## Features

- **User Authentication** — Standard login, registration, and password reset flows
- **Social Login** — OAuth integration via Laravel Socialite (Google, GitHub, etc.)
- **Social Account Linking** — Connects multiple OAuth providers to a single user account
- **Filament Integration** — Admin panel components for user management
- **Vue 3 Frontend** — Pre-built authentication pages with Inertia.js

## Configuration

### Social Login Setup

1. Configure OAuth providers in `.env`:

```env
GOOGLE_CLIENT_ID=your-client-id
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_CLIENT_REDIRECT_URI=/auth/socialite/google/callback

GITHUB_CLIENT_ID=your-client-id
GITHUB_CLIENT_SECRET=your-client-secret
GITHUB_CLIENT_REDIRECT_URI=/auth/socialite/github/callback
```

2. Add the `useSocialite` trait to your User model:

```php
use Modules\Auth\Traits\useSocialite;

class User extends Authenticatable
{
    use useSocialite;

    // ... rest of your model
}
```

The trait provides:

- `socialAccounts()` — HasMany relationship to SocialAccount model
- `getConnectedProvidersAttribute()` — Get list of connected OAuth providers
- `disconnectSocialProvider(string $provider)` — Disconnect a social account
- `getLatestProviderAvatarUrlAttribute()` — Get the most recent provider avatar URL

## Usage

### Authentication Routes

- `/auth/login` — Login page
- `/auth/register` — Registration page
- `/auth/forgot-password` — Password reset request
- `/auth/socialite/{provider}` — OAuth redirect (google, github, etc.)
- `/auth/socialite/{provider}/callback` — OAuth callback

### Service Usage

Handle OAuth callback:

```php
use Modules\Auth\Services\SocialiteService;

$user = app(SocialiteService::class)->handleCallback('google');
```

Disconnect a provider:

```php
$user->disconnectSocialProvider('google');
```

Get connected providers:

```php
$providers = $user->connected_providers;
// Returns: [['provider' => 'google', 'last_login_at' => '...', 'provider_avatar_url' => '...']]
```
