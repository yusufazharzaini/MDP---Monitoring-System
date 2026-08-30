<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Writes the append-only activity trail (requirement 23).
 *
 * Only attributes that actually changed are stored, so a PO quantity edit
 * records 1000 -> 1200 rather than the whole row.
 */
class AuditLogService
{
    /**
     * Attributes never worth auditing or never safe to store.
     *
     * @var array<int, string>
     */
    private const IGNORED = ['updated_at', 'created_at', 'password', 'remember_token'];

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        AuditAction $action,
        string $module,
        ?int $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'module' => $module,
            'record_id' => $recordId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 1000),
        ]);
    }

    /**
     * Audit a model change, storing only the differing attributes.
     */
    public function recordModelChange(AuditAction $action, Model $model): AuditLog
    {
        $changes = $this->auditable($model->getChanges());
        $original = array_intersect_key($this->auditable($model->getRawOriginal()), $changes);

        return $this->record(
            $action,
            class_basename($model),
            $model->getKey(),
            $original === [] ? null : $original,
            $changes === [] ? null : $changes,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function auditable(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(self::IGNORED));
    }
}
