<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'visibility')) {
                $table->string('visibility')->default('public')->after('facility_name');
            }
        });

        if (Schema::hasColumn('users', 'board_visibility')) {
            DB::table('users')->whereNull('visibility')->orWhere('visibility', '')->update([
                'visibility' => DB::raw('board_visibility'),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'visibility')) {
                $table->dropColumn('visibility');
            }
        });
    }
};
