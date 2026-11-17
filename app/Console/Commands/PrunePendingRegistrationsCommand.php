<?php

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
