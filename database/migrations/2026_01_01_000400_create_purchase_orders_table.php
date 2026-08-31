<?php

declare(strict_types=1);

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('po_number', 30)->unique();
            $table->date('po_date')->index();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('plant_id')->constrained('plants')->restrictOnDelete();
            $table->string('currency', 10)->default('IDR');
            $table->string('payment_term', 50)->nullable();
            $table->enum('status', PurchaseOrderStatus::values())
                ->default(PurchaseOrderStatus::DRAFT->value)
                ->index();
            $table->decimal('total_amount', 18, 4)->default(0);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            // Purchase orders are never soft deleted - cancelled instead (see docs/03).
            $table->index(['plant_id', 'po_date']);
            $table->index(['supplier_id', 'po_date']);
            $table->index(['status', 'po_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
