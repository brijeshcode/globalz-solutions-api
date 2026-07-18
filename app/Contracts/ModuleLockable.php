<?php

namespace App\Contracts;

use Carbon\CarbonInterface;

interface ModuleLockable
{
    /**
     * Settings key inside the module_locks group (e.g. 'sale', 'sale_order').
     */
    public function moduleLockKey(): string;

    /**
     * Document date the lock age is measured against.
     */
    public function moduleLockDate(): ?CarbonInterface;

    /**
     * In-flight documents (undelivered, unpaid, unreceived) are never locked.
     */
    public function isModuleLockExempt(): bool;
}
