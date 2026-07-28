<?php

namespace App\Console\Commands;

use App\Models\Item;
use Illuminate\Console\Command;

class VaultPurge extends Command
{
    protected $signature = 'vault:purge';

    protected $description = 'Destroy for good the items that have been in the trash longer than the grace period';

    /** How long a deleted item stays recoverable. */
    public const GRACE_DAYS = 30;

    public function handle(): int
    {
        $destroyed = Item::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays(self::GRACE_DAYS))
            ->forceDelete();

        $this->info("Destroyed {$destroyed} items that had been in the trash for more than ".self::GRACE_DAYS.' days.');

        return self::SUCCESS;
    }
}
