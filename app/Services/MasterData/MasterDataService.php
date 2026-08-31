<?php

declare(strict_types=1);

namespace App\Services\MasterData;

use App\Enums\AuditAction;
use App\Exceptions\BusinessRuleException;
use App\Services\Audit\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * The shared write path for master data.
 *
 * All eight master entities are created, updated and retired the same way -
 * validate, persist, audit - so that flow lives here once. What differs per
 * entity is which references make a record undeletable, and each subclass
 * states that rule for itself in guardDeletion().
 *
 * Master data soft-deletes, so "delete" never destroys history: a retired
 * supplier keeps its purchase orders, and those orders keep pointing at it.
 */
abstract class MasterDataService
{
    public function __construct(
        protected readonly AuditLogService $audit,
    ) {}

    /**
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

    /**
     * Refuse the delete when the record is still in live use.
     *
     * @throws BusinessRuleException
     */
    protected function guardDeletion(Model $record): void
    {
        // Deletable by default; subclasses tighten this.
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Model
    {
        return DB::transaction(function () use ($attributes): Model {
            /** @var Model $record */
            $record = $this->modelClass()::query()->create($attributes);

            $this->audit->record(
                AuditAction::CREATED,
                class_basename($record),
                $record->getKey(),
                null,
                $this->auditableAttributes($record),
            );

            return $record;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Model $record, array $attributes): Model
    {
        return DB::transaction(function () use ($record, $attributes): Model {
            $record->fill($attributes);

            if ($record->isDirty()) {
                // Audited before the save, while the original values still
                // exist on the model; the surrounding transaction rolls the
                // audit row back if the save itself fails.
                $this->audit->recordModelChange(AuditAction::UPDATED, $record);
                $record->save();
            }

            return $record;
        });
    }

    public function delete(Model $record): void
    {
        $this->guardDeletion($record);

        DB::transaction(function () use ($record): void {
            $record->delete();

            $this->audit->record(
                AuditAction::DELETED,
                class_basename($record),
                $record->getKey(),
                $this->auditableAttributes($record),
                null,
            );
        });
    }

    public function restore(Model $record): void
    {
        DB::transaction(function () use ($record): void {
            $record->restore();

            $this->audit->record(
                AuditAction::RESTORED,
                class_basename($record),
                $record->getKey(),
                null,
                $this->auditableAttributes($record),
            );
        });
    }

    /**
     * Reject the delete, naming what is still using the record so the message
     * tells the user what to do rather than only that they cannot.
     *
     * A relation and a query builder both count, but a relation is not a
     * Builder subclass - hence the union rather than a single hint.
     *
     * @param  Builder<Model>|Relation<Model, Model, *>|int  $usage
     *
     * @throws BusinessRuleException
     */
    protected function refuseIfUsed(Builder|Relation|int $usage, string $subject, string $usedBy): void
    {
        $count = is_int($usage) ? $usage : $usage->count();

        if ($count > 0) {
            throw new BusinessRuleException(
                "{$subject} tidak dapat dihapus karena masih digunakan oleh {$count} {$usedBy}."
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function auditableAttributes(Model $record): array
    {
        return collect($record->getAttributes())
            ->except(['created_at', 'updated_at', 'deleted_at'])
            ->all();
    }
}
