<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        DB::table('tenants')
            ->whereIn('plan', ['team', 'cloud_team', 'business', 'cloud_business', 'msp'])
            ->update(['plan' => 'cloud_managed']);

        $masterSlug = (string) (config('tantou-cloud.superadmin_slug') ?: config('tantou.superadmin_slug') ?: 'tantou-team');

        DB::table('tenants')
            ->where('slug', $masterSlug)
            ->update([
                'plan' => 'enterprise',
                'subscription_status' => 'active',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
