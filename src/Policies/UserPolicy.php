<?php

namespace Modules\Auth\Policies;

use App\Models\User;

/**
 * What staff who manage users may do to an account. `admin` passes every check through
 * the app's `Gate::before`, so these rules only ever bind non-admins: an admin's account
 * is out of their reach.
 */
class UserPolicy
{
    public function update(User $user, User $target): bool
    {
        return ! $target->hasRole('admin');
    }

    public function delete(User $user, User $target): bool
    {
        return ! $target->hasRole('admin');
    }

    /** Signing in as someone is its own permission, and never as an admin. */
    public function impersonate(User $user, User $target): bool
    {
        return $user->can('impersonate users') && ! $target->hasRole('admin');
    }
}
