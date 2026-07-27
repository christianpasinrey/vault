<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class VaultInit extends Command
{
    protected $signature = 'vault:init {--email= : Correo con el que iniciaras sesion}';

    protected $description = 'Crea la unica cuenta del sistema y emite un enlace de configuracion de un solo uso';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Correo');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('El correo no es valido.');

            return self::FAILURE;
        }

        $existente = User::first();

        if ($existente && $existente->email !== $email) {
            $this->error("Ya existe una cuenta ({$existente->email}). Este sistema es de usuario unico.");

            return self::FAILURE;
        }

        if ($existente && $existente->auth_hash !== '') {
            $this->error('La cuenta ya esta configurada. Reconfigurarla exigiria destruir la boveda.');

            return self::FAILURE;
        }

        $token = Str::random(64);

        $usuario = $existente ?? new User;
        $usuario->fill([
            'email' => $email,
            'salt' => '',
            'auth_hash' => '',
            'wrapped_vault_key' => '',
            'vault_key_iv' => '',
            // Se guarda el hash, no el token: quien lea la base de datos no
            // puede reutilizar el enlace de configuracion.
            'setup_token' => hash('sha256', $token),
            'setup_token_expires_at' => now()->addMinutes(15),
        ])->save();

        $url = rtrim(config('app.url'), '/')."/setup?token={$token}";

        $this->newLine();
        $this->info('Cuenta preparada. Abre este enlace en el navegador durante los proximos 15 minutos:');
        $this->newLine();
        $this->line($url);
        $this->newLine();
        $this->warn('La master password que elijas no se puede recuperar. Guardala fuera de este sistema.');
        $this->warn('Registra al menos dos passkeys antes de confiarle nada importante.');

        return self::SUCCESS;
    }
}
