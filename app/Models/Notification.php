<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The application's notification record.
 *
 * Extends Laravel's DatabaseNotification rather than replacing it, so the
 * framework's notification pipeline, the Notifiable trait and markAsRead() all
 * keep working. What this class adds is a typed reading of the `data` payload.
 *
 * Per assumption A6 in docs/01, the schema is Laravel's native one: `title`,
 * `message`, `severity` and `url` live inside `data`, and `read_at` replaces the
 * `is_read` flag the original ERD sketched.
 *
 * @property-read string $title
 * @property-read string $message
 * @property-read string $severity
 * @property-read string|null $url
 */
class Notification extends DatabaseNotification
{
    /**
     * Severity values a notification payload may carry, mirroring the badge
     * variants the UI already knows how to render.
     */
    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_DANGER = 'danger';

    public const SEVERITY_SUCCESS = 'success';

    /**
     * Notifications are written by Laravel's notification channel, never by
     * mass assignment from a request.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id'];

    /**
     * Headline text, read from the payload.
     */
    protected function title(): Attribute
    {
        return Attribute::get(fn (): string => (string) ($this->data['title'] ?? ''));
    }

    /**
     * Body text, read from the payload.
     */
    protected function message(): Attribute
    {
        return Attribute::get(fn (): string => (string) ($this->data['message'] ?? ''));
    }

    /**
     * Badge variant for the notification list; defaults to informational.
     */
    protected function severity(): Attribute
    {
        return Attribute::get(fn (): string => (string) ($this->data['severity'] ?? self::SEVERITY_INFO));
    }

    /**
     * Deep link to whatever the notification is about, when there is one.
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->data['url'] ?? null);
    }

    /**
     * Notifications addressed to one user.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey());
    }

    /**
     * Newest first - the only order a notification list is ever read in.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOfSeverity(Builder $query, string $severity): Builder
    {
        return $query->whereJsonContains('data->severity', $severity);
    }

    /**
     * Payload shape every notification in this system produces, so the frontend
     * can rely on it.
     *
     * @return array<string, mixed>
     */
    public static function payload(
        string $title,
        string $message,
        string $severity = self::SEVERITY_INFO,
        ?string $url = null,
    ): array {
        return [
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'url' => $url,
        ];
    }
}
