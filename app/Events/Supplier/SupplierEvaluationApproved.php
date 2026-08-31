<?php

declare(strict_types=1);

namespace App\Events\Supplier;

use App\Enums\AuditAction;

class SupplierEvaluationApproved extends AbstractEvaluationEvent
{
    public function auditAction(): AuditAction
    {
        return AuditAction::APPROVED;
    }

    public function description(): string
    {
        return 'Evaluasi supplier periode '.$this->record->periodLabel()
            .' disetujui dengan grade '.$this->record->grade->label().'.';
    }
}
