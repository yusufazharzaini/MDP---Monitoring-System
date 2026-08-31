<?php

declare(strict_types=1);

use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the framework users table with the organisational attributes this
 * system needs. Split from the base migration because the foreign keys point
 * at departments and plants, which are created above.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->ulid('ulid')->nullable()->unique();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('plant_id')->nullable()->constrained('plants')->nullOnDelete();
            $table->string('employee_code', 30)->nullable()->unique();
            $table->string('position', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->enum('status', RecordStatus::values())->default(RecordStatus::ACTIVE->value)->index();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Indexes must go before the columns they cover: SQLite refuses to
            // drop a column that is still part of a unique index, and MySQL
            // would silently reshape the index instead.
            $table->dropUnique('users_ulid_unique');
            $table->dropUnique('users_employee_code_unique');
            $table->dropIndex('users_status_index');

            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('plant_id');

            $table->dropColumn(['ulid', 'employee_code', 'position', 'phone', 'status', 'deleted_at']);
        });
    }
};
