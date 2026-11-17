<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\TelegramUpdateProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    /**
     * Handle incoming webhook updates from Telegram Bot API.
     */
    public function __invoke(Request $request, TelegramUpdateProcessor $processor, ?string $tenantSlug = null): JsonResponse
    {
        $botTenant = null;
        if ($tenantSlug !== null) {
            $botTenant = Tenant::where('slug', $tenantSlug)->firstOrFail();
        }

        // Verify Secret Token if configured
        $secretHeader = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        $expectedSecret = $botTenant?->telegram_webhook_secret ?: config('tantou.telegram.webhook_secret');

        if (filled($expectedSecret) && ! hash_equals((string) $expectedSecret, $secretHeader)) {
            abort(403, 'Invalid Telegram secret token.');
        }

        $payload = $request->all();
        if (! empty($payload) && is_array($payload)) {
            $processor->processUpdate($payload, $botTenant);
        }

        return response()->json(['ok' => true]);
    }
}
