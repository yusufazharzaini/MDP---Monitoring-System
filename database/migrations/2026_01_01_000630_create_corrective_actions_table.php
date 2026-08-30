<?php

declare(strict_types=1);

use App\Enums\CorrectiveActionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corrective_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_problem_id')->constrained('delivery_problems')->cascadeOnDelete();
            $table->date('action_date');
            $table->foreignId('action_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description');
            $table->enum('status', CorrectiveActionStatus::values())
                ->default(CorrectiveActionStatus::OPEN->value)
                ->index();
            $table->date('due_date')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['delivery_problem_id', 'status'], 'corrective_actions_problem_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corrective_actions');
    }
};
