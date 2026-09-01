<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Jobs\GenerateSupplierEvaluations;
use App\Models\Material;
use App\Models\Notification as NotificationRecord;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\Problem\OverdueProblemsDigest;
use App\Notifications\PurchaseOrder\PurchaseOrderAwaitingApproval;
use App\Services\PurchaseOrder\PurchaseOrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * In-app notifications, and the work that reaches the queue.
 *
 * The rule these tests hold: a notification goes to the people who can act on
 * it and to nobody else, and it never tells you about something you did
 * yourself - a system that does teaches people to ignore it.
 */
final class NotificationCentreTest extends TestCase
{
    use RefreshDatabase;

    private Plant $plant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        Carbon::setTestNow('2026-08-26 09:00:00');

        $this->plant = Plant::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function submitAnOrder(User $buyer): PurchaseOrder
    {
        $material = Material::factory()->create();
        $orders = app(PurchaseOrderService::class);

        $order = $orders->create(
            [
                'po_date' => '2026-08-01',
                'supplier_id' => Supplier::factory()->create()->getKey(),
                'plant_id' => $this->plant->getKey(),
                'currency' => 'IDR',
            ],
            [[
                'material_id' => $material->getKey(),
                'warehouse_id' => Warehouse::factory()->forPlant($this->plant)->create()->getKey(),
                'uom_id' => $material->uom_id,
                'schedule_delivery_date' => '2026-08-26',
                'qty_ordered' => 100,
                'unit_price' => 5000,
            ]],
            $buyer,
        );

        $orders->submit($order, $buyer);

        return $order;
    }

    #[Test]
    public function submitting_an_order_tells_the_people_who_can_approve_it(): void
    {
        Notification::fake();

        $manager = $this->userWithRole('MANAGEMENT');
        $clerk = $this->userWithRole('WAREHOUSE');

        $this->submitAnOrder($this->userWithRole('PURCHASING'));

        Notification::assertSentTo($manager, PurchaseOrderAwaitingApproval::class);
        // A warehouse clerk cannot release an order, so it is not their message.
        Notification::assertNotSentTo($clerk, PurchaseOrderAwaitingApproval::class);
    }

    #[Test]
    public function nobody_is_told_about_their_own_submission(): void
    {
        Notification::fake();

        // PURCHASING can also cancel but not approve; a buyer who can approve
        // still should not be told they submitted something themselves.
        $buyer = $this->userWithRole('MANAGEMENT');

        $this->submitAnOrder($buyer);

        Notification::assertNotSentTo($buyer, PurchaseOrderAwaitingApproval::class);
    }

    #[Test]
    public function an_inactive_approver_is_not_notified(): void
    {
        Notification::fake();

        $manager = $this->userWithRole('MANAGEMENT');
        $manager->forceFill(['status' => 'INACTIVE'])->save();

        $this->submitAnOrder($this->userWithRole('PURCHASING'));

        Notification::assertNothingSent();
    }

    #[Test]
    public function the_stored_payload_follows_the_notification_contract(): void
    {
        $manager = $this->userWithRole('MANAGEMENT');
        $order = $this->submitAnOrder($this->userWithRole('PURCHASING'));

        /** @var NotificationRecord $notification */
        $notification = $manager->appNotifications()->firstOrFail();

        // Assumption A6: title, message, severity and url live inside `data`.
        $this->assertStringContainsString($order->po_number, $notification->title);
        $this->assertNotSame('', $notification->message);
        $this->assertSame(NotificationRecord::SEVERITY_INFO, $notification->severity);
        $this->assertNotNull($notification->url);
        $this->assertNull($notification->read_at);
    }

    #[Test]
    public function the_bell_count_is_shared_with_every_page(): void
    {
        $manager = $this->userWithRole('MANAGEMENT');
        $this->submitAnOrder($this->userWithRole('PURCHASING'));

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('unreadNotifications', 1));
    }

    #[Test]
    public function the_list_shows_a_readers_own_notifications(): void
    {
        $manager = $this->userWithRole('MANAGEMENT');
        $this->submitAnOrder($this->userWithRole('PURCHASING'));

        $this->actingAs($manager)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Notifications/Index')
                ->has('records.data', 1)
                ->where('unread', 1));
    }

    #[Test]
    public function a_reader_never_sees_somebody_elses_notifications(): void
    {
        $manager = $this->userWithRole('MANAGEMENT');
        $this->submitAnOrder($this->userWithRole('PURCHASING'));

        $other = $this->userWithRole('LOGISTIC');

        $this->actingAs($other)
            ->get(route('notifications.index'))
            ->assertInertia(fn ($page) => $page->has('records.data', 0));

        $this->assertSame(1, $manager->unreadNotifications()->count());
    }

    #[Test]
    public function marking_one_read_leaves_the_rest_alone(): void
    {
        $manager = $this->userWithRole('MANAGEMENT');
        $buyer = $this->userWithRole('PURCHASING');
        $this->submitAnOrder($buyer);
        $this->submitAnOrder($buyer);

        $first = $manager->appNotifications()->firstOrFail();

        $this->actingAs($manager)
            ->post(route('notifications.read', $first->id))
            ->assertRedirect();

        $this->assertNotNull($first->refresh()->read_at);
        $this->assertSame(1, $manager->unreadNotifications()->count());
    }

    #[Test]
    public function another_readers_notification_id_is_a_not_found(): void
    {
        $manager = $this->userWithRole('MANAGEMENT');
        $this->submitAnOrder($this->userWithRole('PURCHASING'));
        $id = $manager->appNotifications()->value('id');

        // Scoped to the reader's own relation, so this is a 404 rather than an
        // authorisation question somebody has to remember to ask.
        $this->actingAs($this->userWithRole('LOGISTIC'))
            ->post(route('notifications.read', $id))
            ->assertNotFound();

        $this->assertNull($manager->appNotifications()->firstOrFail()->read_at);
    }

    #[Test]
    public function marking_all_read_clears_the_bell(): void
    {
        $manager = $this->userWithRole('MANAGEMENT');
        $buyer = $this->userWithRole('PURCHASING');
        $this->submitAnOrder($buyer);
        $this->submitAnOrder($buyer);

        $this->actingAs($manager)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $manager->unreadNotifications()->count());
    }

    #[Test]
    public function the_month_end_evaluation_batch_goes_to_the_queue(): void
    {
        Queue::fake();

        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->post(route('supplier-evaluations.store'), ['period' => '2026-07'])
            ->assertRedirect();

        // A month across every active supplier is four aggregates each; the
        // request must not hold open for it.
        Queue::assertPushed(GenerateSupplierEvaluations::class);
    }

    #[Test]
    public function a_single_supplier_is_still_computed_inline(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create();

        $this->actingAs($this->userWithRole('MANAGEMENT'))
            ->post(route('supplier-evaluations.store'), [
                'period' => '2026-07',
                'supplier_id' => $supplier->getKey(),
            ]);

        // One supplier is a request, not a batch - the reader wants the result.
        Queue::assertNotPushed(GenerateSupplierEvaluations::class);
    }

    #[Test]
    public function the_overdue_digest_is_queued_rather_than_sent_inline(): void
    {
        $this->assertInstanceOf(
            ShouldQueue::class,
            new OverdueProblemsDigest(1, 0, []),
        );
    }
}
