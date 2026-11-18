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

use App\Models\PendingRegistration;
use Illuminate\Console\Command;

class PrunePendingRegistrationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tantou:prune-pending-registrations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune expired pending registration magic link records from the database.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deleted = PendingRegistration::where('expires_at', '<=', now())->delete();

        $this->info("Pruned {$deleted} expired pending registration(s).");

        return self::SUCCESS;
    }
}
