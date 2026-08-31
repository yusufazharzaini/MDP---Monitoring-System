<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Models\Delivery;
use App\Models\DeliveryProblem;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Support\NumberGeneratorService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Document numbering.
 *
 * PO-, DN- and PRB- numbers are the identifiers operators quote to suppliers
 * and auditors. A duplicate or a skipped month is a data-integrity incident,
 * not a cosmetic bug, so the sequence gets its own tests rather than being
 * exercised only in passing by the lifecycle suites.
 */
final class NumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private NumberGeneratorService $numbers;

    private int $supplierId;

    private int $plantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->numbers = app(NumberGeneratorService::class);
        Carbon::setTestNow('2026-08-15 09:00:00');

        $this->supplierId = Supplier::factory()->create()->getKey();
        $this->plantId = Plant::factory()->create()->getKey();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Insert a purchase order carrying a given number, without going through
     * the service - the point is to control what the sequence reads back.
     */
    private function existingOrder(string $number): void
    {
        DB::table('purchase_orders')->insert([
            'ulid' => (string) Str::ulid(),
            'po_number' => $number,
            'po_date' => '2026-08-01',
            'supplier_id' => $this->supplierId,
            'plant_id' => $this->plantId,
            'status' => 'DRAFT',
            'currency' => 'IDR',
            'total_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function the_first_number_of_a_month_is_one(): void
    {
        $this->assertSame('PO-202608-0001', $this->numbers->purchaseOrderNumber());
        $this->assertSame('DN-202608-0001', $this->numbers->deliveryNumber());
        $this->assertSame('PRB-202608-0001', $this->numbers->problemNumber());
    }

    #[Test]
    public function each_prefix_keeps_its_own_sequence(): void
    {
        $this->existingOrder('PO-202608-0007');

        // A purchase order at 7 must not push deliveries to 8.
        $this->assertSame('PO-202608-0008', $this->numbers->purchaseOrderNumber());
        $this->assertSame('DN-202608-0001', $this->numbers->deliveryNumber());
    }

    #[Test]
    public function the_sequence_continues_from_the_highest_existing_number(): void
    {
        $this->existingOrder('PO-202608-0001');
        $this->existingOrder('PO-202608-0042');
        $this->existingOrder('PO-202608-0013');

        $this->assertSame('PO-202608-0043', $this->numbers->purchaseOrderNumber());
    }

    #[Test]
    public function a_new_month_restarts_at_one_without_colliding(): void
    {
        $this->existingOrder('PO-202607-0250');

        $this->assertSame('PO-202608-0001', $this->numbers->purchaseOrderNumber());
    }

    #[Test]
    public function a_new_year_restarts_too(): void
    {
        $this->existingOrder('PO-202512-0999');
        Carbon::setTestNow('2026-01-05 09:00:00');

        $this->assertSame('PO-202601-0001', $this->numbers->purchaseOrderNumber());
    }

    #[Test]
    public function a_cancelled_document_never_frees_its_number_for_reuse(): void
    {
        $order = PurchaseOrder::factory()->create([
            'po_number' => 'PO-202608-0001',
            'po_date' => '2026-08-01',
        ]);
        $order->forceFill(['status' => 'CANCELLED'])->save();

        // Reissuing 0001 would give two different orders the same identifier in
        // the supplier's records.
        $this->assertSame('PO-202608-0002', $this->numbers->purchaseOrderNumber());
    }

    #[Test]
    public function the_sequence_survives_passing_the_four_digit_pad(): void
    {
        $this->existingOrder('PO-202608-9999');

        $next = $this->numbers->purchaseOrderNumber();

        // Widening past the pad must keep counting rather than wrapping back to
        // 0001 and colliding with the order that already holds it.
        $this->assertSame('PO-202608-10000', $next);

        $this->existingOrder($next);
        $this->assertSame('PO-202608-10001', $this->numbers->purchaseOrderNumber());
    }

    #[Test]
    public function a_wider_number_is_read_as_larger_than_a_padded_one(): void
    {
        // A plain string sort puts 'PO-202608-9999' above 'PO-202608-10000',
        // which would hand out 10000 twice.
        $this->existingOrder('PO-202608-9999');
        $this->existingOrder('PO-202608-10000');

        $this->assertSame('PO-202608-10001', $this->numbers->purchaseOrderNumber());
    }

    #[Test]
    public function two_allocations_in_one_transaction_never_repeat(): void
    {
        $first = null;
        $second = null;

        DB::transaction(function () use (&$first, &$second): void {
            $first = $this->numbers->purchaseOrderNumber();
            $this->existingOrder($first);
            $second = $this->numbers->purchaseOrderNumber();
        });

        $this->assertSame('PO-202608-0001', $first);
        $this->assertSame('PO-202608-0002', $second);
    }

    #[Test]
    public function the_number_is_dated_by_the_document_not_by_today(): void
    {
        // A receipt booked today for goods that arrived in July belongs to
        // July's sequence, which is what makes the number readable as a period.
        $this->assertSame(
            'DN-202607-0001',
            $this->numbers->deliveryNumber(Carbon::parse('2026-07-20')),
        );
    }

    #[Test]
    public function every_generated_number_matches_the_documented_format(): void
    {
        $this->assertMatchesRegularExpression('/^PO-\d{6}-\d{4,}$/', $this->numbers->purchaseOrderNumber());
        $this->assertMatchesRegularExpression('/^DN-\d{6}-\d{4,}$/', $this->numbers->deliveryNumber());
        $this->assertMatchesRegularExpression('/^PRB-\d{6}-\d{4,}$/', $this->numbers->problemNumber());
    }

    #[Test]
    public function the_unique_index_is_the_final_guarantee(): void
    {
        $this->existingOrder('PO-202608-0001');

        $this->expectException(UniqueConstraintViolationException::class);

        // Whatever happens above it, the schema refuses a duplicate outright.
        $this->existingOrder('PO-202608-0001');
    }

    #[Test]
    public function delivery_and_problem_sequences_behave_the_same_way(): void
    {
        $delivery = Delivery::factory()->create([
            'delivery_number' => 'DN-202608-0004',
            'delivery_date' => '2026-08-02',
        ]);
        // Against the delivery just made: letting the factory build its own
        // would mint a second, randomly numbered receipt and move the sequence.
        DeliveryProblem::factory()->create([
            'delivery_id' => $delivery->getKey(),
            'problem_number' => 'PRB-202608-0011',
            'problem_date' => '2026-08-02',
        ]);

        $this->assertSame('DN-202608-0005', $this->numbers->deliveryNumber());
        $this->assertSame('PRB-202608-0012', $this->numbers->problemNumber());
    }
}
