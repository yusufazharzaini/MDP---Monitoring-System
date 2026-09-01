<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\DeliveryProblem;
use App\Services\Problem\AttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * F-05: the private attachment disk used to sit inside a disk Laravel serves.
 *
 * The `local` disk carried `serve => true`, which registers unauthenticated
 * GET and PUT routes at /storage/{path}, and the attachment disk was rooted
 * inside it. Requests were refused only because Laravel demands a signed URL
 * for a disk whose visibility is not public - an unstated default, one
 * `'visibility' => 'public'` away from publishing every supplier dispute
 * document. These tests pin the layout instead of inheriting the guarantee.
 */
final class AttachmentStorageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function no_route_serves_a_storage_disk_over_http(): void
    {
        $storageRoutes = collect(app('router')->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'storage/'))
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        // Nothing in this application uses them, and they answer before any
        // policy runs.
        $this->assertSame([], $storageRoutes);
    }

    #[Test]
    public function the_attachment_disk_is_not_inside_another_disks_root(): void
    {
        $private = (string) config('filesystems.disks.private.root');

        foreach (['local', 'public'] as $other) {
            $root = (string) config("filesystems.disks.{$other}.root");

            $this->assertFalse(
                str_starts_with($private, rtrim($root, '/').'/'),
                "attachments must not be nested inside the [{$other}] disk",
            );
        }
    }

    #[Test]
    public function no_disk_that_could_reach_attachments_is_served(): void
    {
        foreach (['local', 'private'] as $disk) {
            $this->assertFalse(
                (bool) config("filesystems.disks.{$disk}.serve"),
                "the [{$disk}] disk must not be served over HTTP",
            );
        }
    }

    #[Test]
    public function the_attachment_disk_has_no_public_url(): void
    {
        // A disk with a url is a disk whose files can be linked to directly.
        $this->assertNull(config('filesystems.disks.private.url'));
        $this->assertSame('private', config('filesystems.disks.private.visibility'));
    }

    #[Test]
    public function an_uploaded_file_is_unreachable_without_going_through_the_controller(): void
    {
        $this->seedReferenceData();
        Storage::fake('private');

        $problem = DeliveryProblem::factory()->create();
        $attachment = app(AttachmentService::class)->store(
            $problem,
            UploadedFile::fake()->create('rahasia.pdf', 32, 'application/pdf'),
            $this->userWithRole('WAREHOUSE'),
        );

        // The probe from the audit, unauthenticated, against every path shape
        // that used to reach the bytes.
        foreach ([
            '/storage/attachments/'.$attachment->file_path,
            '/storage/'.$attachment->file_path,
            '/storage/../.env',
        ] as $uri) {
            $this->get($uri)->assertNotFound();
        }
    }

    #[Test]
    public function the_authorised_route_still_serves_the_file(): void
    {
        $this->seedReferenceData();
        Storage::fake('private');

        $problem = DeliveryProblem::factory()->create();
        $attachment = app(AttachmentService::class)->store(
            $problem,
            UploadedFile::fake()->create('berita-acara.pdf', 32, 'application/pdf'),
            $this->userWithRole('WAREHOUSE'),
        );

        // Closing the back door must not close the front one.
        $this->actingAs($this->userWithPermissions(['problem.view']))
            ->get(route('problem-attachments.download', [$problem->ulid, $attachment->ulid]))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    #[Test]
    public function an_unauthenticated_reader_still_cannot_use_the_authorised_route(): void
    {
        $this->seedReferenceData();
        Storage::fake('private');

        $problem = DeliveryProblem::factory()->create();
        $attachment = app(AttachmentService::class)->store(
            $problem,
            UploadedFile::fake()->create('berita-acara.pdf', 32, 'application/pdf'),
            $this->userWithRole('WAREHOUSE'),
        );

        $this->get(route('problem-attachments.download', [$problem->ulid, $attachment->ulid]))
            ->assertRedirect(route('login'));
    }
}
