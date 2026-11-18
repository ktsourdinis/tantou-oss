<?php

/**
 * Tantou (担当) — Human Capacity OS
 *
 * @author    Konstantinos Tsourdinis / HELLENIC WEB3 STUDIO
 * @copyright 2026 Konstantinos Tsourdinis / HELLENIC WEB3 STUDIO
 * @license   AGPL-3.0-or-later OR LicenseRef-Tantou-Commercial
 *
 * @link      https://github.com/ktsourdinis/tantou-oss
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later OR LicenseRef-Tantou-Commercial
 */

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeSuperadminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tantou:make-superadmin {email : The email address of the user} {--revoke : Revoke superadmin privileges}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Grant or revoke platform superadmin privileges for a user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $revoke = (bool) $this->option('revoke');

        if (! $revoke && (str_ends_with($email, '@tantou.local') || str_ends_with($email, '@hellenic-demo.gr'))) {
            $this->error("Demo accounts ([{$email}]) can never be granted platform superadmin privileges.");

            return self::FAILURE;
        }

        $users = User::withoutGlobalScopes()->whereRaw('LOWER(email) = ?', [$email])->get();

        if ($users->isEmpty()) {
            $this->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        foreach ($users as $user) {
            $user->is_superadmin = ! $revoke;
            $user->save();

            if ($revoke) {
                $this->info("Revoked superadmin privileges from [{$user->name}] ({$user->email}).");
            } else {
                $this->info("Successfully granted superadmin privileges to [{$user->name}] ({$user->email}).");
            }
        }

        return self::SUCCESS;
    }
}
