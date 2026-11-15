<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Gives an LGU account a new temporary password, for when its password is forgotten. Works on any
 * environment the command can run in (local, Laravel Cloud), since each has its own database.
 * The password is printed once and must be changed at the next sign-in.
 */
class ResetUserPassword extends Command
{
    protected $signature = 'terraspec:reset-password {email : Email address of the account to reset}';

    protected $description = 'Give an LGU account a new temporary password (must be changed at the next sign-in)';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error("No account with the email {$email}.");

            return self::FAILURE;
        }

        $temporary = Str::password(14, symbols: false);

        $user->forceFill([
            'password' => Hash::make($temporary),
            'must_change_password' => true,
            'remember_token' => Str::random(60),
        ])->save();

        // Sign the account out of any browser where it is still logged in (database sessions only)
        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
        }

        $this->info("Password reset for {$user->email} ({$user->role}).");
        $this->line("Temporary password: {$temporary}");
        $this->warn('Shown once. Sign in with it and choose a new password when asked.');

        return self::SUCCESS;
    }
}
