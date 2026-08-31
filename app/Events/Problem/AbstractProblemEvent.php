<?php

declare(strict_types=1);

namespace App\Events\Problem;

use App\Models\DeliveryProblem;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

abstract class AbstractProblemEvent implements ProblemLifecycleEvent
{
    use Dispatchable;

    public function __construct(
        protected readonly DeliveryProblem $record,
        protected readonly ?User $user = null,
    ) {}

    public function problem(): DeliveryProblem
    {
        return $this->record;
    }

    public function actor(): ?User
    {
        return $this->user;
    }
}
