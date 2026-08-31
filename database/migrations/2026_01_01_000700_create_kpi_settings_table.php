<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every KPI threshold the UI renders comes from this table (never hard-coded
 * in Vue, per requirement 11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->decimal('target_value', 10, 4);
            $table->decimal('warning_value', 10, 4)->nullable();
            $table->decimal('critical_value', 10, 4)->nullable();
            $table->string('unit', 20)->default('%');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_settings');
    }
};
