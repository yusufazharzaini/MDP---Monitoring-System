<?php

declare(strict_types=1);

namespace App\Events\Delivery;

use App\Enums\AuditAction;
use App\Models\Delivery;
use App\Models\User;

/**
 * A goods receipt was booked, corrected or reversed.
 *
 * An interface for the same reason the purchase order events use one: Laravel
 * resolves listeners through interfaces, so one listener covers every
 * transition and the next one needs no new registration.
 */
interface DeliveryLifecycleEvent
{
    public function delivery(): Delivery;

    public function actor(): ?User;

    public function auditAction(): AuditAction;

    public function description(): string;
}
