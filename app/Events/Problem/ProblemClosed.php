<?php

declare(strict_types=1);

namespace App\Events\Problem;

use App\Enums\AuditAction;
use App\Models\DeliveryProblem;
use App\Models\User;

class ProblemClosed extends AbstractProblemEvent
{
    public function __construct(
        DeliveryProblem $record,
        ?User $user = null,
        public readonly ?string $note = null,
    ) {
        parent::__construct($record, $user);
    }

    public function auditAction(): AuditAction
    {
        return AuditAction::CLOSED;
    }

    public function description(): string
    {
        return "Problem {$this->record->problem_number} ditutup."
            .($this->note === null ? '' : " Catatan: {$this->note}");
    }
}
