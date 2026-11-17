<?php

use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook/{tenantSlug?}', TelegramWebhookController::class)
    ->name('api.telegram.webhook');

Route::post('/stripe/webhook', StripeWebhookController::class)
    ->name('api.stripe.webhook');
