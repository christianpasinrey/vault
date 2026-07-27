<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Este sistema no se siembra.
     *
     * La unica cuenta se crea con `php artisan vault:init`, que exige completar
     * la master password desde el navegador. Un seeder que creara usuarios
     * dejaria una cuenta con credenciales que nadie controla.
     */
    public function run(): void
    {
        //
    }
}
