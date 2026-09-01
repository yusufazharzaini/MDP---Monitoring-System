<?php

declare(strict_types=1);

namespace App\Events\Problem;

use App\Enums\AuditAction;

class ProblemReported extends AbstractProblemEvent
{
    public function auditAction(): AuditAction
    {
        return AuditAction::CREATED;
    }

    public function description(): string
    {
        return "Problem {$this->record->problem_number} dilaporkan dengan severity "
            .$this->record->severity->label().'.';
    }
}
