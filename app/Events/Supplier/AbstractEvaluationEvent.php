<?php

declare(strict_types=1);

namespace App\Events\Supplier;

use App\Models\SupplierEvaluation;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

abstract class AbstractEvaluationEvent implements EvaluationLifecycleEvent
{
    use Dispatchable;

    public function __construct(
        protected readonly SupplierEvaluation $record,
        protected readonly ?User $user = null,
    ) {}

    public function evaluation(): SupplierEvaluation
    {
        return $this->record;
    }

    public function actor(): ?User
    {
        return $this->user;
    }
}
