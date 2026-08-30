<?php

declare(strict_types=1);

use App\Enums\SupplierGrade;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->decimal('delivery_score', 10, 4)->default(0);
            $table->decimal('quality_score', 10, 4)->default(0);
            $table->decimal('quantity_score', 10, 4)->default(0);
            $table->decimal('responsiveness_score', 10, 4)->default(0);
            $table->decimal('total_score', 10, 4)->default(0);
            $table->enum('grade', SupplierGrade::values())->default(SupplierGrade::POOR->value)->index();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One evaluation per supplier per month.
            $table->unique(['supplier_id', 'period_year', 'period_month'], 'supplier_evaluations_period_unique');
            $table->index(['period_year', 'period_month'], 'supplier_evaluations_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_evaluations');
    }
};
