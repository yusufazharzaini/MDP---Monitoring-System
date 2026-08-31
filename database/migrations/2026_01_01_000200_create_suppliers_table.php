<?php

declare(strict_types=1);

use App\Enums\SupplierStatus;
use App\Enums\SupplierType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('code', 20)->unique();
            $table->string('name', 150)->index();
            $table->string('short_name', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->default('Indonesia');
            $table->string('pic_name', 100)->nullable();
            $table->string('pic_email', 100)->nullable();
            $table->string('pic_phone', 30)->nullable();
            $table->unsignedInteger('lead_time_days')->default(0);
            $table->string('payment_term', 50)->nullable();
            $table->enum('supplier_type', SupplierType::values())->default(SupplierType::LOCAL->value)->index();
            $table->enum('status', SupplierStatus::values())->default(SupplierStatus::ACTIVE->value)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
