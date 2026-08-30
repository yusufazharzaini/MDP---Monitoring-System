<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_evaluation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_evaluation_id')->constrained('supplier_evaluations')->cascadeOnDelete();
            $table->string('criteria_name', 100);
            $table->decimal('weight', 5, 2);
            $table->decimal('score', 10, 4);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_evaluation_items');
    }
};
