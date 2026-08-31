<?php

declare(strict_types=1);

use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('code', 30)->unique();
            $table->string('name', 150)->index();
            $table->foreignId('category_id')->constrained('material_categories')->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('uoms')->restrictOnDelete();
            $table->text('specification')->nullable();
            $table->decimal('minimum_stock', 18, 4)->default(0);
            $table->decimal('critical_stock', 18, 4)->default(0);
            $table->unsignedInteger('lead_time_days')->default(0);
            $table->boolean('is_critical')->default(false)->index();
            $table->enum('status', RecordStatus::values())->default(RecordStatus::ACTIVE->value)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
