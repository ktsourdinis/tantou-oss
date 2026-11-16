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
        Schema::table('users', function (Blueprint $table) {
            $table->string('facility_name')->nullable()->index()->after('role');
        });

        Schema::table('post_its', function (Blueprint $table) {
            $table->string('facility_name')->nullable()->index()->after('requester_id');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('strict_client_isolation')->default(false)->after('settings');
            $table->boolean('public_submission_enabled')->default(false)->after('strict_client_isolation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['strict_client_isolation', 'public_submission_enabled']);
        });

        Schema::table('post_its', function (Blueprint $table) {
            $table->dropIndex(['facility_name']);
            $table->dropColumn('facility_name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['facility_name']);
            $table->dropColumn('facility_name');
        });
    }
};
