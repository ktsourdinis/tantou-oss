<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('plan')->default('team')->after('slug');
            $table->string('billing_interval')->default('monthly')->after('plan');
            $table->string('subscription_status')->default('active')->after('billing_interval');
            $table->string('stripe_customer_id')->nullable()->index()->after('subscription_status');
            $table->string('stripe_subscription_id')->nullable()->index()->after('stripe_customer_id');
            $table->timestamp('trial_ends_at')->nullable()->after('stripe_subscription_id');
            $table->timestamp('current_period_ends_at')->nullable()->after('trial_ends_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropIndex(['stripe_customer_id']);
            $table->dropIndex(['stripe_subscription_id']);
            $table->dropColumn([
                'plan',
                'billing_interval',
                'subscription_status',
                'stripe_customer_id',
                'stripe_subscription_id',
                'trial_ends_at',
                'current_period_ends_at',
            ]);
        });
    }
};
