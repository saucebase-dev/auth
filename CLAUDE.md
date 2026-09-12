# Auth Module

Authentication, registration, magic link (passwordless), password reset, email verification, OAuth (Socialite), and user impersonation.

## Key Files

| Layer | Files |
|-------|-------|
| Controllers | `LoginController`, `RegisterController`, `SocialiteController`, `ForgotPasswordController`, `ResetPasswordController`, `VerifyEmailController`, `EmailVerificationNotificationController`, `EmailVerificationPromptController`, `PasswordController`, `ProfileController`, `ReimpersonateController`, `MagicLinkController` |
| Models | `SocialAccount` (provider, tokens, avatar, last_login_at), `MagicLinkToken` (hashed token, expires_at, used_at) |
| Service | `SocialiteService` — all OAuth logic (find/create user, link/disconnect accounts) |
| Requests | `LoginRequest` (credential validation + rate limiting), `RegisterRequest` (password hashing in `passedValidation`), `UpdateProfileInfoRequest`, `UpdateProfileAvatarRequest`, `UpdatePasswordRequest` |
| Exceptions | `AuthException` (credentials, throttle), `SocialiteException` (disconnect, account linking, provider validation, registration disabled) |
| Listeners | `AssignUserRole` (Registered), `UpdateUserLastLogin` (Login), `Impersonation` (TakeImpersonation — session history) |
| Notifications | `WelcomeNotification` (Registered), `MagicLinkNotification` (passwordless login link with configured expiry) |
| Settings | `AuthSettings` (`registration_enabled`, `modal_enabled`, `magic_link_enabled`, `magic_link_expiry`, `login_notification_enabled`, `enabled_socialite_providers`) |
| Settings sections | `ProfileSection` (order 10, `Auth::SettingsProfile`), `SecuritySection` (order 15, `Auth::SettingsSecurity`) — panels in core's settings modal |
| Trait | `Sociable` — added to User model (socialAccounts relation, connected_providers, disconnect) |
| Filament | `AuthPlugin`, `AuthenticationSettings`, `UserResource` (list, create, view, edit), `UserForm`, `UsersTable` |
| Pages | `Login`, `Register`, `ForgotPassword`, `ResetPassword`, `VerifyEmail`, `MagicLink`, `SettingsProfile` + `SettingsSecurity` (settings modal panels) |
| Layout | `AuthCardLayout` — card with logo, status alerts, page transitions |
| Component | `SocialiteProviders` — Google/GitHub buttons with divider, `PageHeader` — title with optional back link |

## Frontend

Follows the dual-framework pattern (see root `CLAUDE.md` → Architecture > Frontend).

- Both `resources/js/vue/` and `resources/js/react/` exist and must stay in sync
- `resources/js/app.ts` is a generated re-export — do not edit it directly
- `registerIcon()`, `registerAction()`, `registerGlobalComponent()` calls in `setup()` must be mirrored in both framework implementations

## Routes

**Guest routes** (`/auth/*`): login (GET/POST), register (GET/POST), forgot-password (GET/POST, throttle:6,1), reset-password/{token} (GET, signed), reset-password (POST, throttle:6,1), magic-link (GET/POST, throttle:5,1)

**Auth routes** (`/auth/*`): logout (POST — a GET logout is reachable from any other site's markup), verify-email (GET), verify-email/{id}/{hash} (GET, signed), email/verification-notification (POST, throttle:6,1), password (PUT)

**Socialite** (outside guest/auth groups): `auth.socialite.redirect` (GET), `auth.socialite.callback` (GET), `auth.socialite.disconnect` (DELETE, auth)

**Magic Link** (outside guest/auth groups): `magic-link.authenticate` — `/auth/magic-link/{token}` (GET) — must be accessible from email clients

**Account settings** (`/settings/*`, middleware `auth`, `verified`, `role:admin|user`): `settings.profile` (redirects to the `#settings/profile` fragment), `settings.profile.update-info` (PATCH), `settings.profile.update-avatar` (POST), `settings.profile.delete-avatar` (DELETE), `settings.profile.password.update` (PUT)

The routes are grouped under `settings.profile.*` for history; password and
socialite disconnect are rendered by the **Security** panel, not Profile.

**Impersonation**: `/auth/impersonate/{userId}` (POST, auth)

**API**: `/api/v1/auth/me` (GET, auth:sanctum)

## Patterns

### Changing Your Email Drops Its Verification
`ProfileController::updateInfo()` clears `email_verified_at` when the address actually
changed. `email_verified_at` is deliberately **not** in the User model's `$fillable`, so no
request payload can grant itself a verified address; `SocialiteService` sets it with
`forceFill()`.

Changing a password does **not** yet end other sessions — that needs `AuthenticateSession`
in the `web` group, which is an app-wide change awaiting a regression pass (sc-714).

Note that `User` does not implement `MustVerifyEmail` (the import is commented out), so the
`verified` middleware is currently a pass-through and the verification routes are not
enforced. The timestamp is still kept accurate for when that changes.

### Socialite Dual-Flow
`SocialiteController::callback()` checks `Auth::check()` to branch:
- **Guest**: finds/creates user via `SocialiteService::handleCallback()`, logs in, redirects to intended URL
- **Authenticated**: links account via `SocialiteService::linkAccountToUser()`, redirects to `settings.profile` (this module owns that route)

### Disconnect Validation
Cannot disconnect if it's the user's only login method. `SocialiteService::disconnectProvider()` throws `SocialiteException::cannotDisconnectOnlyMethod()` when `socialAccounts->count() === 1 && !$user->password`.

Also prevents account takeover: linking a social ID already owned by another user throws `accountAlreadyLinked`.

### Rate Limiting
`LoginRequest::ensureIsNotRateLimited()` — 5 attempts per `email|ip` key. Fires `Lockout` event, throws `AuthException::throttle($seconds)`. Cleared on success.

### Impersonation
Uses `lab404/laravel-impersonate` + `filament-impersonate`. Session stores history at `impersonation.recent_history` (max 4 user IDs). `ReimpersonateController` lets admins re-impersonate from recent list (max 3 shown in UI, filters deleted users and self). Stop via `filament-impersonate.leave` route.

### Magic Link Flow
`MagicLinkController::store()` silently finds the user by email (no error on unknown email). If found: deletes existing tokens for that user, creates a new `MagicLinkToken` (token = SHA-256 hash of `Str::random(64)`, expiry controlled by `AuthSettings`), and sends `MagicLinkNotification` with the plain-token URL.

`MagicLinkController::authenticate()` hashes the incoming token, looks it up, calls `isValid()` (not expired + not used), logs in the user, marks the token used, and redirects to intended URL or dashboard.

`AuthSettings` is auto-discovered from `src/Settings`, with defaults installed
from `database/settings`. Magic links are disabled by default, with a 15-minute
expiry once switched on. Administrators manage both values through the `AuthenticationSettings`
Filament page under the Settings navigation group.

**Token storage:** Plain token only lives in the email link. DB stores `hash('sha256', $plainToken)`. This means even if the DB is compromised, tokens cannot be forged or replayed.

### Account Settings Routes Keep the `settings.*` Names
Profile management moved here from the retired `settings` module. The routes deliberately
keep the `/settings/*` URLs and `settings.*` names, because app core resolves them by name,
and `PasswordChangedNotification` links to `route('settings.profile')`. That route now
redirects into the `#settings/profile` fragment rather than rendering a page.

The settings *shell* is core's modal (`pages/Settings`, `useSettingsModal`); this module
only contributes `ProfileSection` and `SecuritySection`. Core discovers them from
`src/Settings`, so `NavUser` no longer guards on `route().has('settings.profile')` — it
guards on whether any section was contributed at all.

### Profile Is Identity, Security Is Credentials
`SettingsProfile` holds name, email, and avatar. `SettingsSecurity` holds the password form
and connected social accounts. The split is by the question being asked, not by ownership:
both sections live here, and `SecuritySection` sits at order 15 so it follows Profile (10)
ahead of anything another module contributes.

### Login and Register Open in a Modal Only When Asked
Both URLs stay canonical pages. `LoginController::create()` and `RegisterController::create()`
return `Inertia::render()` unless **both** `AuthSettings::$modal_enabled` is on and the request
carries `Modal::HEADER_MODAL`; only then do they return `Inertia::modal()` with a `modal: true`
prop. There are no modal-only components — the page components branch on that prop, and the
form closes the modal through `useModal()` on success.

Branching on the header explicitly matters: `Modal::toResponse()` falls back to the **referer**
when the header is absent, which would turn any ordinary in-app link to `/auth/login` into a
modal over the previous page.

`AuthServiceProvider` shares the setting as `auth.modal_enabled`, and core's `Header`
(Vue + React) reads it to swap `Link` for `ModalLink` on the navbar Sign In and Get Started
entries — `ModalLink` is what sends the header. Every other link to those URLs stays a page.

`PasswordController::update()` is shared by two routes: `password.update` (`PUT /auth/password`)
and `settings.profile.password.update`. It redirects to `settings.profile` on success.

### Logout Action Handler
`app.ts` registers the `logout`, `settings`, and `profile` icons plus the logout action handler via `registerIcon()` and `registerAction()` from `@/lib/navigation`. The action shows a confirmation dialog and posts to `route('logout')`.

## ENV Variables

```
GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_CLIENT_REDIRECT_URI
GITHUB_CLIENT_ID, GITHUB_CLIENT_SECRET, GITHUB_CLIENT_REDIRECT_URI
```

Redirect URIs default to `/auth/socialite/{provider}/callback`. Providers configured in `config/services.php`.

## Testing

```bash
php artisan test --testsuite=Modules --filter='^Modules\\Auth\\Tests'  # PHPUnit
npx playwright test --project="@auth*"                 # E2E
```

**E2E coverage**: login (basic, errors, security/rate-limiting, social, modal, logout), register (basic, errors), forgot-password (basic, errors), verify-email, profile (avatar, socialite settings under the Security panel). Page objects in `tests/e2e/pages/`, fixtures in `tests/e2e/fixtures/users.ts`.

## Gotchas

- `LoginRequest::validateCredentials()` validates without logging in — the controller handles `Auth::login()` separately
- Social users get a random password and are **not** marked verified — signing in with a provider is not proof of the address, and nobody has confirmed it (sc-713)
- Socialite redirect/callback routes are outside both guest and auth middleware groups
- `RegisterRequest::passedValidation()` hashes the password before the controller sees it
- Filament UserResource enforces single role (maxItems: 1) despite multi-select UI
- Magic link authenticate route is outside both guest and auth middleware groups (link is clicked from email client)
- `MagicLinkToken::isValid()` checks both `expires_at->isFuture()` and `used_at === null`
- `modal_enabled` is presentation only. Turning it off does not change what the routes do, only how they are reached — the canonical URLs work either way, which is why there is no separate modal route.
- `registration_enabled` closes both signup paths: `EnsureRegistrationEnabled` 404s the register routes, and `SocialiteService::handleCallback()` throws `registrationDisabled()` rather than creating a new user (existing users still sign in). Login is deliberately not toggleable — disabling it would lock out admins.
