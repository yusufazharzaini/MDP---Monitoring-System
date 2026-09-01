<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProblemStatus;
use App\Models\DeliveryProblem;
use App\Models\ProblemAttachment;
use App\Models\User;

/**
 * Attachments follow their problem: whoever may read the problem may download
 * its evidence, and whoever may work the problem may add to it.
 *
 * This policy is the only thing between an authenticated user and a file on the
 * private disk, because the disk itself has no public URL.
 */
class ProblemAttachmentPolicy
{
    public function view(User $user, ProblemAttachment $attachment): bool
    {
        return $user->can('problem.view');
    }

    public function create(User $user, DeliveryProblem $problem): bool
    {
        return $user->can('problem.update') && $problem->status !== ProblemStatus::CANCELLED;
    }

    /**
     * Removing evidence from a problem that is already settled would change
     * what a closure was based on, so it is limited to problems still in play.
     */
    public function delete(User $user, ProblemAttachment $attachment): bool
    {
        // loadMissing rather than a bare relation read: strict mode forbids
        // lazy loading, and a policy must not depend on the caller having
        // remembered to eager-load.
        $attachment->loadMissing('problem');

        return $user->can('problem.update')
            && ($attachment->problem?->status->isOpen() ?? false);
    }
}
