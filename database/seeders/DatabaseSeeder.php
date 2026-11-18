<?php

namespace Database\Seeders;

use App\Console\Commands\ResetHellenicDemoCommand;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if ($this->command) {
            $this->command->call('tantou:reset-demo');
        } else {
            Artisan::call('tantou:reset-demo');
        }

        ResetHellenicDemoCommand::provisionMasterSuperadmin();
    }
}
