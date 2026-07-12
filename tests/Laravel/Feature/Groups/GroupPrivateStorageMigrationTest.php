<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Groups;

use App\Console\Commands\MigrateGroupsPrivateStorage;
use App\Core\TenantContext;
use App\Models\User;
use App\Services\GroupConfigurationService;
use App\Services\GroupFileService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\Laravel\TestCase;

final class GroupPrivateStorageMigrationTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;
    private int $groupId;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('uploads');
        Storage::fake('legacy_httpdocs');

        GroupConfigurationService::set(GroupConfigurationService::CONFIG_TAB_FILES, true);
        $this->owner = User::factory()->forTenant($this->testTenantId)->create([
            'username' => 'storage_owner_' . Str::lower(Str::random(8)),
        ]);
        $this->groupId = $this->insertGroup($this->testTenantId, (int) $this->owner->id, 'Storage convergence');
        $this->insertMembership($this->groupId, (int) $this->owner->id, 'owner');
    }

    public function test_command_is_dry_run_by_default_and_apply_requires_exact_scope_and_acknowledgement(): void
    {
        $mediaId = $this->insertLegacyMedia('groups/2/' . $this->groupId . '/media/dry-run.jpg');
        Storage::disk('public')->put('groups/2/' . $this->groupId . '/media/dry-run.jpg', 'dry-run-content');

        self::assertSame(0, Artisan::call('groups:migrate-private-storage', [
            '--tenant' => (string) $this->testTenantId,
        ]), Artisan::output());
        self::assertSame('groups/2/' . $this->groupId . '/media/dry-run.jpg', DB::table('group_media')->where('id', $mediaId)->value('file_path'));
        self::assertSame('https://app.project-nexus.ie/storage/groups/legacy.jpg', DB::table('group_media')->where('id', $mediaId)->value('url'));
        Storage::disk('local')->assertMissing('groups/2/' . $this->groupId . '/media/dry-run.jpg');
        Storage::disk('public')->assertExists('groups/2/' . $this->groupId . '/media/dry-run.jpg');
        self::assertSame(0, DB::table('group_private_storage_migrations')->count());

        self::assertSame(2, Artisan::call('groups:migrate-private-storage', [
            '--apply' => true,
            '--tenant' => (string) $this->testTenantId,
        ]));
        self::assertSame(2, Artisan::call('groups:migrate-private-storage', [
            '--delete-source' => true,
            '--tenant' => (string) $this->testTenantId,
        ]));
        self::assertSame(2, Artisan::call('groups:migrate-private-storage', [
            '--apply' => true,
            '--acknowledge' => MigrateGroupsPrivateStorage::APPLY_ACKNOWLEDGEMENT,
        ]));
    }

    public function test_apply_checksum_verifies_all_legacy_asset_types_and_rerun_is_idempotent(): void
    {
        $mediaPath = "groups/{$this->testTenantId}/{$this->groupId}/media/photo.jpg";
        $thumbnailPath = "groups/{$this->testTenantId}/{$this->groupId}/media/thumb.jpg";
        $fileSource = 'legacy/group-file.txt';
        $teamSource = "uploads/team_documents/{$this->testTenantId}/{$this->groupId}/brief.pdf";
        Storage::disk('public')->put($mediaPath, 'media-bytes');
        Storage::disk('public')->put($thumbnailPath, 'thumbnail-bytes');
        Storage::disk('uploads')->put($fileSource, 'group-file-bytes');
        Storage::disk('legacy_httpdocs')->put($teamSource, '%PDF-team-document');

        $mediaId = $this->insertLegacyMedia($mediaPath, $thumbnailPath);
        $fileId = $this->insertLegacyGroupFile('/uploads/' . $fileSource);
        $documentId = $this->insertLegacyTeamDocument('/' . $teamSource);

        self::assertSame(0, $this->runApply(), Artisan::output());
        $media = DB::table('group_media')->where('id', $mediaId)->first();
        self::assertNotNull($media);
        self::assertSame($mediaPath, $media->file_path);
        self::assertSame($thumbnailPath, $media->thumbnail_path);
        self::assertNull($media->url);
        Storage::disk('local')->assertExists($mediaPath);
        Storage::disk('local')->assertExists($thumbnailPath);
        self::assertSame('media-bytes', Storage::disk('local')->get($mediaPath));
        self::assertSame('thumbnail-bytes', Storage::disk('local')->get($thumbnailPath));

        $privateFile = DB::table('group_files')->where('id', $fileId)->first();
        self::assertNotNull($privateFile);
        self::assertStringStartsWith("groups/{$this->testTenantId}/{$this->groupId}/legacy-files/", (string) $privateFile->file_path);
        self::assertSame('group-file-bytes', Storage::disk('local')->get((string) $privateFile->file_path));

        $document = DB::table('team_documents')->where('id', $documentId)->first();
        self::assertNotNull($document);
        self::assertNotNull($document->group_file_id);
        self::assertNotNull($document->storage_migrated_at);
        $mappedFile = DB::table('group_files')->where('id', (int) $document->group_file_id)->first();
        self::assertNotNull($mappedFile);
        self::assertSame('team-documents', $mappedFile->folder);
        self::assertSame('%PDF-team-document', Storage::disk('local')->get((string) $mappedFile->file_path));

        self::assertSame(4, DB::table('group_private_storage_migrations')->count());
        foreach (DB::table('group_private_storage_migrations')->get() as $migration) {
            self::assertSame(hash('sha256', match ((string) $migration->asset_role . ':' . (string) $migration->source_table) {
                'file:group_media' => 'media-bytes',
                'thumbnail:group_media' => 'thumbnail-bytes',
                'file:group_files' => 'group-file-bytes',
                default => '%PDF-team-document',
            }), $migration->sha256);
            self::assertNull($migration->source_deleted_at);
        }
        Storage::disk('public')->assertExists($mediaPath);
        Storage::disk('public')->assertExists($thumbnailPath);
        Storage::disk('uploads')->assertExists($fileSource);
        Storage::disk('legacy_httpdocs')->assertExists($teamSource);

        $groupFileCount = DB::table('group_files')->count();
        self::assertSame(0, $this->runApply(), Artisan::output());
        self::assertSame(4, DB::table('group_private_storage_migrations')->count());
        self::assertSame($groupFileCount, DB::table('group_files')->count());
    }

    public function test_delete_source_runs_only_after_commit_and_is_resumable_from_registry(): void
    {
        $mediaPath = "groups/{$this->testTenantId}/{$this->groupId}/media/delete-me.jpg";
        Storage::disk('public')->put($mediaPath, 'delete-after-commit');
        $this->insertLegacyMedia($mediaPath);

        self::assertSame(0, $this->runApply(), Artisan::output());
        Storage::disk('public')->assertExists($mediaPath);
        self::assertNull(DB::table('group_private_storage_migrations')->value('source_deleted_at'));

        self::assertSame(0, $this->runApply(true), Artisan::output());
        Storage::disk('public')->assertMissing($mediaPath);
        Storage::disk('local')->assertExists($mediaPath);
        self::assertNotNull(DB::table('group_private_storage_migrations')->value('source_deleted_at'));

        self::assertSame(0, $this->runApply(true), Artisan::output());
        self::assertSame(1, DB::table('group_private_storage_migrations')->count());
    }

    public function test_delete_source_adapter_failure_returns_failure_and_keeps_retryable_registry(): void
    {
        $path = "groups/{$this->testTenantId}/{$this->groupId}/media/delete-failure.jpg";
        Storage::disk('public')->put($path, 'delete-failure-canary');
        $this->insertLegacyMedia($path);
        self::assertSame(0, $this->runApply(), Artisan::output());

        $manager = Storage::getFacadeRoot();
        self::assertInstanceOf(FilesystemManager::class, $manager);
        $realPublic = $manager->disk('public');
        $failingPublic = Mockery::mock($realPublic)->makePartial();
        $failingPublic->shouldReceive('delete')->once()->with($path)->andReturn(false);
        $manager->set('public', $failingPublic);
        try {
            self::assertSame(1, $this->runApply(true), Artisan::output());
        } finally {
            $manager->set('public', $realPublic);
        }

        self::assertTrue($realPublic->exists($path));
        self::assertNull(DB::table('group_private_storage_migrations')->value('source_deleted_at'));
        Storage::disk('local')->assertExists($path);

        self::assertSame(0, $this->runApply(true), Artisan::output());
        self::assertFalse($realPublic->exists($path));
        self::assertNotNull(DB::table('group_private_storage_migrations')->value('source_deleted_at'));
    }

    public function test_team_document_collision_prefers_historical_httpdocs_root_and_registry_tracks_it(): void
    {
        $httpdocsPath = "uploads/team_documents/{$this->testTenantId}/{$this->groupId}/root-collision.pdf";
        $uploadsPath = "team_documents/{$this->testTenantId}/{$this->groupId}/root-collision.pdf";
        Storage::disk('legacy_httpdocs')->put($httpdocsPath, '%PDF-correct-httpdocs-bytes');
        Storage::disk('uploads')->put($uploadsPath, '%PDF-wrong-root-uploads-bytes');
        Storage::disk('public')->put($httpdocsPath, '%PDF-wrong-public-bytes');
        $documentId = $this->insertLegacyTeamDocument('/' . $httpdocsPath);

        self::assertSame(0, $this->runApply(), Artisan::output());
        $fileId = (int) DB::table('team_documents')->where('id', $documentId)->value('group_file_id');
        $targetPath = (string) DB::table('group_files')->where('id', $fileId)->value('file_path');
        self::assertSame('%PDF-correct-httpdocs-bytes', Storage::disk('local')->get($targetPath));
        $registry = DB::table('group_private_storage_migrations')
            ->where('source_table', 'team_documents')
            ->where('source_id', $documentId)
            ->first();
        self::assertNotNull($registry);
        self::assertSame('legacy_httpdocs', $registry->source_disk);
        self::assertSame($httpdocsPath, $registry->source_path);
        self::assertSame(hash('sha256', '%PDF-correct-httpdocs-bytes'), $registry->sha256);

        self::assertSame(0, $this->runApply(true), Artisan::output());
        Storage::disk('legacy_httpdocs')->assertMissing($httpdocsPath);
        Storage::disk('uploads')->assertExists($uploadsPath);
        Storage::disk('public')->assertExists($httpdocsPath);
    }

    public function test_tenant_scoped_delete_defers_shared_physical_source_until_all_tenants_migrate(): void
    {
        $foreignOwner = User::factory()->forTenant(999)->create();
        TenantContext::setById($this->testTenantId);
        $foreignGroupId = $this->insertGroup(999, (int) $foreignOwner->id, 'Foreign shared storage');
        $sharedStoredPath = '/storage/shared/corrupt-shared.jpg';
        $sharedDiskPath = 'shared/corrupt-shared.jpg';
        Storage::disk('public')->put($sharedDiskPath, 'shared-across-tenants');
        $localMediaId = $this->insertLegacyMedia($sharedStoredPath);
        $foreignMediaId = $this->insertLegacyMedia(
            $sharedStoredPath,
            null,
            999,
            $foreignGroupId,
            (int) $foreignOwner->id,
        );

        self::assertSame(1, $this->runApply(true), Artisan::output());
        self::assertNull(DB::table('group_media')->where('id', $localMediaId)->value('url'));
        self::assertNotNull(DB::table('group_media')->where('id', $foreignMediaId)->value('url'));
        Storage::disk('public')->assertExists($sharedDiskPath);
        self::assertNull(DB::table('group_private_storage_migrations')
            ->where('source_id', $localMediaId)
            ->value('source_deleted_at'));

        self::assertSame(0, $this->runApplyForTenant(999, true), Artisan::output());
        self::assertNull(DB::table('group_media')->where('id', $foreignMediaId)->value('url'));
        Storage::disk('public')->assertMissing($sharedDiskPath);
        self::assertSame(2, DB::table('group_private_storage_migrations')
            ->whereNotNull('source_deleted_at')
            ->count());
    }

    public function test_existing_private_media_with_public_url_registers_and_deletes_matching_public_copy(): void
    {
        $path = "groups/{$this->testTenantId}/{$this->groupId}/media/already-private.jpg";
        Storage::disk('local')->put($path, 'matching-private-and-public');
        Storage::disk('public')->put($path, 'matching-private-and-public');
        $mediaId = $this->insertLegacyMedia($path);

        self::assertSame(0, $this->runApply(true), Artisan::output());
        self::assertNull(DB::table('group_media')->where('id', $mediaId)->value('url'));
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        $registry = DB::table('group_private_storage_migrations')
            ->where('source_table', 'group_media')
            ->where('source_id', $mediaId)
            ->where('asset_role', 'file')
            ->first();
        self::assertNotNull($registry);
        self::assertSame('public', $registry->source_disk);
        self::assertSame($path, $registry->source_path);
        self::assertNotNull($registry->source_deleted_at);
    }

    public function test_limit_bounds_source_rows_and_next_run_resumes_in_id_order(): void
    {
        $firstPath = "groups/{$this->testTenantId}/{$this->groupId}/media/limit-first.jpg";
        $secondPath = "groups/{$this->testTenantId}/{$this->groupId}/media/limit-second.jpg";
        Storage::disk('public')->put($firstPath, 'first');
        Storage::disk('public')->put($secondPath, 'second');
        $firstId = $this->insertLegacyMedia($firstPath);
        $secondId = $this->insertLegacyMedia($secondPath);

        self::assertSame(0, Artisan::call('groups:migrate-private-storage', [
            '--tenant' => (string) $this->testTenantId,
            '--limit' => '1',
            '--apply' => true,
            '--acknowledge' => MigrateGroupsPrivateStorage::APPLY_ACKNOWLEDGEMENT,
        ]), Artisan::output());
        self::assertNull(DB::table('group_media')->where('id', $firstId)->value('url'));
        self::assertNotNull(DB::table('group_media')->where('id', $secondId)->value('url'));
        self::assertSame(1, DB::table('group_private_storage_migrations')->count());

        self::assertSame(0, $this->runApply(), Artisan::output());
        self::assertNull(DB::table('group_media')->where('id', $secondId)->value('url'));
        self::assertSame(2, DB::table('group_private_storage_migrations')->count());
    }

    public function test_database_failure_rolls_back_repoint_deletes_compensating_target_and_preserves_source(): void
    {
        $path = "groups/{$this->testTenantId}/{$this->groupId}/media/rollback.jpg";
        Storage::disk('public')->put($path, 'rollback-canary');
        $mediaId = $this->insertLegacyMedia($path);

        Event::listen(QueryExecuted::class, static function (QueryExecuted $event): void {
            if (str_starts_with(strtolower(trim($event->sql)), 'update `group_media` set')) {
                throw new \RuntimeException('Injected post-copy database failure.');
            }
        });
        try {
            self::assertSame(1, $this->runApply(), Artisan::output());
        } finally {
            Event::forget(QueryExecuted::class);
        }

        self::assertSame($path, DB::table('group_media')->where('id', $mediaId)->value('file_path'));
        self::assertNotNull(DB::table('group_media')->where('id', $mediaId)->value('url'));
        self::assertSame(0, DB::table('group_private_storage_migrations')->count());
        Storage::disk('public')->assertExists($path);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_external_url_unsafe_and_missing_paths_are_never_fetched_and_tenant_filter_is_strict(): void
    {
        DB::table('group_media')->insert([
            'tenant_id' => $this->testTenantId,
            'group_id' => $this->groupId,
            'uploaded_by' => $this->owner->id,
            'media_type' => 'image',
            'file_path' => null,
            'url' => 'https://untrusted.invalid/never-fetch.jpg',
            'file_size' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertLegacyGroupFile('../../outside.txt');
        $this->insertLegacyGroupFile('/storage/groups/missing.txt');

        $foreignOwner = User::factory()->forTenant(999)->create();
        TenantContext::setById($this->testTenantId);
        $foreignGroupId = $this->insertGroup(999, (int) $foreignOwner->id, 'Foreign storage');
        $foreignPath = "groups/999/{$foreignGroupId}/media/foreign.jpg";
        Storage::disk('public')->put($foreignPath, 'foreign-canary');
        $foreignMedia = $this->insertLegacyMedia($foreignPath, null, 999, $foreignGroupId, (int) $foreignOwner->id);

        self::assertSame(1, $this->runApply(), Artisan::output());
        self::assertSame(0, DB::table('group_private_storage_migrations')->count());
        self::assertSame($foreignPath, DB::table('group_media')->where('id', $foreignMedia)->value('file_path'));
        self::assertNotNull(DB::table('group_media')->where('id', $foreignMedia)->value('url'));
        Storage::disk('local')->assertMissing($foreignPath);
        Storage::disk('public')->assertExists($foreignPath);
    }

    public function test_compatibility_endpoints_use_private_group_files_and_protected_download_urls(): void
    {
        $anonymousUrl = "/v2/groups/{$this->groupId}/files/999/download";
        $this->apiGet($anonymousUrl)->assertUnauthorized();
        Sanctum::actingAs($this->owner, ['*']);

        $upload = $this->apiPost("/v2/groups/{$this->groupId}/documents", [
            'title' => 'Private notes',
            'file' => UploadedFile::fake()->createWithContent('notes.txt', 'protected team notes'),
        ])->assertCreated();
        $fileId = (int) $upload->json('data.id');
        self::assertGreaterThan(0, $fileId);
        $file = DB::table('group_files')->where('id', $fileId)->first();
        self::assertNotNull($file);
        self::assertSame('team-documents', $file->folder);
        self::assertStringStartsWith("groups/{$this->testTenantId}/{$this->groupId}/", (string) $file->file_path);
        Storage::disk('local')->assertExists((string) $file->file_path);

        $list = $this->apiGet("/v2/groups/{$this->groupId}/documents")->assertOk();
        self::assertSame($fileId, (int) $list->json('data.0.id'));
        self::assertSame(
            "/api/v2/groups/{$this->groupId}/files/{$fileId}/download",
            $list->json('data.0.download_url'),
        );
        self::assertStringNotContainsString("groups/{$this->testTenantId}/", (string) $list->json('data.0.download_url'));
        self::assertStringNotContainsString('storage/', (string) $list->getContent());

        $download = $this->apiGet("/v2/groups/{$this->groupId}/files/{$fileId}/download")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        self::assertStringContainsString('private', (string) $download->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', (string) $download->headers->get('Cache-Control'));
        $this->apiDelete("/v2/team-documents/{$fileId}")->assertNoContent();
        self::assertNull(DB::table('group_files')->where('id', $fileId)->first());
        Storage::disk('local')->assertMissing((string) $file->file_path);
    }

    public function test_compatibility_upload_reuses_canonical_mime_and_quota_validation(): void
    {
        Sanctum::actingAs($this->owner, ['*']);
        $this->apiPost("/v2/groups/{$this->groupId}/documents", [
            'file' => UploadedFile::fake()->createWithContent(
                'payload.svg',
                '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            ),
        ])->assertUnprocessable()->assertJsonPath('errors.0.code', 'INVALID_TYPE');

        DB::table('group_files')->insert([
            'tenant_id' => $this->testTenantId,
            'group_id' => $this->groupId,
            'file_name' => 'quota-sentinel.bin',
            'file_path' => "groups/{$this->testTenantId}/{$this->groupId}/quota-sentinel.bin",
            'file_type' => 'application/octet-stream',
            'file_size' => GroupFileService::MAX_GROUP_STORAGE,
            'folder' => null,
            'description' => null,
            'download_count' => 0,
            'uploaded_by' => $this->owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->apiPost("/v2/groups/{$this->groupId}/documents", [
            'file' => UploadedFile::fake()->createWithContent('small.txt', 'quota must reject'),
        ])->assertStatus(409)->assertJsonPath('errors.0.code', 'GROUP_QUOTA_EXCEEDED');
        self::assertSame(1, DB::table('group_files')->where('group_id', $this->groupId)->count());
        Storage::disk('local')->assertDirectoryEmpty('groups');
    }

    public function test_migrated_legacy_document_id_deletes_only_its_mapped_private_file(): void
    {
        $source = "uploads/team_documents/{$this->testTenantId}/{$this->groupId}/mapped.txt";
        Storage::disk('legacy_httpdocs')->put($source, 'mapped document');
        $dummyFile = $this->insertLegacyGroupFile("groups/{$this->testTenantId}/{$this->groupId}/dummy.txt");
        Storage::disk('local')->put("groups/{$this->testTenantId}/{$this->groupId}/dummy.txt", 'dummy');
        $documentId = $this->insertLegacyTeamDocument('/' . $source);

        self::assertSame(0, $this->runApply(), Artisan::output());
        $mappedFileId = (int) DB::table('team_documents')->where('id', $documentId)->value('group_file_id');
        self::assertNotSame($dummyFile, $mappedFileId);
        $mappedPath = (string) DB::table('group_files')->where('id', $mappedFileId)->value('file_path');

        Sanctum::actingAs($this->owner, ['*']);
        $this->apiDelete("/v2/team-documents/{$documentId}")->assertNoContent();
        self::assertNull(DB::table('group_files')->where('id', $mappedFileId)->first());
        self::assertNotNull(DB::table('group_files')->where('id', $dummyFile)->first());
        self::assertNull(DB::table('team_documents')->where('id', $documentId)->first());
        Storage::disk('local')->assertMissing($mappedPath);
    }

    public function test_unmigrated_legacy_id_collision_cannot_delete_an_unrelated_private_file(): void
    {
        $privatePath = "groups/{$this->testTenantId}/{$this->groupId}/collision-private.txt";
        $fileId = (int) DB::table('group_files')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'group_id' => $this->groupId,
            'file_name' => 'collision-private.txt',
            'file_path' => $privatePath,
            'file_type' => 'text/plain',
            'file_size' => 7,
            'folder' => 'team-documents',
            'description' => null,
            'download_count' => 0,
            'uploaded_by' => $this->owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Storage::disk('local')->put($privatePath, 'private');
        DB::table('team_documents')->insert([
            'id' => $fileId,
            'tenant_id' => $this->testTenantId,
            'group_id' => $this->groupId,
            'group_file_id' => null,
            'storage_migrated_at' => null,
            'title' => 'Unmigrated collision',
            'file_path' => "/uploads/team_documents/{$this->testTenantId}/{$this->groupId}/collision.txt",
            'file_type' => 'text/plain',
            'file_size' => 6,
            'uploaded_by' => $this->owner->id,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($this->owner, ['*']);
        $this->apiDelete("/v2/team-documents/{$fileId}")
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');
        self::assertNotNull(DB::table('group_files')->where('id', $fileId)->first());
        Storage::disk('local')->assertExists($privatePath);
    }

    private function runApply(bool $deleteSource = false): int
    {
        return $this->runApplyForTenant($this->testTenantId, $deleteSource);
    }

    private function runApplyForTenant(int $tenantId, bool $deleteSource = false): int
    {
        $arguments = [
            '--tenant' => (string) $tenantId,
            '--apply' => true,
            '--acknowledge' => MigrateGroupsPrivateStorage::APPLY_ACKNOWLEDGEMENT,
        ];
        if ($deleteSource) {
            $arguments['--delete-source'] = true;
        }

        return Artisan::call('groups:migrate-private-storage', $arguments);
    }

    private function insertLegacyMedia(
        string $path,
        ?string $thumbnail = null,
        ?int $tenantId = null,
        ?int $groupId = null,
        ?int $uploadedBy = null,
    ): int {
        return (int) DB::table('group_media')->insertGetId([
            'tenant_id' => $tenantId ?? $this->testTenantId,
            'group_id' => $groupId ?? $this->groupId,
            'uploaded_by' => $uploadedBy ?? $this->owner->id,
            'media_type' => 'image',
            'file_path' => $path,
            'url' => 'https://app.project-nexus.ie/storage/groups/legacy.jpg',
            'thumbnail_path' => $thumbnail,
            'caption' => null,
            'file_size' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertLegacyGroupFile(string $path): int
    {
        return (int) DB::table('group_files')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'group_id' => $this->groupId,
            'file_name' => 'legacy.txt',
            'file_path' => $path,
            'file_type' => 'text/plain',
            'file_size' => 1,
            'folder' => null,
            'description' => null,
            'download_count' => 0,
            'uploaded_by' => $this->owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertLegacyTeamDocument(string $path): int
    {
        return (int) DB::table('team_documents')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'group_id' => $this->groupId,
            'group_file_id' => null,
            'storage_migrated_at' => null,
            'title' => 'Legacy team brief.pdf',
            'file_path' => $path,
            'file_type' => 'application/pdf',
            'file_size' => 1,
            'uploaded_by' => $this->owner->id,
            'created_at' => now(),
        ]);
    }

    private function insertGroup(int $tenantId, int $ownerId, string $name): int
    {
        return (int) DB::table('groups')->insertGetId([
            'tenant_id' => $tenantId,
            'owner_id' => $ownerId,
            'name' => $name . ' ' . Str::lower(Str::random(8)),
            'description' => 'Private storage migration fixture.',
            'visibility' => 'private',
            'status' => 'active',
            'is_active' => true,
            'cached_member_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertMembership(int $groupId, int $userId, string $role): void
    {
        DB::table('group_members')->insert([
            'tenant_id' => $this->testTenantId,
            'group_id' => $groupId,
            'user_id' => $userId,
            'role' => $role,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
