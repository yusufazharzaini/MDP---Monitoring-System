<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Models\Delivery;
use App\Models\DeliveryProblem;
use App\Models\PurchaseOrder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Allocates the human-readable document numbers: PO-YYYYMM-0001,
 * DN-YYYYMM-0001, PRB-YYYYMM-0001.
 *
 * The sequence is derived from the highest existing number in the same month,
 * read with a row lock so two concurrent creates cannot collide. The unique
 * index on each number column is the final guarantee.
 */
class NumberGeneratorService
{
    /**
     * Minimum width of the sequence. Numbers are padded to this and grow past
     * it rather than wrapping - see next().
     */
    private const SEQUENCE_LENGTH = 4;

    public function purchaseOrderNumber(?CarbonInterface $date = null): string
    {
        return $this->next(PurchaseOrder::query(), 'po_number', 'PO', $date);
    }

    public function deliveryNumber(?CarbonInterface $date = null): string
    {
        return $this->next(Delivery::query(), 'delivery_number', 'DN', $date);
    }

    public function problemNumber(?CarbonInterface $date = null): string
    {
        return $this->next(DeliveryProblem::query(), 'problem_number', 'PRB', $date);
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private function next(Builder $query, string $column, string $prefix, ?CarbonInterface $date): string
    {
        $date ??= Carbon::now();
        $stem = sprintf('%s-%s-', $prefix, $date->format('Ym'));

        /*
         * Longest first, then highest.
         *
         * A plain descending sort on the column is a string sort, which ranks
         * 'PO-202608-9999' above 'PO-202608-10000' and would hand out 10000 a
         * second time - a duplicate the unique index then rejects, blocking
         * every further document that month. Ordering by length first makes a
         * wider sequence sort as the larger number, which is what it is.
         */
        $latest = $query
            ->where($column, 'like', $stem.'%')
            ->orderByRaw(sprintf('LENGTH(%s) DESC', $column))
            ->orderByDesc($column)
            ->lockForUpdate()
            ->value($column);

        // Everything after the final separator, so the sequence is read whole
        // however wide it has grown rather than as a fixed four characters.
        $sequence = $latest === null
            ? 1
            : (int) substr((string) $latest, strlen($stem)) + 1;

        return $stem.str_pad((string) $sequence, self::SEQUENCE_LENGTH, '0', STR_PAD_LEFT);
    }
}
