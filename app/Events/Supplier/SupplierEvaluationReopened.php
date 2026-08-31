<?php

declare(strict_types=1);

namespace App\Events\Supplier;

use App\Enums\AuditAction;
use App\Models\SupplierEvaluation;
use App\Models\User;

class SupplierEvaluationReopened extends AbstractEvaluationEvent
{
    public function __construct(
        SupplierEvaluation $record,
        ?User $user = null,
        public readonly ?string $reason = null,
    ) {
        parent::__construct($record, $user);
    }

    public function auditAction(): AuditAction
    {
        return AuditAction::UPDATED;
    }

    public function description(): string
    {
        return 'Evaluasi supplier periode '.$this->record->periodLabel().' dibuka kembali.'
            .($this->reason === null ? '' : " Alasan: {$this->reason}");
    }
}
