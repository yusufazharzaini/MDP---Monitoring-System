<?php

declare(strict_types=1);

namespace App\Events\Problem;

use App\Enums\AuditAction;
use App\Models\DeliveryProblem;
use App\Models\User;

/**
 * A delivery problem was reported, revised, closed or cancelled.
 *
 * An interface for the same reason the purchase order and delivery events use
 * one: Laravel resolves listeners through interfaces, so a single listener
 * covers every transition and the next one needs no new registration.
 */
interface ProblemLifecycleEvent
{
    public function problem(): DeliveryProblem;

    public function actor(): ?User;

    public function auditAction(): AuditAction;

    public function description(): string;
}
