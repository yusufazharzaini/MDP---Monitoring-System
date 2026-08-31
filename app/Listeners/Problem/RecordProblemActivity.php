<?php

declare(strict_types=1);

namespace App\Listeners\Problem;

use App\Events\Problem\ProblemLifecycleEvent;
use App\Services\Audit\AuditLogService;

class RecordProblemActivity
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function handle(ProblemLifecycleEvent $event): void
    {
        $problem = $event->problem();

        $this->audit->record(
            $event->auditAction(),
            'DeliveryProblem',
            $problem->getKey(),
            null,
            [
                'problem_number' => $problem->problem_number,
                'severity' => $problem->severity->value,
                'status' => $problem->status->value,
                'description' => $event->description(),
            ],
        );
    }
}
