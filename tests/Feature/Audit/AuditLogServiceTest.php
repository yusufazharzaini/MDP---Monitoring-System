<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Material;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The audit trail's before/after contract.
 *
 * recordModelChange() must be called *before* save(), because Eloquent syncs a
 * model's original attributes as part of saving and the "before" values are
 * gone afterwards. That has already been a live bug in this project - both
 * columns recorded the new value, producing an entry showing a change from
 * 1200 to 1200 - and the lifecycle suites only assert that an entry exists,
 * never what is inside it.
 */
final class AuditLogServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuditLogService $audit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->audit = app(AuditLogService::class);
    }

    #[Test]
    public function an_edit_records_the_value_it_changed_from(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Sebelum', 'lead_time_days' => 7]);

        $supplier->fill(['name' => 'Sesudah', 'lead_time_days' => 14]);
        $log = $this->audit->recordModelChange(AuditAction::UPDATED, $supplier);
        $supplier->save();

        $this->assertSame(['name' => 'Sebelum', 'lead_time_days' => 7], $log->old_values);
        $this->assertSame(['name' => 'Sesudah', 'lead_time_days' => 14], $log->new_values);
    }

    #[Test]
    public function only_the_attributes_that_actually_changed_are_stored(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Tetap', 'lead_time_days' => 7]);

        $supplier->fill(['lead_time_days' => 21]);
        $log = $this->audit->recordModelChange(AuditAction::UPDATED, $supplier);

        // An untouched column appears in neither side, so a reader sees the
        // change rather than the whole row.
        $this->assertSame(['lead_time_days' => 7], $log->old_values);
        $this->assertSame(['lead_time_days' => 21], $log->new_values);
        $this->assertArrayNotHasKey('name', $log->new_values ?? []);
    }

    #[Test]
    public function a_save_with_nothing_dirty_records_no_values_at_all(): void
    {
        $supplier = Supplier::factory()->create();

        $log = $this->audit->recordModelChange(AuditAction::UPDATED, $supplier);

        $this->assertNull($log->old_values);
        $this->assertNull($log->new_values);
    }

    #[Test]
    public function timestamps_are_never_audited(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Awal']);

        $supplier->fill(['name' => 'Baru']);
        $supplier->updated_at = now()->addDay();
        $log = $this->audit->recordModelChange(AuditAction::UPDATED, $supplier);

        $this->assertArrayNotHasKey('updated_at', $log->new_values ?? []);
        $this->assertSame(['name' => 'Baru'], $log->new_values);
    }

    #[Test]
    public function a_password_never_reaches_the_audit_log(): void
    {
        $user = User::factory()->create();

        $user->fill(['name' => 'Nama Baru']);
        $user->password = bcrypt('rahasia-sekali');
        $user->remember_token = 'token-rahasia';

        $log = $this->audit->recordModelChange(AuditAction::UPDATED, $user);

        $this->assertArrayNotHasKey('password', $log->new_values ?? []);
        $this->assertArrayNotHasKey('remember_token', $log->new_values ?? []);
        $this->assertArrayNotHasKey('password', $log->old_values ?? []);
        $this->assertSame(['name' => 'Nama Baru'], $log->new_values);
    }

    #[Test]
    public function calling_after_save_is_the_mistake_this_contract_guards_against(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Sebelum']);

        $supplier->fill(['name' => 'Sesudah'])->save();
        $log = $this->audit->recordModelChange(AuditAction::UPDATED, $supplier);

        /*
         * Documented here rather than fixed: once save() has synced the
         * originals there is nothing left to diff, so the entry is empty. An
         * empty entry is honest; the failure mode worth preventing is an entry
         * that claims 1200 changed to 1200, and this shows it cannot happen.
         */
        $this->assertNull($log->new_values);
        $this->assertNull($log->old_values);
    }

    #[Test]
    public function the_entry_names_the_module_and_the_record(): void
    {
        $material = Material::factory()->create();

        $material->fill(['name' => 'Nama Material Baru']);
        $log = $this->audit->recordModelChange(AuditAction::UPDATED, $material);

        $this->assertSame('Material', $log->module);
        $this->assertSame($material->getKey(), $log->record_id);
        $this->assertSame(AuditAction::UPDATED, $log->action);
    }

    #[Test]
    public function the_acting_user_and_their_request_context_are_recorded(): void
    {
        $user = $this->userWithRole('PURCHASING');
        $this->actingAs($user);

        $log = $this->audit->record(AuditAction::APPROVED, 'PurchaseOrder', 42);

        $this->assertSame($user->getKey(), $log->user_id);
        $this->assertNotNull($log->ip_address);
    }

    #[Test]
    public function an_unauthenticated_action_still_leaves_a_trail(): void
    {
        $log = $this->audit->record(AuditAction::IMPORTED, 'PurchaseOrder', 7);

        // A scheduled command has no user, and that must not stop it recording.
        $this->assertNull($log->user_id);
        $this->assertSame('PurchaseOrder', $log->module);
    }

    #[Test]
    public function a_long_user_agent_is_truncated_to_fit_its_column(): void
    {
        $this->withHeader('User-Agent', str_repeat('x', 3000))
            ->actingAs($this->userWithRole('VIEWER'))
            ->get(route('dashboard'));

        $log = $this->audit->record(AuditAction::EXPORTED, 'Report');

        $this->assertLessThanOrEqual(1000, strlen((string) $log->user_agent));
    }

    #[Test]
    public function the_trail_is_append_only_in_practice(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Satu']);

        $supplier->fill(['name' => 'Dua']);
        $this->audit->recordModelChange(AuditAction::UPDATED, $supplier);
        $supplier->save();

        $supplier->fill(['name' => 'Tiga']);
        $this->audit->recordModelChange(AuditAction::UPDATED, $supplier);
        $supplier->save();

        $entries = AuditLog::query()
            ->where('module', 'Supplier')
            ->where('record_id', $supplier->getKey())
            ->orderBy('id')
            ->get();

        // Two edits leave two entries, each naming its own step - the second
        // does not overwrite or amend the first.
        $this->assertCount(2, $entries);
        $this->assertSame(['name' => 'Satu'], $entries[0]->old_values);
        $this->assertSame(['name' => 'Dua'], $entries[1]->old_values);
        $this->assertSame(['name' => 'Tiga'], $entries[1]->new_values);
    }
}
