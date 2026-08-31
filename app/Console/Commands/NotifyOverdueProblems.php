<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ProblemSeverity;
use App\Enums\ProblemStatus;
use App\Models\DeliveryProblem;
use App\Models\User;
use App\Notifications\Problem\OverdueProblemsDigest;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Sends the daily overdue-problem digest.
 *
 * Scheduled in routes/console.php. It counts in the database and lists only
 * the worst few, so the cost is two queries whatever the backlog looks like.
 */
class NotifyOverdueProblems extends Command
{
    /**
     * How many problems the digest names before it stops listing and leaves
     * the rest to the link.
     */
    private const WORST_LIMIT = 10;

    protected $signature = 'problems:notify-overdue';

    protected $description = 'Notify supervisors about open delivery problems past their due date';

    public function handle(): int
    {
        $today = Carbon::now()->startOfDay();

        $overdue = DeliveryProblem::query()
            ->whereIn('status', [ProblemStatus::OPEN, ProblemStatus::IN_PROGRESS])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today->toDateString());

        /** @var object{total: int|null, critical: int|null}|null $totals */
        $totals = (clone $overdue)->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when severity = ? then 1 else 0 end) as critical', [ProblemSeverity::CRITICAL->value])
            ->first();

        $total = (int) ($totals->total ?? 0);

        if ($total === 0) {
            $this->info('Tidak ada problem yang melewati due date.');

            return self::SUCCESS;
        }

        $recipients = $this->recipients();

        if ($recipients->isEmpty()) {
            $this->warn('Tidak ada penerima dengan permission problem.close.');

            return self::SUCCESS;
        }

        Notification::send($recipients, new OverdueProblemsDigest(
            $total,
            (int) ($totals->critical ?? 0),
            $this->worst($overdue, $today),
        ));

        $this->info("Digest {$total} problem terlambat dikirim ke {$recipients->count()} penerima.");

        return self::SUCCESS;
    }

    /**
     * The worst offenders: most overdue first, severity breaking the tie.
     *
     * @param  Builder<DeliveryProblem>  $overdue
     * @return array<int, array{problem_number: string, ulid: string, supplier: string, severity: string, due_date: string, days_overdue: int}>
     */
    private function worst(Builder $overdue, Carbon $today): array
    {
        return (clone $overdue)
            ->with('supplier:id,code,name,short_name')
            ->orderBy('due_date')
            ->limit(self::WORST_LIMIT)
            ->get(['id', 'ulid', 'problem_number', 'supplier_id', 'severity', 'due_date'])
            ->map(fn (DeliveryProblem $problem): array => [
                'problem_number' => $problem->problem_number,
                'ulid' => $problem->ulid,
                'supplier' => $problem->supplier?->displayName() ?? '-',
                'severity' => $problem->severity->label(),
                'due_date' => $problem->due_date?->toDateString() ?? '-',
                'days_overdue' => (int) $problem->due_date?->copy()->startOfDay()->diffInDays($today),
            ])
            ->all();
    }

    /**
     * Whoever may close a problem is whoever needs to know one is late.
     *
     * @return Collection<int, User>
     */
    private function recipients()
    {
        return User::query()
            ->active()
            ->permission('problem.close')
            ->get(['id', 'name', 'email']);
    }
}
