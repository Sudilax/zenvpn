<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantAdmin extends Command
{
    protected $signature   = 'admin:grant {email : The email address of the user to promote}';
    protected $description = 'Grant Filament admin panel access to a user by email';

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email: {$email}");
            return self::FAILURE;
        }

        if ($user->is_admin) {
            $this->warn("{$user->name} ({$email}) is already an admin.");
            return self::SUCCESS;
        }

        $user->update(['is_admin' => true]);

        $this->info("✅  {$user->name} ({$email}) is now an admin and can access /admin.");

        return self::SUCCESS;
    }
}
