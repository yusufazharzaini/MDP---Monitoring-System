<?php

declare(strict_types=1);

use App\Enums\OverallDeliveryStatus;
use App\Enums\QuantityStatus;
use App\Enums\TimelinessStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            // RESTRICT, not CASCADE: an order line is business history. A purchase
            // order is cancelled, never deleted, so nothing should be able to take
            // its lines with it.
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('uoms')->restrictOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->date('schedule_delivery_date')->index();
            $table->decimal('qty_ordered', 18, 4);
            $table->decimal('unit_price', 18, 4)->default(0);
            $table->decimal('amount', 18, 4)->default(0);

            // Denormalised receipt rollup, maintained by DeliveryStatusService so the
            // PO monitoring table and critical-material queries stay pure aggregates.
            $table->decimal('qty_received', 18, 4)->default(0);
            $table->date('first_receipt_date')->nullable();
            $table->date('last_receipt_date')->nullable();
            $table->enum('fulfillment_status', QuantityStatus::values())
                ->default(QuantityStatus::PENDING->value)
                ->index();
            $table->enum('timeliness_status', TimelinessStatus::values())
                ->default(TimelinessStatus::PENDING->value)
                ->index();
            $table->enum('overall_status', OverallDeliveryStatus::values())
                ->default(OverallDeliveryStatus::PENDING->value)
                ->index();

            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['purchase_order_id', 'line_no']);
            $table->index(['schedule_delivery_date', 'overall_status'], 'po_items_schedule_overall_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
