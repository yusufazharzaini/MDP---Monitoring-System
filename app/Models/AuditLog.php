<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only activity trail. Rows are never updated or deleted.
 */
class AuditLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'action', 'module', 'record_id',
        'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Attribute names that differ between the before and after snapshots.
     *
     * @return array<int, string>
     */
    public function changedFields(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        return array_values(array_keys(array_diff_assoc(
            array_map(static fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $new),
            array_map(static fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $old),
        )));
    }
}
