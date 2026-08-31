<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\OverallDeliveryStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\CorrectiveAction;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryProblem;
use App\Models\Plant;
use App\Models\ProblemAttachment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\SupplierEvaluation;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Requirement 1: a safe $fillable.
 *
 * The rule this suite enforces is that a column is fillable only if a user may
 * legitimately type it on a form. Identifiers, lifecycle status, approval
 * metadata and every derived column belong to a service and are written with
 * forceFill(), so no request payload can approve its own purchase order or
 * rewrite the dashboard.
 */
final class MassAssignmentSafetyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function serviceOwnedColumns(): array
    {
        $cases = [
            PurchaseOrder::class => ['ulid', 'po_number', 'status', 'total_amount', 'created_by', 'approved_by', 'approved_at'],
            PurchaseOrderItem::class => ['amount', 'qty_received', 'first_receipt_date', 'last_receipt_date', 'fulfillment_status', 'timeliness_status', 'overall_status'],
            Delivery::class => ['ulid', 'delivery_number', 'status', 'received_by'],
            DeliveryItem::class => ['timeliness_status', 'quantity_status', 'overall_status', 'days_late'],
            DeliveryProblem::class => ['ulid', 'problem_number', 'status', 'closed_at', 'created_by'],
            CorrectiveAction::class => ['status', 'completed_at'],
            SupplierEvaluation::class => ['total_score', 'grade', 'created_by'],
            Supplier::class => ['ulid'],
            Plant::class => ['ulid'],
            User::class => ['ulid'],
        ];

        $flattened = [];
        foreach ($cases as $model => $columns) {
            foreach ($columns as $column) {
                $flattened[class_basename($model).'.'.$column] = [$model, $column];
            }
        }

        return $flattened;
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[Test]
    #[DataProvider('serviceOwnedColumns')]
    public function service_owned_columns_are_not_mass_assignable(string $model, string $column): void
    {
        $this->assertNotContains(
            $column,
            (new $model)->getFillable(),
            class_basename($model)."::\$fillable must not expose {$column} - it is owned by a service.",
        );
    }

    #[Test]
    public function a_request_cannot_approve_its_own_purchase_order(): void
    {
        $supplier = Supplier::factory()->create();
        $plant = Plant::factory()->create();

        $this->expectException(MassAssignmentException::class);

        PurchaseOrder::create([
            'po_date' => '2026-08-01',
            'supplier_id' => $supplier->getKey(),
            'plant_id' => $plant->getKey(),
            'status' => PurchaseOrderStatus::APPROVED,
        ]);
    }

    #[Test]
    public function a_request_cannot_write_a_delivery_line_performance_verdict(): void
    {
        $item = PurchaseOrderItem::factory()->create();
        $delivery = Delivery::factory()->create(['purchase_order_id' => $item->purchase_order_id]);

        $this->expectException(MassAssignmentException::class);

        DeliveryItem::create([
            'delivery_id' => $delivery->getKey(),
            'purchase_order_item_id' => $item->getKey(),
            'material_id' => $item->material_id,
            'uom_id' => $item->uom_id,
            'qty_received' => 100,
            'timeliness_status' => 'ON_TIME',
        ]);
    }

    #[Test]
    public function the_service_can_still_write_what_a_request_cannot(): void
    {
        $item = PurchaseOrderItem::factory()->create();

        // forceFill is the documented escape hatch, and it is deliberately
        // explicit at every call site in the service layer.
        $item->forceFill(['overall_status' => OverallDeliveryStatus::ON_TIME_FULL])->save();

        $this->assertSame(
            OverallDeliveryStatus::ON_TIME_FULL,
            $item->fresh()?->overall_status,
        );
    }

    #[Test]
    public function the_attachment_storage_path_is_hidden_from_serialisation(): void
    {
        $attachment = ProblemAttachment::factory()->create();

        $this->assertArrayNotHasKey('file_path', $attachment->toArray());
        $this->assertArrayHasKey('file_name', $attachment->toArray());
    }
}
