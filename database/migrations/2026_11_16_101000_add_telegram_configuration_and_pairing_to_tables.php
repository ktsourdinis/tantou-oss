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
            $table->boolean('telegram_enabled')->default(false)->after('timezone');
            $table->text('telegram_bot_token')->nullable()->after('telegram_enabled');
            $table->string('telegram_bot_username')->nullable()->after('telegram_bot_token');
            $table->string('telegram_webhook_secret', 64)->nullable()->after('telegram_bot_username');
            $table->boolean('telegram_privacy_mode')->default(false)->after('telegram_webhook_secret');
            $table->bigInteger('telegram_update_offset')->default(0)->after('telegram_privacy_mode');
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->string('telegram_chat_id')->nullable()->index()->after('color');
            $table->string('telegram_link_token', 64)->nullable()->unique()->after('telegram_chat_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('telegram_chat_id')->nullable()->index()->after('timezone');
            $table->string('telegram_username')->nullable()->after('telegram_chat_id');
            $table->string('telegram_link_token', 64)->nullable()->unique()->after('telegram_username');
            $table->boolean('telegram_notifications_enabled')->default(true)->after('telegram_link_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'telegram_chat_id',
                'telegram_username',
                'telegram_link_token',
                'telegram_notifications_enabled',
            ]);
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->dropColumn([
                'telegram_chat_id',
                'telegram_link_token',
            ]);
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn([
                'telegram_enabled',
                'telegram_bot_token',
                'telegram_bot_username',
                'telegram_webhook_secret',
                'telegram_privacy_mode',
                'telegram_update_offset',
            ]);
        });
    }
};
