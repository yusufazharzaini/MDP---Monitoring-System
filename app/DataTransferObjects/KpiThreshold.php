<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * One KPI's configured thresholds.
 *
 * A plain value object rather than the Eloquent model, because these are
 * cached: serialising models into the cache couples the cache payload to the
 * model class and breaks the moment either changes.
 */
final readonly class KpiThreshold
{
    public function __construct(
        public string $code,
        public string $name,
        public float $target,
        public ?float $warning,
        public ?float $critical,
        public string $unit,
        public ?string $description = null,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            code: (string) $row['code'],
            name: (string) $row['name'],
            target: (float) $row['target'],
            warning: $row['warning'] === null ? null : (float) $row['warning'],
            critical: $row['critical'] === null ? null : (float) $row['critical'],
            unit: (string) ($row['unit'] ?? '%'),
            description: $row['description'] ?? null,
        );
    }

    /**
     * Traffic-light band a measured value falls into.
     */
    public function severityFor(float $value): string
    {
        if ($this->critical !== null && $value < $this->critical) {
            return 'critical';
        }

        if ($this->warning !== null && $value < $this->warning) {
            return 'warning';
        }

        return $value >= $this->target ? 'success' : 'info';
    }

    public function meetsTarget(float $value): bool
    {
        return $value >= $this->target;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'target' => $this->target,
            'warning' => $this->warning,
            'critical' => $this->critical,
            'unit' => $this->unit,
            'description' => $this->description,
        ];
    }
}
