<?php

declare(strict_types=1);

use App\Enums\DeliveryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('delivery_number', 30)->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('plant_id')->constrained('plants')->restrictOnDelete();
            $table->date('delivery_date')->index();
            $table->string('do_number', 50)->nullable();
            $table->string('vehicle_number', 30)->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', DeliveryStatus::values())
                ->default(DeliveryStatus::PENDING->value)
                ->index();
            $table->text('remarks')->nullable();
            $table->timestamps();

            // Deliveries are never soft deleted - cancelled instead (see docs/03).
            $table->index(['plant_id', 'delivery_date']);
            $table->index(['supplier_id', 'delivery_date']);
            $table->index(['status', 'delivery_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
