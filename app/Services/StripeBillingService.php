<?php

namespace App\Services;

use App\Models\Tenant;
use App\Support\Edition;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class StripeBillingService
{
    /**
     * Create a Stripe Checkout Session for subscription upgrade or renewal.
     * In local/testing mode without Stripe secret, performs simulated activation.
     */
    public function createCheckoutSession(Tenant $tenant, string $plan, string $interval = 'monthly'): string
    {
        $plan = in_array($plan, ['team', 'business'], true) ? $plan : 'team';
        $interval = in_array($interval, ['monthly', 'yearly'], true) ? $interval : 'monthly';
        $secret = (string) config('tantou.stripe.secret');

        // Dev / Sandbox Fallback when STRIPE_SECRET is empty
        if (blank($secret)) {
            $tenant->update([
                'plan' => $plan,
                'billing_interval' => $interval,
                'subscription_status' => 'active',
                'current_period_ends_at' => $interval === 'yearly' ? now()->addYear() : now()->addMonth(),
            ]);

            session()->flash('status', '⚡ [Dev Mode] Simulated Stripe activation for Cloud '.ucfirst($plan).' plan!');

            return Edition::workspaceUrl($tenant, '/board', request());
        }

        $priceId = (string) config("tantou.stripe.prices.{$plan}.{$interval}");
        if (blank($priceId)) {
            $priceId = $plan === 'business' ? 'price_business_'.$interval : 'price_team_'.$interval;
        }

        $boardUrl = Edition::workspaceUrl($tenant, '/board', request());
        $successUrl = Edition::workspaceUrl($tenant, '/board?session_id={CHECKOUT_SESSION_ID}', request());
        $cancelUrl = Edition::workspaceUrl($tenant, '/board?canceled=1', request());

        $payload = [
            'mode' => 'subscription',
            'client_reference_id' => (string) $tenant->id,
            'line_items[0][price]' => $priceId,
            'line_items[0][quantity]' => 1,
            'tax_id_collection[enabled]' => 'true',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata[tenant_id]' => (string) $tenant->id,
            'metadata[plan]' => $plan,
            'metadata[interval]' => $interval,
        ];

        if (filled($tenant->stripe_customer_id)) {
            $payload['customer'] = $tenant->stripe_customer_id;
        } else {
            $adminUser = $tenant->users()->where('role', 'admin')->first();
            if ($adminUser && filled($adminUser->email)) {
                $payload['customer_email'] = $adminUser->email;
            }
        }

        try {
            $response = Http::withToken($secret)
                ->asForm()
                ->post('https://api.stripe.com/v1/checkout/sessions', $payload);

            if ($response->successful()) {
                return (string) ($response->json('url') ?? $boardUrl);
            }

            Log::error('Stripe Checkout Session creation failed', [
                'tenant_id' => $tenant->id,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            session()->flash('error', __('Failed to initialize Stripe checkout. Please try again.'));

            return $boardUrl;
        } catch (Throwable $e) {
            Log::error('Stripe Checkout Session exception: '.$e->getMessage(), [
                'tenant_id' => $tenant->id,
            ]);

            session()->flash('error', __('Payment gateway unavailable.'));

            return $boardUrl;
        }
    }

    /**
     * Create a Stripe Customer Billing Portal session for managing invoices and cards.
     */
    public function createBillingPortalSession(Tenant $tenant): string
    {
        $secret = (string) config('tantou.stripe.secret');
        $boardUrl = Edition::workspaceUrl($tenant, '/board', request());

        if (blank($secret) || blank($tenant->stripe_customer_id)) {
            session()->flash('status', '⚡ [Dev Mode] Simulated Stripe Customer Portal access.');

            return $boardUrl;
        }

        try {
            $response = Http::withToken($secret)
                ->asForm()
                ->post('https://api.stripe.com/v1/billing_portal/sessions', [
                    'customer' => $tenant->stripe_customer_id,
                    'return_url' => $boardUrl,
                ]);

            if ($response->successful()) {
                return (string) ($response->json('url') ?? $boardUrl);
            }

            Log::error('Stripe Billing Portal creation failed', [
                'tenant_id' => $tenant->id,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            session()->flash('error', __('Failed to open billing portal.'));

            return $boardUrl;
        } catch (Throwable $e) {
            Log::error('Stripe Billing Portal exception: '.$e->getMessage());

            session()->flash('error', __('Billing portal unavailable.'));

            return $boardUrl;
        }
    }
}
