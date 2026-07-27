<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable([
    'email', 'salt', 'kdf_algo', 'kdf_iterations', 'auth_hash',
    'wrapped_vault_key', 'vault_key_iv', 'auto_lock_seconds',
    'setup_token', 'setup_token_expires_at',
])]
// El auth_hash y el setup_token no deben aparecer en ninguna respuesta,
// ni siquiera por accidente al serializar el modelo.
#[Hidden(['auth_hash', 'remember_token', 'setup_token'])]
class User extends Authenticatable
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kdf_iterations' => 'integer',
            'auto_lock_seconds' => 'integer',
            'setup_token_expires_at' => 'datetime',
        ];
    }

    /** @return HasMany<WebauthnCredential, $this> */
    public function webauthnCredentials(): HasMany
    {
        return $this->hasMany(WebauthnCredential::class);
    }

    /** @return HasMany<Item, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
