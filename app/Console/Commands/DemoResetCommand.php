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

use Illuminate\Console\Command;

class DemoResetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tantou:demo-reset {--force : Force reset even when TANTOU_DEMO_MODE is false}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely reset and re-seed the public demo workspace back to its clean showcase state when TANTOU_DEMO_MODE is enabled.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('app.demo_mode', false) && ! $this->option('force')) {
            $this->warn('Demo reset skipped: TANTOU_DEMO_MODE is not enabled (use --force to override).');

            return self::FAILURE;
        }

        return $this->call('tantou:reset-demo');
    }
}
