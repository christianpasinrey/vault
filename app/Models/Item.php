<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un secreto de la boveda.
 *
 * Este modelo no sabe que guarda. El ciphertext contiene un JSON cifrado en el
 * navegador con la Vault Key, que el servidor jamas posee.
 */
#[Fillable(['id', 'user_id', 'ciphertext', 'iv', 'version'])]
class Item extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
