<?php

declare(strict_types=1);

namespace App\Events\PurchaseOrder;

use App\Enums\AuditAction;
use App\Models\PurchaseOrder;
use App\Models\User;

/**
 * A purchase order moved from one state to another.
 *
 * This is an interface rather than a base class on purpose: Laravel's dispatcher
 * resolves listeners through a class's *interfaces*, not its parents, so a
 * listener typed against this contract is called for every transition without
 * anyone having to remember to register the next one.
 */
interface PurchaseOrderLifecycleEvent
{
    public function order(): PurchaseOrder;

    public function actor(): ?User;

    /**
     * How this transition is written into the audit trail.
     */
    public function auditAction(): AuditAction;

    /**
     * A short sentence describing the transition, for the audit payload.
     */
    public function description(): string;
}
