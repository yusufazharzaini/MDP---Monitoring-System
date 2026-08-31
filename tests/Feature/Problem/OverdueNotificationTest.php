<?php

declare(strict_types=1);

namespace Tests\Feature\Problem;

use App\Enums\ProblemSeverity;
use App\Enums\ProblemStatus;
use App\Models\DeliveryProblem;
use App\Notifications\Problem\OverdueProblemsDigest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The daily overdue digest.
 *
 * One notification per recipient per run, addressed to whoever may close a
 * problem - the people who can actually do something about a late one.
 */
final class OverdueNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        Carbon::setTestNow('2026-08-26 07:00:00');
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function overdueProblem(ProblemSeverity $severity = ProblemSeverity::HIGH): DeliveryProblem
    {
        return DeliveryProblem::factory()->create([
            'status' => ProblemStatus::OPEN,
            'severity' => $severity,
            'problem_date' => '2026-08-01',
            'due_date' => '2026-08-10',
        ]);
    }

    #[Test]
    public function nothing_is_sent_when_no_problem_is_overdue(): void
    {
        DeliveryProblem::factory()->create([
            'status' => ProblemStatus::OPEN,
            'problem_date' => '2026-08-20',
            'due_date' => '2026-09-30',
        ]);
        $this->userWithRole('LOGISTIC');

        $this->artisan('problems:notify-overdue')->assertSuccessful();

        Notification::assertNothingSent();
    }

    #[Test]
    public function the_digest_reaches_everyone_who_may_close_a_problem(): void
    {
        $this->overdueProblem();

        $supervisor = $this->userWithRole('LOGISTIC');
        $manager = $this->userWithRole('MANAGEMENT');
        // WAREHOUSE may work a problem but not close one, so it is not their queue.
        $clerk = $this->userWithRole('WAREHOUSE');

        $this->artisan('problems:notify-overdue')->assertSuccessful();

        Notification::assertSentTo($supervisor, OverdueProblemsDigest::class);
        Notification::assertSentTo($manager, OverdueProblemsDigest::class);
        Notification::assertNotSentTo($clerk, OverdueProblemsDigest::class);
    }

    #[Test]
    public function one_digest_carries_the_whole_backlog_rather_than_one_per_problem(): void
    {
        DeliveryProblem::factory()->count(4)->create([
            'status' => ProblemStatus::OPEN,
            'severity' => ProblemSeverity::HIGH,
            'problem_date' => '2026-08-01',
            'due_date' => '2026-08-10',
        ]);
        $this->overdueProblem(ProblemSeverity::CRITICAL);

        $supervisor = $this->userWithRole('LOGISTIC');

        $this->artisan('problems:notify-overdue')->assertSuccessful();

        Notification::assertSentToTimes($supervisor, OverdueProblemsDigest::class, 1);
        Notification::assertSentTo(
            $supervisor,
            fn (OverdueProblemsDigest $digest): bool => $digest->total === 5 && $digest->critical === 1,
        );
    }

    #[Test]
    public function a_closed_or_cancelled_problem_never_appears_in_the_digest(): void
    {
        DeliveryProblem::factory()->create([
            'status' => ProblemStatus::CLOSED,
            'problem_date' => '2026-08-01',
            'due_date' => '2026-08-10',
        ]);
        DeliveryProblem::factory()->create([
            'status' => ProblemStatus::CANCELLED,
            'problem_date' => '2026-08-01',
            'due_date' => '2026-08-10',
        ]);
        $this->userWithRole('LOGISTIC');

        $this->artisan('problems:notify-overdue')->assertSuccessful();

        Notification::assertNothingSent();
    }

    #[Test]
    public function the_payload_names_the_worst_offenders_and_how_late_they_are(): void
    {
        $problem = $this->overdueProblem(ProblemSeverity::CRITICAL);
        $supervisor = $this->userWithRole('LOGISTIC');

        $this->artisan('problems:notify-overdue')->assertSuccessful();

        Notification::assertSentTo($supervisor, function (OverdueProblemsDigest $digest) use ($problem): bool {
            $first = $digest->worst[0];

            return $first['problem_number'] === $problem->problem_number
                && $first['due_date'] === '2026-08-10'
                && $first['days_overdue'] === 16;
        });
    }

    #[Test]
    public function the_stored_payload_follows_the_notification_data_contract(): void
    {
        $this->overdueProblem();
        $supervisor = $this->userWithRole('LOGISTIC');

        $this->artisan('problems:notify-overdue')->assertSuccessful();

        Notification::assertSentTo($supervisor, function (OverdueProblemsDigest $digest) use ($supervisor): bool {
            $data = $digest->toArray($supervisor);

            return isset($data['title'], $data['message'], $data['severity'], $data['url'])
                && $data['total'] === 1;
        });
    }

    #[Test]
    public function an_inactive_user_is_not_notified(): void
    {
        $this->overdueProblem();

        $supervisor = $this->userWithRole('LOGISTIC');
        $supervisor->forceFill(['status' => 'INACTIVE'])->save();

        $this->artisan('problems:notify-overdue')->assertSuccessful();

        Notification::assertNothingSent();
    }
}
