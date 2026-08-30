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
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('plant_id')->constrained('plants')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->text('address')->nullable();
            $table->enum('status', RecordStatus::values())->default(RecordStatus::ACTIVE->value)->index();
            $table->timestamps();
            $table->softDeletes();

            // A warehouse code only has to be unique inside its own plant.
            $table->unique(['plant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
