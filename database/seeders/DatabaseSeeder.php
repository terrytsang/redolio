<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(DemoFixturesSeeder::class);
        $this->call(DesignDirectionSeeder::class);
        $this->call(CatalogueSeeder::class);
        $this->call(PlanSeeder::class);
        $this->call(PlatformSettingsSeeder::class);
        $this->call(AdminUserSeeder::class);
    }
}
