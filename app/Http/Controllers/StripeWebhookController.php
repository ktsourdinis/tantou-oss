<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Support\Edition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController
{
    /**
     * Handle incoming Stripe webhook notifications.
     */
    public function __invoke(Request $request): JsonResponse
    {
        if (! Edition::isCloud()) {
            return response()->json(['error' => 'Stripe webhook is disabled in Community edition.'], 404);
        }

        $rawPayload = (string) $request->getContent();
        $sigHeader = (string) $request->header('Stripe-Signature', '');
        $webhookSecret = (string) config('tantou.stripe.webhook_secret');

        // 1. Signature Verification (when webhook secret is configured)
        if (filled($webhookSecret)) {
            if (blank($sigHeader)) {
                return response()->json(['error' => 'Missing Stripe-Signature header'], 400);
            }

            if (! $this->verifySignature($rawPayload, $sigHeader, $webhookSecret)) {
                return response()->json(['error' => 'Invalid webhook signature'], 400);
            }
        }

        $payload = json_decode($rawPayload, true);
        if (! is_array($payload) || ! isset($payload['type'])) {
            return response()->json(['error' => 'Invalid JSON payload'], 400);
        }

        $eventType = (string) $payload['type'];
        $dataObject = (array) ($payload['data']['object'] ?? []);

        Log::info("Processing Stripe Webhook: {$eventType}");

        match ($eventType) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($dataObject),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($dataObject),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($dataObject),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    /**
     * Verify Stripe HMAC SHA-256 signature against timestamp and payload.
     */
    protected function verifySignature(string $payload, string $sigHeader, string $secret): bool
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $sigHeader) as $part) {
            $item = explode('=', trim($part), 2);
            if (count($item) === 2) {
                if ($item[0] === 't') {
                    $timestamp = $item[1];
                } elseif ($item[0] === 'v1') {
                    $signatures[] = $item[1];
                }
            }
        }

        if (! $timestamp || empty($signatures)) {
            return false;
        }

        // Optional: Reject signatures older than 10 minutes to protect against replay attacks
        if (abs(time() - (int) $timestamp) > 600) {
            return false;
        }

        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expectedSignature, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle checkout.session.completed: activate tenant plan and assign Stripe IDs.
     *
     * @param  array<string, mixed>  $session
     */
    protected function handleCheckoutSessionCompleted(array $session): void
    {
        $tenantId = (int) ($session['client_reference_id'] ?? $session['metadata']['tenant_id'] ?? 0);
        $customerId = (string) ($session['customer'] ?? '');
        $subscriptionId = (string) ($session['subscription'] ?? '');
        $plan = (string) ($session['metadata']['plan'] ?? '');
        $interval = (string) ($session['metadata']['interval'] ?? 'monthly');

        $tenant = null;
        if ($tenantId > 0) {
            $tenant = Tenant::find($tenantId);
        }

        if (! $tenant && filled($customerId)) {
            $tenant = Tenant::where('stripe_customer_id', $customerId)->first();
        }

        if (! $tenant) {
            Log::warning('Stripe webhook checkout.session.completed: Tenant not found', [
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
            ]);

            return;
        }

        $updateData = [
            'subscription_status' => 'active',
            'billing_interval' => in_array($interval, ['monthly', 'yearly'], true) ? $interval : 'monthly',
            'current_period_ends_at' => $interval === 'yearly' ? now()->addYear() : now()->addMonth(),
        ];

        if (in_array($plan, ['team', 'business'], true)) {
            $updateData['plan'] = $plan;
        }

        if (filled($customerId)) {
            $updateData['stripe_customer_id'] = $customerId;
        }

        if (filled($subscriptionId)) {
            $updateData['stripe_subscription_id'] = $subscriptionId;
        }

        $tenant->update($updateData);

        Log::info("Tenant #{$tenant->id} ({$tenant->slug}) activated plan {$tenant->plan} via Stripe Checkout.");
    }

    /**
     * Handle customer.subscription.updated: sync status and period end.
     *
     * @param  array<string, mixed>  $subscription
     */
    protected function handleSubscriptionUpdated(array $subscription): void
    {
        $subId = (string) ($subscription['id'] ?? '');
        $customerId = (string) ($subscription['customer'] ?? '');
        $status = (string) ($subscription['status'] ?? 'active');

        $tenant = Tenant::where('stripe_subscription_id', $subId)
            ->orWhere('stripe_customer_id', $customerId)
            ->first();

        if (! $tenant) {
            return;
        }

        $updateData = [
            'subscription_status' => $status,
        ];

        if (isset($subscription['current_period_end']) && is_numeric($subscription['current_period_end'])) {
            $updateData['current_period_ends_at'] = date('Y-m-d H:i:s', (int) $subscription['current_period_end']);
        }

        // Check if plan or interval changed from price ID
        $priceId = (string) ($subscription['items']['data'][0]['price']['id'] ?? '');
        if (filled($priceId)) {
            if (str_contains($priceId, 'business')) {
                $updateData['plan'] = 'business';
            } elseif (str_contains($priceId, 'team')) {
                $updateData['plan'] = 'team';
            }

            if (str_contains($priceId, 'yearly')) {
                $updateData['billing_interval'] = 'yearly';
            } elseif (str_contains($priceId, 'monthly')) {
                $updateData['billing_interval'] = 'monthly';
            }
        }

        $tenant->update($updateData);
    }

    /**
     * Handle customer.subscription.deleted: mark status as canceled.
     *
     * @param  array<string, mixed>  $subscription
     */
    protected function handleSubscriptionDeleted(array $subscription): void
    {
        $subId = (string) ($subscription['id'] ?? '');
        $customerId = (string) ($subscription['customer'] ?? '');

        $tenant = Tenant::where('stripe_subscription_id', $subId)
            ->orWhere('stripe_customer_id', $customerId)
            ->first();

        if ($tenant) {
            $tenant->update(['subscription_status' => 'canceled']);
            Log::info("Tenant #{$tenant->id} ({$tenant->slug}) subscription marked as canceled.");
        }
    }
}
