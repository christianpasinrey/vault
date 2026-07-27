<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * This system is never seeded.
     *
     * The single account is created with `php artisan vault:init`, which forces
     * you to choose the master password in the browser. A seeder that created
     * users would leave behind an account whose credentials nobody controls.
     */
    public function run(): void
    {
        //
    }
}
