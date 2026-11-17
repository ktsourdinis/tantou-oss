<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TelegramBotService;
use App\Services\TelegramUpdateProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TelegramPollCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tantou:telegram-poll
                            {--once : Run a single polling cycle and exit}
                            {--timeout=10 : Polling timeout in seconds for getUpdates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll Telegram Bot API for updates in intranet or firewall environments without inbound webhooks';

    /**
     * Execute the console command.
     */
    public function handle(TelegramBotService $bot, TelegramUpdateProcessor $processor): int
    {
        $once = (bool) $this->option('once');
        $timeout = (int) $this->option('timeout');

        $this->info('Starting Tantou Telegram Poller'.($once ? ' (single cycle)' : ' (continuous)').'...');

        do {
            // 1. Poll global bot if configured
            $globalToken = config('tantou.telegram.bot_token');
            if (filled($globalToken)) {
                $offset = (int) Cache::get('tantou:telegram:global_offset', 0);
                $updates = $bot->getUpdates(null, $offset, 100, $timeout);

                if ($updates && ! empty($updates['result'])) {
                    $this->line('Received '.count($updates['result']).' updates for global bot.');
                    foreach ($updates['result'] as $update) {
                        $processor->processUpdate($update, null);
                        $offset = max($offset, ((int) $update['update_id']) + 1);
                    }
                    Cache::put('tantou:telegram:global_offset', $offset, now()->addDays(30));
                }
            }

            // 2. Poll tenant-specific bots
            $customTenants = Tenant::where('telegram_enabled', true)
                ->whereNotNull('telegram_bot_token')
                ->get();

            foreach ($customTenants as $tenant) {
                $offset = (int) $tenant->telegram_update_offset;
                $updates = $bot->getUpdates($tenant, $offset, 100, $timeout);

                if ($updates && ! empty($updates['result'])) {
                    $this->line('Received '.count($updates['result'])." updates for tenant {$tenant->slug}.");
                    foreach ($updates['result'] as $update) {
                        $processor->processUpdate($update, $tenant);
                        $offset = max($offset, ((int) $update['update_id']) + 1);
                    }
                    $tenant->update(['telegram_update_offset' => $offset]);
                }
            }

            if (! $once) {
                sleep(1);
            }
        } while (! $once);

        $this->info('Telegram polling cycle completed.');

        return self::SUCCESS;
    }
}
