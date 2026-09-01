<?php

declare(strict_types=1);

namespace App\Events\Problem;

use App\Enums\AuditAction;

class ProblemUpdated extends AbstractProblemEvent
{
    public function auditAction(): AuditAction
    {
        return AuditAction::UPDATED;
    }

    public function description(): string
    {
        return "Problem {$this->record->problem_number} diperbarui.";
    }
}
