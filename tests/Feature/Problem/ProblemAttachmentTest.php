<?php

declare(strict_types=1);

namespace Tests\Feature\Problem;

use App\Enums\ProblemStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\DeliveryProblem;
use App\Models\ProblemAttachment;
use App\Models\User;
use App\Services\Problem\AttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Evidence files (requirements 8 and 35).
 *
 * The private disk has no public URL, so everything here is about the two
 * things that keep it that way: nothing the uploader controls may reach a
 * filesystem path, and the only route to the bytes runs a policy first.
 */
final class ProblemAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private AttachmentService $attachments;

    private DeliveryProblem $problem;

    private User $owner;

    /**
     * @var array<int, string>
     */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        Storage::fake('private');

        $this->attachments = app(AttachmentService::class);
        $this->owner = $this->userWithRole('WAREHOUSE');
        $this->problem = DeliveryProblem::factory()->create();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->temporaryFiles = [];

        parent::tearDown();
    }

    private function pdf(string $name = 'berita-acara.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 64, 'application/pdf');
    }

    /**
     * An upload backed by a real file, so getMimeType() probes the content
     * rather than reading a type the test handed it.
     */
    private function realUpload(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'mdp');
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    #[Test]
    public function an_upload_lands_on_the_private_disk_and_records_its_metadata(): void
    {
        $attachment = $this->attachments->store($this->problem, $this->pdf(), $this->owner);

        Storage::disk('private')->assertExists($attachment->file_path);
        $this->assertSame('berita-acara.pdf', $attachment->file_name);
        $this->assertSame('application/pdf', $attachment->mime_type);
        $this->assertGreaterThan(0, $attachment->file_size);
        $this->assertSame($this->owner->getKey(), $attachment->uploaded_by);
    }

    #[Test]
    public function the_stored_path_is_generated_and_never_the_uploaded_name(): void
    {
        $attachment = $this->attachments->store($this->problem, $this->pdf(), $this->owner);

        $this->assertStringNotContainsString('berita-acara', $attachment->file_path);
        $this->assertStringStartsWith('problem-attachments/'.$this->problem->ulid.'/', $attachment->file_path);
        $this->assertStringEndsWith('.pdf', $attachment->file_path);
    }

    #[Test]
    public function a_traversal_filename_cannot_escape_the_directory(): void
    {
        $file = UploadedFile::fake()->create('../../../.env.pdf', 16, 'application/pdf');

        $attachment = $this->attachments->store($this->problem, $file, $this->owner);

        $this->assertStringNotContainsString('..', $attachment->file_path);
        $this->assertStringNotContainsString('..', $attachment->file_name);
        $this->assertStringStartsWith('problem-attachments/'.$this->problem->ulid.'/', $attachment->file_path);
        Storage::disk('private')->assertExists($attachment->file_path);
    }

    #[Test]
    public function the_storage_path_never_reaches_a_serialised_payload(): void
    {
        $attachment = $this->attachments->store($this->problem, $this->pdf(), $this->owner);

        $this->assertArrayNotHasKey('file_path', $attachment->toArray());
    }

    #[Test]
    public function a_disallowed_type_is_refused_even_with_an_allowed_extension(): void
    {
        /*
         * A PHP script renamed to .pdf. This has to be a real file rather than
         * UploadedFile::fake(), because the fake reports a type guessed from
         * its name - which is exactly the check being bypassed here. The real
         * upload is probed with finfo, and reports text/x-php.
         */
        $file = $this->realUpload('payload.pdf', "<?php echo 'hi';");

        $this->expectException(BusinessRuleException::class);

        $this->attachments->store($this->problem, $file, $this->owner);
    }

    #[Test]
    public function the_upload_route_rejects_a_script_renamed_with_an_allowed_extension(): void
    {
        $this->actingAs($this->userWithPermissions(['problem.view', 'problem.update']))
            ->post(route('problem-attachments.store', $this->problem->ulid), [
                'file' => $this->realUpload('payload.pdf', "<?php echo 'hi';"),
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('problem_attachments', 0);
    }

    #[Test]
    public function a_file_over_the_configured_limit_is_refused(): void
    {
        config()->set('mdp.attachments.max_kilobytes', 100);
        $file = UploadedFile::fake()->create('besar.pdf', 200, 'application/pdf');

        $this->expectException(BusinessRuleException::class);

        $this->attachments->store($this->problem, $file, $this->owner);
    }

    #[Test]
    public function an_empty_file_is_refused(): void
    {
        $file = UploadedFile::fake()->create('kosong.pdf', 0, 'application/pdf');

        $this->expectException(BusinessRuleException::class);

        $this->attachments->store($this->problem, $file, $this->owner);
    }

    #[Test]
    public function a_cancelled_problem_accepts_no_further_evidence(): void
    {
        $this->problem->forceFill(['status' => ProblemStatus::CANCELLED])->save();

        $this->expectException(BusinessRuleException::class);

        $this->attachments->store($this->problem->refresh(), $this->pdf(), $this->owner);
    }

    #[Test]
    public function deleting_removes_both_the_row_and_the_bytes(): void
    {
        $attachment = $this->attachments->store($this->problem, $this->pdf(), $this->owner);
        $path = $attachment->file_path;

        $this->attachments->delete($attachment);

        Storage::disk('private')->assertMissing($path);
        $this->assertDatabaseMissing('problem_attachments', ['id' => $attachment->getKey()]);
    }

    #[Test]
    public function a_missing_file_is_reported_rather_than_streamed_as_nothing(): void
    {
        $attachment = $this->attachments->store($this->problem, $this->pdf(), $this->owner);
        Storage::disk('private')->delete($attachment->file_path);

        $this->expectException(BusinessRuleException::class);

        $this->attachments->stream($attachment);
    }

    #[Test]
    public function the_download_route_streams_the_file_to_an_authorised_user(): void
    {
        $attachment = $this->attachments->store($this->problem, $this->pdf(), $this->owner);

        $response = $this->actingAs($this->userWithPermissions(['problem.view']))
            ->get(route('problem-attachments.download', [$this->problem->ulid, $attachment->ulid]));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('berita-acara.pdf', (string) $response->headers->get('content-disposition'));
    }

    #[Test]
    public function the_download_route_refuses_a_user_without_the_view_permission(): void
    {
        $attachment = $this->attachments->store($this->problem, $this->pdf(), $this->owner);

        $this->actingAs($this->userWithPermissions(['delivery.view']))
            ->get(route('problem-attachments.download', [$this->problem->ulid, $attachment->ulid]))
            ->assertForbidden();
    }

    #[Test]
    public function an_attachment_is_not_reachable_through_another_problems_url(): void
    {
        $attachment = $this->attachments->store($this->problem, $this->pdf(), $this->owner);
        $other = DeliveryProblem::factory()->create();

        $this->actingAs($this->userWithPermissions(['problem.view']))
            ->get(route('problem-attachments.download', [$other->ulid, $attachment->ulid]))
            ->assertNotFound();
    }

    #[Test]
    public function uploading_through_the_route_requires_the_update_permission(): void
    {
        $this->actingAs($this->userWithPermissions(['problem.view']))
            ->post(route('problem-attachments.store', $this->problem->ulid), ['file' => $this->pdf()])
            ->assertForbidden();

        $this->assertDatabaseCount('problem_attachments', 0);
    }

    #[Test]
    public function the_upload_route_rejects_a_disallowed_extension_before_the_service_runs(): void
    {
        $this->actingAs($this->userWithPermissions(['problem.view', 'problem.update']))
            ->post(route('problem-attachments.store', $this->problem->ulid), [
                'file' => UploadedFile::fake()->create('skrip.php', 8),
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('problem_attachments', 0);
    }

    #[Test]
    public function an_upload_through_the_route_is_stored_and_attributed(): void
    {
        $user = $this->userWithPermissions(['problem.view', 'problem.update']);

        $this->actingAs($user)
            ->post(route('problem-attachments.store', $this->problem->ulid), ['file' => $this->pdf()])
            ->assertRedirect();

        $attachment = ProblemAttachment::query()->firstOrFail();

        $this->assertSame($user->getKey(), $attachment->uploaded_by);
        $this->assertSame($this->problem->getKey(), $attachment->delivery_problem_id);
        Storage::disk('private')->assertExists($attachment->file_path);
    }
}
