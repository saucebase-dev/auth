<?php

namespace Modules\Auth\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\text;

class MakeAdminCommand extends Command
{
    protected $signature = 'auth:make-admin
                            {email? : Email address of the user to promote}';

    protected $description = 'Promote an existing user to admin';

    public function handle(): int
    {
        $email = $this->argument('email') ?? text('Email address', required: true);

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email: {$email}");
            $this->line('Register an account first, then re-run this command.');

            return self::FAILURE;
        }

        $user->syncRoles(['admin']);

        $this->info("Promoted admin: {$user->name} <{$user->email}>");

        return self::SUCCESS;
    }
}
