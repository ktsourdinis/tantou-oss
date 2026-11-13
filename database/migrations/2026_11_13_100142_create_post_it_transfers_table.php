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
            $table->foreignId('assigned_by_id')->nullable()->after('assignee_id')->constrained('users')->nullOnDelete();
        });

        Schema::create('post_it_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_it_id')->constrained('post_its')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // 'assigned', 'transferred', 'started', 'completed', 'unassigned'
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_it_transfers');

        Schema::table('post_its', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_by_id');
        });
    }
};
