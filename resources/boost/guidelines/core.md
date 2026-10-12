## Auth module

`modules/auth` (namespace `Modules\Auth`) owns login, registration, magic link, password reset,
email verification, Socialite OAuth, impersonation, and the Profile and Security settings panels.

- Account settings routes keep their `/settings/*` URLs and `settings.*` names; core resolves them by name.
- `email_verified_at` is never mass-assignable; set it with `forceFill()`.
- Login and register are canonical pages that open as a modal only when the request carries the modal header.

Activate the `saucebase-auth-development` skill before changing this module.
