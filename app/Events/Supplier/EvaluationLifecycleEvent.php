<?php

declare(strict_types=1);

namespace App\Events\Supplier;

use App\Enums\AuditAction;
use App\Models\SupplierEvaluation;
use App\Models\User;

/**
 * A monthly scorecard was signed off or reopened.
 *
 * An interface, as elsewhere in this codebase, because Laravel resolves
 * listeners through interfaces - so one listener audits every transition.
 */
interface EvaluationLifecycleEvent
{
    public function evaluation(): SupplierEvaluation;

    public function actor(): ?User;

    public function auditAction(): AuditAction;

    public function description(): string;
}
