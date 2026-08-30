<?php

declare(strict_types=1);

use App\Enums\DeliveryItemCondition;
use App\Enums\OverallDeliveryStatus;
use App\Enums\QuantityStatus;
use App\Enums\TimelinessStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The delivery line is the grain of every performance KPI (assumption A1).
 * Its three status columns are derived and indexed so the dashboard can
 * aggregate them in SQL rather than in PHP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('uoms')->restrictOnDelete();
            $table->decimal('qty_received', 18, 4);
            $table->enum('condition', DeliveryItemCondition::values())
                ->default(DeliveryItemCondition::GOOD->value);

            $table->enum('timeliness_status', TimelinessStatus::values())
                ->default(TimelinessStatus::PENDING->value)
                ->index();
            $table->enum('quantity_status', QuantityStatus::values())
                ->default(QuantityStatus::PENDING->value)
                ->index();
            $table->enum('overall_status', OverallDeliveryStatus::values())
                ->default(OverallDeliveryStatus::PENDING->value)
                ->index();
            $table->integer('days_late')->default(0);

            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['material_id', 'timeliness_status'], 'delivery_items_material_timeliness_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_items');
    }
};
