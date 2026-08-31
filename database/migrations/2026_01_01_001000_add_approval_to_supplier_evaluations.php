<?php

declare(strict_types=1);

use App\Enums\EvaluationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives a monthly scorecard the sign-off it was always described as having.
 *
 * Without these columns `generateForPeriod` would overwrite an evaluation a
 * manager had already approved, because nothing recorded that they had. A
 * DRAFT stays recomputable; an APPROVED row is frozen until somebody with the
 * right explicitly reopens it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_evaluations', function (Blueprint $table): void {
            $table->enum('status', EvaluationStatus::values())
                ->default(EvaluationStatus::DRAFT->value)
                ->after('grade');
            $table->foreignId('approved_by')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable()->after('approved_by');

            // The evaluation register is filtered by period and status together.
            $table->index(['status', 'period_year', 'period_month'], 'supplier_evaluations_status_period_index');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_evaluations', function (Blueprint $table): void {
            $table->dropIndex('supplier_evaluations_status_period_index');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['status', 'approved_at']);
        });
    }
};
