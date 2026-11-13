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
            $table->foreignId('department_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('departments')
                ->nullOnDelete();
            $table->string('priority')->default('normal')->after('status');
            $table->dropColumn('complexity_score');
            $table->integer('bounty_points')->default(0)->after('weight');
            $table->foreignId('helper_id')
                ->nullable()
                ->after('assignee_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->integer('helper_split')->default(0)->after('helper_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_its', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('helper_id');
            $table->dropColumn([
                'priority',
                'bounty_points',
                'helper_split',
            ]);
            $table->integer('complexity_score')->nullable()->after('weight');
        });
    }
};
