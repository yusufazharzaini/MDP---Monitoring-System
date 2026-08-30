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
        Schema::create('plants', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('code', 10)->unique();
            $table->string('name', 100)->index();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('pic_name', 100)->nullable();
            $table->string('pic_phone', 30)->nullable();
            $table->enum('status', RecordStatus::values())->default(RecordStatus::ACTIVE->value)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plants');
    }
};
