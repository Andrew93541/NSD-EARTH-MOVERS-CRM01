<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException(
                'DatabaseSeeder contains development fixtures and demo accounts. It cannot be executed in production.'
            );
        }

        // Run authentic production-ready foundational seeder
        $this->call(ProductionSeeder::class);
    }
}
