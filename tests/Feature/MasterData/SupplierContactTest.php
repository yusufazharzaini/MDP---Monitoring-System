<?php

declare(strict_types=1);

namespace Tests\Feature\MasterData;

use App\Models\Supplier;
use App\Models\SupplierContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SupplierContactTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->supplier = Supplier::factory()->create();
    }

    #[Test]
    public function the_supplier_page_lists_its_contacts(): void
    {
        SupplierContact::factory()->primary()->create(['supplier_id' => $this->supplier->getKey()]);
        SupplierContact::factory()->create(['supplier_id' => $this->supplier->getKey()]);

        $this->actingAs($this->userWithRole('ADMIN'))
            ->get(route('suppliers.show', $this->supplier->ulid))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Suppliers/Show')
                ->has('contacts', 2)
                ->where('contacts.0.is_primary', true)
            );
    }

    #[Test]
    public function a_contact_can_be_added_to_a_supplier(): void
    {
        $this->actingAs($this->userWithRole('ADMIN'))
            ->post(route('supplier-contacts.store', $this->supplier->ulid), [
                'supplier_id' => $this->supplier->getKey(),
                'name' => 'Budi Santoso',
                'position' => 'Sales',
                'status' => 'ACTIVE',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas(SupplierContact::class, [
            'supplier_id' => $this->supplier->getKey(),
            'name' => 'Budi Santoso',
        ]);
    }

    #[Test]
    public function marking_a_contact_primary_releases_the_previous_one(): void
    {
        $first = SupplierContact::factory()->primary()->create(['supplier_id' => $this->supplier->getKey()]);
        $second = SupplierContact::factory()->create(['supplier_id' => $this->supplier->getKey()]);

        $this->actingAs($this->userWithRole('ADMIN'))
            ->put(route('supplier-contacts.update', [$this->supplier->ulid, $second->getKey()]), [
                'supplier_id' => $this->supplier->getKey(),
                'name' => $second->name,
                'is_primary' => true,
                'status' => 'ACTIVE',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($second->refresh()->is_primary);
        $this->assertFalse($first->refresh()->is_primary, 'A supplier may have only one primary contact.');
    }

    #[Test]
    public function a_contact_belonging_to_another_supplier_is_not_reachable(): void
    {
        $other = Supplier::factory()->create();
        $contact = SupplierContact::factory()->create(['supplier_id' => $other->getKey()]);

        $this->actingAs($this->userWithRole('ADMIN'))
            ->delete(route('supplier-contacts.destroy', [$this->supplier->ulid, $contact->getKey()]))
            ->assertNotFound();

        $this->assertDatabaseHas(SupplierContact::class, ['id' => $contact->getKey()]);
    }

    #[Test]
    public function a_user_without_the_supplier_permission_cannot_add_a_contact(): void
    {
        $this->actingAs($this->userWithPermissions(['supplier.view']))
            ->post(route('supplier-contacts.store', $this->supplier->ulid), [
                'supplier_id' => $this->supplier->getKey(),
                'name' => 'Tidak Boleh',
                'status' => 'ACTIVE',
            ])
            ->assertForbidden();
    }
}
