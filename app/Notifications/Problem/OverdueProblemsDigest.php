<?php

declare(strict_types=1);

namespace App\Notifications\Problem;

use App\Models\Notification as NotificationRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The daily overdue-problem digest (requirement 24).
 *
 * One notification per recipient per run rather than one per problem: a
 * supervisor with thirty overdue problems needs a queue to work through, not
 * thirty mails. The payload follows assumption A6 - `title`, `message`,
 * `severity` and `url` live inside `data`, and Laravel's `read_at` replaces an
 * `is_read` column.
 */
class OverdueProblemsDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array{problem_number: string, ulid: string, supplier: string, severity: string, due_date: string, days_overdue: int}>  $worst
     */
    public function __construct(
        public readonly int $total,
        public readonly int $critical,
        public readonly array $worst,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title())
            ->greeting('Halo,')
            ->line($this->message());

        foreach ($this->worst as $problem) {
            $mail->line(sprintf(
                '%s - %s (%s), telat %d hari dari target %s.',
                $problem['problem_number'],
                $problem['supplier'],
                $problem['severity'],
                $problem['days_overdue'],
                $problem['due_date'],
            ));
        }

        return $mail
            ->action('Buka daftar problem', url(route('problems.index', ['overdue' => 1], false)))
            ->line('Setiap problem membutuhkan corrective action yang selesai sebelum dapat ditutup.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message(),
            // A badge variant, matching what the notification list renders -
            // the model documents `severity` that way.
            'severity' => $this->critical > 0 ? NotificationRecord::SEVERITY_DANGER : NotificationRecord::SEVERITY_WARNING,
            'url' => route('problems.index', ['overdue' => 1], false),
            'total' => $this->total,
            'critical' => $this->critical,
            'problems' => $this->worst,
        ];
    }

    private function title(): string
    {
        return "{$this->total} problem delivery melewati target penyelesaian";
    }

    private function message(): string
    {
        $message = "Terdapat {$this->total} problem yang masih terbuka dan sudah melewati due date";

        return $this->critical > 0
            ? $message.", {$this->critical} di antaranya berseverity Critical."
            : $message.'.';
    }
}
