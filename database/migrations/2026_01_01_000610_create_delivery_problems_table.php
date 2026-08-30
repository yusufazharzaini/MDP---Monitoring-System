<?php

declare(strict_types=1);

use App\Enums\ProblemSeverity;
use App\Enums\ProblemStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_problems', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('problem_number', 30)->unique();
            // RESTRICT: a problem is an independent business record with its own
            // number, corrective actions and audit trail - it must never disappear
            // because a delivery row was removed.
            $table->foreignId('delivery_id')->constrained('deliveries')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->foreignId('problem_category_id')->constrained('problem_categories')->restrictOnDelete();
            $table->date('problem_date')->index();
            $table->text('description');
            $table->enum('severity', ProblemSeverity::values())
                ->default(ProblemSeverity::MEDIUM->value)
                ->index();
            $table->text('root_cause')->nullable();
            $table->enum('status', ProblemStatus::values())
                ->default(ProblemStatus::OPEN->value)
                ->index();
            $table->string('pic', 100)->nullable();
            $table->date('due_date')->nullable()->index();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'problem_date']);
            $table->index(['problem_category_id', 'problem_date'], 'problems_category_date_index');
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_problems');
    }
};
