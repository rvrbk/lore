<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('lore:make-admin {username : The account to make an admin} {--revoke : Make the account a regular user again}')]
#[Description('Give an account the admin role (subscribed to every monitor), or take it away')]
class MakeAdmin extends Command
{
    public function handle(): int
    {
        $user = User::where('username', $this->argument('username'))->first();
        if ($user === null) {
            $this->error('No account with that username.');

            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            $user->forceFill(['role' => User::ROLE_USER])->save();
            $this->info("{$user->username} is a regular user again. Their monitor subscriptions stay, and they can now change them.");

            return self::SUCCESS;
        }

        $user->makeAdmin();
        $this->info("{$user->username} is an admin and subscribed to every monitor.");

        return self::SUCCESS;
    }
}
