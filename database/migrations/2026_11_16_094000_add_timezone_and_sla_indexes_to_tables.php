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
            if (! Schema::hasColumn('tenants', 'timezone')) {
                $table->string('timezone')->default('Europe/Athens')->after('public_submission_enabled');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone')->nullable()->after('locale');
            }
        });

        Schema::table('post_its', function (Blueprint $table): void {
            if (! Schema::hasColumn('post_its', 'started_at')) {
                $table->timestamp('started_at')->nullable()->index()->after('bounty_points');
            } else {
                $table->index('started_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_its', function (Blueprint $table): void {
            if (Schema::hasColumn('post_its', 'started_at')) {
                $table->dropIndex(['started_at']);
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'timezone')) {
                $table->dropColumn('timezone');
            }
        });

        Schema::table('tenants', function (Blueprint $table): void {
            if (Schema::hasColumn('tenants', 'timezone')) {
                $table->dropColumn('timezone');
            }
        });
    }
};
