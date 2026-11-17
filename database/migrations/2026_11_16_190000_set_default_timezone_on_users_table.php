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
        if (Schema::hasColumn('users', 'timezone')) {
            DB::table('users')->whereNull('timezone')->orWhere('timezone', '')->update(['timezone' => 'Europe/Athens']);

            Schema::table('users', function (Blueprint $table): void {
                $table->string('timezone')->nullable()->default('Europe/Athens')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'timezone')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('timezone')->nullable()->default(null)->change();
            });
        }
    }
};
