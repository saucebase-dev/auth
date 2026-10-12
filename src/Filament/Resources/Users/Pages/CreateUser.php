<?php

namespace Modules\Auth\Filament\Resources\Users\Pages;

use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Modules\Auth\Filament\Resources\Users\UserResource;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** Staff who cannot assign roles still create an ordinary account. */
    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->record;

        if ($user->roles()->doesntExist()) {
            $user->assignRole('user');
        }
    }
}
