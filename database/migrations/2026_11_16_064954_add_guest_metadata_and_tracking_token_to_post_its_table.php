<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update post_its table: add guest metadata and tracking token, make requester_id nullable
        Schema::table('post_its', function (Blueprint $table) {
            $table->string('tracking_token', 32)->nullable()->unique()->after('id');
            $table->string('guest_name')->nullable()->after('facility_name');
            $table->string('guest_contact')->nullable()->after('guest_name');
        });

        // Drop foreign key on requester_id to change nullability safely
        Schema::table('post_its', function (Blueprint $table) {
            $table->dropForeign(['requester_id']);
        });

        Schema::table('post_its', function (Blueprint $table) {
            $table->foreignId('requester_id')->nullable()->change();
            $table->foreign('requester_id')->references('id')->on('users')->nullOnDelete();
        });

        if (Schema::hasColumn('post_its', 'created_by')) {
            Schema::table('post_its', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->change();
            });
        }

        // Backfill existing post_its with unique tracking tokens
        $existingPostIts = DB::table('post_its')->whereNull('tracking_token')->get(['id']);
        foreach ($existingPostIts as $postIt) {
            $token = 'TNT-'.strtoupper(Str::random(8));
            while (DB::table('post_its')->where('tracking_token', $token)->exists()) {
                $token = 'TNT-'.strtoupper(Str::random(8));
            }
            DB::table('post_its')->where('id', $postIt->id)->update(['tracking_token' => $token]);
        }

        // 2. Allow guest comments on tracking page
        Schema::table('post_it_comments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('post_it_comments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('guest_name')->nullable()->after('user_id');
        });

        // 3. Allow guest attachments
        Schema::table('post_it_attachments', function (Blueprint $table) {
            $table->dropForeign(['uploader_id']);
        });

        Schema::table('post_it_attachments', function (Blueprint $table) {
            $table->foreignId('uploader_id')->nullable()->change();
            $table->foreign('uploader_id')->references('id')->on('users')->nullOnDelete();
        });

        // 4. Allow guest transfers/workflow entries
        Schema::table('post_it_transfers', function (Blueprint $table) {
            $table->dropForeign(['actor_id']);
        });

        Schema::table('post_it_transfers', function (Blueprint $table) {
            $table->foreignId('actor_id')->nullable()->change();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_it_transfers', function (Blueprint $table) {
            $table->dropForeign(['actor_id']);
        });

        Schema::table('post_it_transfers', function (Blueprint $table) {
            $table->foreignId('actor_id')->change();
            $table->foreign('actor_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('post_it_attachments', function (Blueprint $table) {
            $table->dropForeign(['uploader_id']);
        });

        Schema::table('post_it_attachments', function (Blueprint $table) {
            $table->foreignId('uploader_id')->change();
            $table->foreign('uploader_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('post_it_comments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('guest_name');
        });

        Schema::table('post_it_comments', function (Blueprint $table) {
            $table->foreignId('user_id')->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('post_its', function (Blueprint $table) {
            $table->dropForeign(['requester_id']);
        });

        Schema::table('post_its', function (Blueprint $table) {
            $table->foreignId('requester_id')->change();
            $table->foreign('requester_id')->references('id')->on('users')->cascadeOnDelete();
            $table->dropColumn(['tracking_token', 'guest_name', 'guest_contact']);
        });
    }
};
