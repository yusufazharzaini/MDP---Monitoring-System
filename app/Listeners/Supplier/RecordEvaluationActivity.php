<?php

declare(strict_types=1);

namespace App\Listeners\Supplier;

use App\Events\Supplier\EvaluationLifecycleEvent;
use App\Services\Audit\AuditLogService;

class RecordEvaluationActivity
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function handle(EvaluationLifecycleEvent $event): void
    {
        $evaluation = $event->evaluation();

        $this->audit->record(
            $event->auditAction(),
            'SupplierEvaluation',
            $evaluation->getKey(),
            null,
            [
                'period' => $evaluation->periodLabel(),
                'supplier_id' => $evaluation->supplier_id,
                'total_score' => $evaluation->total_score,
                'grade' => $evaluation->grade->value,
                'status' => $evaluation->status->value,
                'description' => $event->description(),
            ],
        );
    }
}
