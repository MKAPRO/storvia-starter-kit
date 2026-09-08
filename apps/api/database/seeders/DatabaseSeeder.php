<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use LogicException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed STORVIA's safe, non-secret baseline data.
     */
    public function run(): void
    {
        $this->call(AccessControlSeeder::class);

        if (! (bool) config('storvia.starter_demo.enabled', false)) {
            return;
        }

        if (app()->environment('production')) {
            throw new LogicException('Starter demo seeding cannot run in production.');
        }

        $this->call(StarterDemoSeeder::class);
    }
}
