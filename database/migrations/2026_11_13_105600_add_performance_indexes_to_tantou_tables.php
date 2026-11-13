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
        Schema::table('post_its', function (Blueprint $table) {
            $table->index(['tenant_id', 'status']);
            $table->index(['assignee_id', 'status']);
            $table->index(['requester_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['tenant_id', 'board_visibility', 'is_available']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_its', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropIndex(['assignee_id', 'status']);
            $table->dropIndex(['requester_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'board_visibility', 'is_available']);
        });
    }
};
