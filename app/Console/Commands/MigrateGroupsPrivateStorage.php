<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Converges historical Groups assets on the authenticated private-storage
 * contract. The migration is deliberately operator-driven and dry-run-first.
 */
final class MigrateGroupsPrivateStorage extends Command
{
    public const APPLY_ACKNOWLEDGEMENT = 'MIGRATE-GROUPS-PRIVATE-STORAGE';

    protected $signature = 'groups:migrate-private-storage
        {--tenant= : Limit inventory and migration to one tenant ID}
        {--all-tenants : Explicitly select every tenant for apply mode}
        {--limit=0 : Maximum source rows to inspect (0 means all)}
        {--apply : Copy, verify, and repoint assets (default is dry-run)}
        {--acknowledge= : Exact acknowledgement required with --apply}
        {--delete-source : Delete verified public source objects after commit}';

    protected $description = 'Dry-run or apply resumable migration of legacy Groups assets to private storage';

    /** @var array<string, int> */
    private array $totals = [];

    private bool $apply = false;
    private ?int $tenantId = null;
    private int $remaining = PHP_INT_MAX;

    public function handle(): int
    {
        $this->resetTotals();
        if (! $this->validateSchema()) {
            return self::FAILURE;
        }
        if (! $this->validateOptions()) {
            return self::INVALID;
        }

        $this->components->info($this->apply
            ? 'APPLY mode: verified copies and database repoints will be persisted.'
            : 'DRY-RUN mode: storage and database state will not be changed.');

        $this->processGroupMedia();
        $this->processGroupFiles();
        $this->processTeamDocuments();

        if ($this->apply && (bool) $this->option('delete-source')) {
            $this->deleteCommittedSources();
        }

        $this->renderSummary();

        return $this->apply && $this->totals['failed'] > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function resetTotals(): void
    {
        $this->totals = [
            'scanned' => 0,
            'already_private' => 0,
            'would_migrate' => 0,
            'migrated' => 0,
            'concurrent_or_rerun' => 0,
            'unmigratable_external_url' => 0,
            'unsafe_path' => 0,
            'missing_source' => 0,
            'invalid_owner' => 0,
            'source_deleted' => 0,
            'source_already_absent' => 0,
            'source_delete_deferred' => 0,
            'failed' => 0,
        ];
    }

    private function validateSchema(): bool
    {
        $required = [
            'groups',
            'users',
            'group_media',
            'group_files',
            'team_documents',
            'group_private_storage_migrations',
        ];
        $missing = array_values(array_filter(
            $required,
            static fn (string $table): bool => ! Schema::hasTable($table),
        ));
        if ($missing !== []) {
            $this->error('Required tables are missing: ' . implode(', ', $missing));
            return false;
        }
        foreach (['group_file_id', 'storage_migrated_at'] as $column) {
            if (! Schema::hasColumn('team_documents', $column)) {
                $this->error("Required team_documents.{$column} migration column is missing.");
                return false;
            }
        }

        return true;
    }

    private function validateOptions(): bool
    {
        $rawTenant = trim((string) ($this->option('tenant') ?? ''));
        if ($rawTenant !== '' && (! ctype_digit($rawTenant) || (int) $rawTenant < 1)) {
            $this->error('--tenant must be a positive integer.');
            return false;
        }
        $this->tenantId = $rawTenant !== '' ? (int) $rawTenant : null;

        $rawLimit = trim((string) ($this->option('limit') ?? '0'));
        if ($rawLimit === '' || ! ctype_digit($rawLimit)) {
            $this->error('--limit must be zero or a positive integer.');
            return false;
        }
        $limit = (int) $rawLimit;
        $this->remaining = $limit === 0 ? PHP_INT_MAX : $limit;

        $this->apply = (bool) $this->option('apply');
        $allTenants = (bool) $this->option('all-tenants');
        if ($this->tenantId !== null && $allTenants) {
            $this->error('Use either --tenant or --all-tenants, never both.');
            return false;
        }
        if ((bool) $this->option('delete-source') && ! $this->apply) {
            $this->error('--delete-source is only valid with --apply.');
            return false;
        }
        if (! $this->apply) {
            return true;
        }
        if ($this->tenantId === null && ! $allTenants) {
            $this->error('--apply requires either --tenant=<id> or --all-tenants.');
            return false;
        }
        if ((string) ($this->option('acknowledge') ?? '') !== self::APPLY_ACKNOWLEDGEMENT) {
            $this->error('--apply requires --acknowledge=' . self::APPLY_ACKNOWLEDGEMENT . '.');
            return false;
        }

        return true;
    }

    private function processGroupMedia(): void
    {
        if ($this->remaining === 0) {
            return;
        }
        $query = $this->scoped(DB::table('group_media'))
            ->select([
                'id', 'tenant_id', 'group_id', 'file_path', 'thumbnail_path',
                'url', 'file_size',
            ])
            ->orderBy('id');
        $this->applyLimit($query);

        foreach ($query->get() as $row) {
            if (! $this->consumeRow()) {
                break;
            }
            $filePath = $this->nullableString($row->file_path);
            if ($filePath === null) {
                $reason = $this->nullableString($row->url) !== null
                    ? 'unmigratable_external_url'
                    : 'missing_source';
                $this->issue('group_media', (int) $row->id, $reason, 'No local media source path is recorded.');
                continue;
            }

            $hasLegacyPublicUrl = $this->nullableString($row->url) !== null;
            $main = $this->resolveAsset(
                $filePath,
                (int) $row->tenant_id,
                (int) $row->group_id,
                'group_media',
                $hasLegacyPublicUrl,
            );
            if (! $this->assetCanMigrate('group_media', (int) $row->id, 'file', $main)) {
                continue;
            }
            $thumbnail = null;
            $thumbnailPath = $this->nullableString($row->thumbnail_path);
            if ($thumbnailPath !== null) {
                $thumbnail = $this->resolveAsset(
                    $thumbnailPath,
                    (int) $row->tenant_id,
                    (int) $row->group_id,
                    'group_media',
                    $hasLegacyPublicUrl,
                );
                if (! $this->assetCanMigrate('group_media', (int) $row->id, 'thumbnail', $thumbnail)) {
                    continue;
                }
            }

            $needsRepoint = $main['state'] !== 'private'
                || ($thumbnail !== null && $thumbnail['state'] !== 'private')
                || $this->nullableString($row->url) !== null;
            if (! $needsRepoint) {
                $this->totals['already_private']++;
                continue;
            }
            if (! $this->apply) {
                $this->totals['would_migrate']++;
                $this->line("[dry-run] group_media {$row->id}: copy and privately repoint media assets");
                continue;
            }

            try {
                $mainCopy = $this->copyAsset('group_media', (int) $row->id, 'file', $main, (int) $row->tenant_id, (int) $row->group_id);
                $thumbnailCopy = $thumbnail === null
                    ? null
                    : $this->copyAsset('group_media', (int) $row->id, 'thumbnail', $thumbnail, (int) $row->tenant_id, (int) $row->group_id);
                $changed = $this->persistMedia($row, $mainCopy, $thumbnailCopy);
                if ($changed) {
                    $this->totals['migrated']++;
                } else {
                    $this->totals['concurrent_or_rerun']++;
                }
            } catch (Throwable $exception) {
                if (isset($mainCopy)) {
                    $this->compensateTarget($mainCopy);
                }
                if (isset($thumbnailCopy) && $thumbnailCopy !== null) {
                    $this->compensateTarget($thumbnailCopy);
                }
                $this->failure('group_media', (int) $row->id, $exception);
            }
        }
    }

    private function processGroupFiles(): void
    {
        if ($this->remaining === 0) {
            return;
        }
        $query = $this->scoped(DB::table('group_files'))
            ->select(['id', 'tenant_id', 'group_id', 'file_path', 'file_size'])
            ->orderBy('id');
        $this->applyLimit($query);

        foreach ($query->get() as $row) {
            if (! $this->consumeRow()) {
                break;
            }
            $path = $this->nullableString($row->file_path);
            if ($path === null) {
                $this->issue('group_files', (int) $row->id, 'missing_source', 'No source path is recorded.');
                continue;
            }
            $asset = $this->resolveAsset(
                $path,
                (int) $row->tenant_id,
                (int) $row->group_id,
                'group_files',
            );
            if (! $this->assetCanMigrate('group_files', (int) $row->id, 'file', $asset)) {
                continue;
            }
            if ($asset['state'] === 'private') {
                $this->totals['already_private']++;
                continue;
            }
            if (! $this->apply) {
                $this->totals['would_migrate']++;
                $this->line("[dry-run] group_files {$row->id}: copy and privately repoint file");
                continue;
            }

            try {
                $copy = $this->copyAsset('group_files', (int) $row->id, 'file', $asset, (int) $row->tenant_id, (int) $row->group_id);
                $changed = $this->persistGroupFile($row, $copy);
                if ($changed) {
                    $this->totals['migrated']++;
                } else {
                    $this->totals['concurrent_or_rerun']++;
                }
            } catch (Throwable $exception) {
                if (isset($copy)) {
                    $this->compensateTarget($copy);
                }
                $this->failure('group_files', (int) $row->id, $exception);
            }
        }
    }

    private function processTeamDocuments(): void
    {
        if ($this->remaining === 0) {
            return;
        }
        $query = $this->scoped(DB::table('team_documents'))
            ->select([
                'id', 'tenant_id', 'group_id', 'group_file_id', 'title',
                'file_path', 'file_type', 'file_size', 'uploaded_by', 'created_at',
            ])
            ->orderBy('id');
        $this->applyLimit($query);

        foreach ($query->get() as $row) {
            if (! $this->consumeRow()) {
                break;
            }
            if ($row->group_file_id !== null) {
                $mapped = DB::table('group_files')
                    ->where('id', (int) $row->group_file_id)
                    ->where('tenant_id', (int) $row->tenant_id)
                    ->where('group_id', (int) $row->group_id)
                    ->exists();
                if ($mapped) {
                    $this->totals['already_private']++;
                } else {
                    $this->issue('team_documents', (int) $row->id, 'missing_source', 'The mapped private file no longer exists.');
                }
                continue;
            }
            if (! $this->validTeamDocumentOwner($row)) {
                $this->issue('team_documents', (int) $row->id, 'invalid_owner', 'Group or uploader is outside the recorded tenant.');
                continue;
            }
            $path = $this->nullableString($row->file_path);
            if ($path === null) {
                $this->issue('team_documents', (int) $row->id, 'missing_source', 'No source path is recorded.');
                continue;
            }
            $asset = $this->resolveAsset(
                $path,
                (int) $row->tenant_id,
                (int) $row->group_id,
                'team_documents',
            );
            if (! $this->assetCanMigrate('team_documents', (int) $row->id, 'file', $asset)) {
                continue;
            }
            if (! $this->apply) {
                $this->totals['would_migrate']++;
                $this->line("[dry-run] team_documents {$row->id}: create canonical private group file");
                continue;
            }

            try {
                $copy = $this->copyAsset('team_documents', (int) $row->id, 'file', $asset, (int) $row->tenant_id, (int) $row->group_id);
                $changed = $this->persistTeamDocument($row, $copy);
                if ($changed) {
                    $this->totals['migrated']++;
                } else {
                    $this->totals['concurrent_or_rerun']++;
                }
            } catch (Throwable $exception) {
                if (isset($copy)) {
                    $this->compensateTarget($copy);
                }
                $this->failure('team_documents', (int) $row->id, $exception);
            }
        }
    }

    private function scoped(Builder $query): Builder
    {
        if ($this->tenantId !== null) {
            $query->where('tenant_id', $this->tenantId);
        }

        return $query;
    }

    private function applyLimit(Builder $query): void
    {
        if ($this->remaining !== PHP_INT_MAX) {
            $query->limit($this->remaining);
        }
    }

    private function consumeRow(): bool
    {
        if ($this->remaining === 0) {
            return false;
        }
        $this->totals['scanned']++;
        if ($this->remaining !== PHP_INT_MAX) {
            $this->remaining--;
        }

        return true;
    }

    /**
     * @return array{state: 'private'|'source'|'external'|'unsafe'|'missing', disk?: string, path?: string}
     */
    private function resolveAsset(
        string $storedPath,
        int $tenantId,
        int $groupId,
        string $sourceTable,
        bool $preferLegacySource = false,
    ): array {
        $decoded = $this->decodeStoredPath($storedPath);
        if ($decoded['state'] !== 'path') {
            return ['state' => $decoded['state']];
        }
        $relative = $decoded['path'];
        $localPrivateExists = $this->isCanonicalTargetPath($relative, $tenantId, $groupId)
            && Storage::disk('local')->exists($relative);
        if ($localPrivateExists && ! $preferLegacySource) {
            return ['state' => 'private', 'disk' => 'local', 'path' => $relative];
        }

        foreach ($this->sourceCandidates($relative, $sourceTable) as [$disk, $path]) {
            if (Storage::disk($disk)->exists($path)) {
                return ['state' => 'source', 'disk' => $disk, 'path' => $path];
            }
        }

        if ($localPrivateExists) {
            return ['state' => 'private', 'disk' => 'local', 'path' => $relative];
        }

        return ['state' => 'missing'];
    }

    /** @return array{state: 'path'|'external'|'unsafe', path?: string} */
    private function decodeStoredPath(string $storedPath): array
    {
        $value = trim($storedPath);
        if ($value === '' || str_contains($value, "\0")) {
            return ['state' => 'unsafe'];
        }
        if (filter_var($value, FILTER_VALIDATE_URL) !== false) {
            $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
            if (! in_array($scheme, ['http', 'https'], true)) {
                return ['state' => 'external'];
            }
            $sourceHost = strtolower((string) parse_url($value, PHP_URL_HOST));
            $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
            if ($sourceHost === '' || $appHost === '' || $sourceHost !== $appHost) {
                return ['state' => 'external'];
            }
            $value = (string) parse_url($value, PHP_URL_PATH);
        }

        $value = rawurldecode(str_replace('\\', '/', $value));
        if ($value === '' || str_contains($value, "\0") || preg_match('/^[A-Za-z]:\//', $value) === 1) {
            return ['state' => 'unsafe'];
        }
        $value = ltrim($value, '/');
        $segments = explode('/', $value);
        if ($segments === [] || array_filter(
            $segments,
            static fn (string $segment): bool => $segment === '' || $segment === '.' || $segment === '..',
        ) !== []) {
            return ['state' => 'unsafe'];
        }

        return ['state' => 'path', 'path' => implode('/', $segments)];
    }

    /** @return list<array{0: string, 1: string}> */
    private function sourceCandidates(string $relative, string $sourceTable): array
    {
        if (str_starts_with($relative, 'storage/')) {
            return [
                ['public', substr($relative, strlen('storage/'))],
                ['legacy_httpdocs', $relative],
            ];
        }
        if (str_starts_with($relative, 'uploads/')) {
            if ($sourceTable === 'team_documents') {
                // Historical TeamDocumentService wrote beneath
                // httpdocs/uploads/team_documents, not the root uploads disk.
                return [
                    ['legacy_httpdocs', $relative],
                    ['uploads', substr($relative, strlen('uploads/'))],
                    ['public', $relative],
                ];
            }
            if ($sourceTable === 'group_media') {
                return [
                    ['public', $relative],
                    ['legacy_httpdocs', $relative],
                    ['uploads', substr($relative, strlen('uploads/'))],
                ];
            }

            // Legacy GroupFileService/documented generic precedence.
            return [
                ['uploads', substr($relative, strlen('uploads/'))],
                ['legacy_httpdocs', $relative],
                ['public', $relative],
            ];
        }
        if (str_starts_with($relative, 'httpdocs/')) {
            return [['legacy_httpdocs', substr($relative, strlen('httpdocs/'))]];
        }

        if ($sourceTable === 'team_documents') {
            return [
                ['legacy_httpdocs', $relative],
                ['uploads', $relative],
                ['public', $relative],
            ];
        }

        return [
            ['public', $relative],
            ['uploads', $relative],
            ['legacy_httpdocs', $relative],
        ];
    }

    /**
     * @param array{state: string, disk?: string, path?: string} $asset
     */
    private function assetCanMigrate(string $table, int $id, string $role, array $asset): bool
    {
        if (in_array($asset['state'], ['private', 'source'], true)) {
            return true;
        }
        $reason = match ($asset['state']) {
            'external' => 'unmigratable_external_url',
            'unsafe' => 'unsafe_path',
            default => 'missing_source',
        };
        $this->issue($table, $id, $reason, "{$role} asset cannot be resolved without network access.");

        return false;
    }

    /**
     * @param array{state: string, disk?: string, path?: string} $asset
     * @return array{source_disk: string, source_path: string, target_path: string, checksum: string, bytes: int, created: bool, register: bool}
     */
    private function copyAsset(
        string $table,
        int $id,
        string $role,
        array $asset,
        int $tenantId,
        int $groupId,
    ): array {
        $sourceDisk = (string) ($asset['disk'] ?? '');
        $sourcePath = (string) ($asset['path'] ?? '');
        if ($sourceDisk === '' || $sourcePath === '') {
            throw new RuntimeException('Resolved source asset is incomplete.');
        }
        $source = Storage::disk($sourceDisk);
        $checksum = $this->checksum($source, $sourcePath);
        $bytes = (int) $source->size($sourcePath);
        $targetPath = $sourceDisk === 'local'
            ? $sourcePath
            : $this->targetPath($table, $id, $role, $tenantId, $groupId, $sourcePath, $checksum);
        $target = Storage::disk('local');
        $created = false;

        if ($sourceDisk !== 'local') {
            if ($target->exists($targetPath)) {
                if (! hash_equals($checksum, $this->checksum($target, $targetPath))) {
                    throw new RuntimeException("Private target collision at {$targetPath}.");
                }
            } else {
                $stream = $source->readStream($sourcePath);
                if (! is_resource($stream)) {
                    throw new RuntimeException("Unable to read {$sourceDisk}:{$sourcePath}.");
                }
                try {
                    if (! $target->writeStream($targetPath, $stream)) {
                        throw new RuntimeException("Unable to write local:{$targetPath}.");
                    }
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
                $created = true;
            }
            if (! hash_equals($checksum, $this->checksum($target, $targetPath))) {
                if ($created) {
                    $target->delete($targetPath);
                }
                throw new RuntimeException("Checksum verification failed for local:{$targetPath}.");
            }
        }

        return [
            'source_disk' => $sourceDisk,
            'source_path' => $sourcePath,
            'target_path' => $targetPath,
            'checksum' => $checksum,
            'bytes' => $bytes,
            'created' => $created,
            'register' => $sourceDisk !== 'local',
        ];
    }

    private function checksum(Filesystem $disk, string $path): string
    {
        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to checksum {$path}.");
        }
        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);
            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }

    private function targetPath(
        string $table,
        int $id,
        string $role,
        int $tenantId,
        int $groupId,
        string $sourcePath,
        string $checksum,
    ): string {
        if ($this->isCanonicalTargetPath($sourcePath, $tenantId, $groupId)) {
            return $sourcePath;
        }
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if (preg_match('/^[a-z0-9]{1,10}$/', $extension) !== 1) {
            $extension = 'bin';
        }
        $directory = match ($table) {
            'team_documents' => "groups/{$tenantId}/{$groupId}/team-documents",
            'group_files' => "groups/{$tenantId}/{$groupId}/legacy-files",
            default => $role === 'thumbnail'
                ? "groups/{$tenantId}/{$groupId}/media/thumbnails"
                : "groups/{$tenantId}/{$groupId}/media",
        };

        return sprintf('%s/legacy-%d-%s.%s', $directory, $id, substr($checksum, 0, 16), $extension);
    }

    private function isCanonicalTargetPath(string $path, int $tenantId, int $groupId): bool
    {
        if (! str_starts_with($path, "groups/{$tenantId}/{$groupId}/")) {
            return false;
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{target_path: string, created: bool} $copy
     */
    private function compensateTarget(array $copy): void
    {
        if (! $copy['created'] || $this->targetReferenced($copy['target_path'])) {
            return;
        }
        Storage::disk('local')->delete($copy['target_path']);
    }

    private function targetReferenced(string $path): bool
    {
        return DB::table('group_private_storage_migrations')
            ->where('target_disk', 'local')
            ->where('target_path', $path)
            ->exists();
    }

    /**
     * @param object $row
     * @param array<string, mixed> $main
     * @param array<string, mixed>|null $thumbnail
     */
    private function persistMedia(object $row, array $main, ?array $thumbnail): bool
    {
        return DB::transaction(function () use ($row, $main, $thumbnail): bool {
            $current = DB::table('group_media')
                ->where('id', (int) $row->id)
                ->where('tenant_id', (int) $row->tenant_id)
                ->lockForUpdate()
                ->first();
            if ($current === null) {
                throw new RuntimeException('Media row disappeared during migration.');
            }
            $targetThumbnail = $thumbnail['target_path'] ?? null;
            if ((string) ($current->file_path ?? '') === $main['target_path']
                && $this->nullableString($current->thumbnail_path ?? null) === $targetThumbnail
                && $this->nullableString($current->url ?? null) === null
                && $this->copyAlreadyRegistered('group_media', (int) $row->id, 'file', $main)
                && ($thumbnail === null
                    || $this->copyAlreadyRegistered('group_media', (int) $row->id, 'thumbnail', $thumbnail))) {
                return false;
            }
            if ($this->nullableString($current->file_path ?? null) !== $this->nullableString($row->file_path)
                || $this->nullableString($current->thumbnail_path ?? null) !== $this->nullableString($row->thumbnail_path)
                || $this->nullableString($current->url ?? null) !== $this->nullableString($row->url)) {
                throw new RuntimeException('Media row changed concurrently; no repoint was committed.');
            }

            $updates = [
                'file_path' => $main['target_path'],
                'thumbnail_path' => $targetThumbnail,
                'url' => null,
                'file_size' => $main['bytes'],
            ];
            if (Schema::hasColumn('group_media', 'updated_at')) {
                $updates['updated_at'] = now();
            }
            DB::table('group_media')
                ->where('id', (int) $row->id)
                ->where('tenant_id', (int) $row->tenant_id)
                ->update($updates);
            $this->registerCopy('group_media', (int) $row->id, 'file', (int) $row->tenant_id, (int) $row->group_id, $main);
            if ($thumbnail !== null) {
                $this->registerCopy('group_media', (int) $row->id, 'thumbnail', (int) $row->tenant_id, (int) $row->group_id, $thumbnail);
            }

            return true;
        }, 3);
    }

    /** @param object $row @param array<string, mixed> $copy */
    private function persistGroupFile(object $row, array $copy): bool
    {
        return DB::transaction(function () use ($row, $copy): bool {
            $current = DB::table('group_files')
                ->where('id', (int) $row->id)
                ->where('tenant_id', (int) $row->tenant_id)
                ->lockForUpdate()
                ->first();
            if ($current === null) {
                throw new RuntimeException('Group file row disappeared during migration.');
            }
            if ((string) $current->file_path === $copy['target_path']
                && Storage::disk('local')->exists($copy['target_path'])
                && $this->copyAlreadyRegistered('group_files', (int) $row->id, 'file', $copy)) {
                return false;
            }
            if ((string) $current->file_path !== (string) $row->file_path) {
                throw new RuntimeException('Group file row changed concurrently; no repoint was committed.');
            }
            $updates = ['file_path' => $copy['target_path'], 'file_size' => $copy['bytes']];
            if (Schema::hasColumn('group_files', 'updated_at')) {
                $updates['updated_at'] = now();
            }
            DB::table('group_files')
                ->where('id', (int) $row->id)
                ->where('tenant_id', (int) $row->tenant_id)
                ->update($updates);
            $this->registerCopy('group_files', (int) $row->id, 'file', (int) $row->tenant_id, (int) $row->group_id, $copy);

            return true;
        }, 3);
    }

    /** @param object $row @param array<string, mixed> $copy */
    private function persistTeamDocument(object $row, array $copy): bool
    {
        return DB::transaction(function () use ($row, $copy): bool {
            $current = DB::table('team_documents')
                ->where('id', (int) $row->id)
                ->where('tenant_id', (int) $row->tenant_id)
                ->lockForUpdate()
                ->first();
            if ($current === null) {
                throw new RuntimeException('Team document row disappeared during migration.');
            }
            if ($current->group_file_id !== null) {
                return false;
            }
            if ((string) $current->file_path !== (string) $row->file_path) {
                throw new RuntimeException('Team document row changed concurrently; no mapping was committed.');
            }

            $fileId = (int) DB::table('group_files')->insertGetId([
                'group_id' => (int) $row->group_id,
                'tenant_id' => (int) $row->tenant_id,
                'file_name' => $this->safeDisplayName((string) $row->title, $copy['source_path']),
                'file_path' => $copy['target_path'],
                'file_type' => $this->safeMime($row->file_type),
                'file_size' => $copy['bytes'],
                'uploaded_by' => (int) $row->uploaded_by,
                'folder' => 'team-documents',
                'description' => null,
                'download_count' => 0,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => now(),
            ]);
            $this->registerCopy('team_documents', (int) $row->id, 'file', (int) $row->tenant_id, (int) $row->group_id, $copy);
            DB::table('team_documents')
                ->where('id', (int) $row->id)
                ->where('tenant_id', (int) $row->tenant_id)
                ->update(['group_file_id' => $fileId, 'storage_migrated_at' => now()]);

            return true;
        }, 3);
    }

    /** @param array<string, mixed> $copy */
    private function registerCopy(
        string $table,
        int $id,
        string $role,
        int $tenantId,
        int $groupId,
        array $copy,
    ): void {
        if (! $copy['register']) {
            return;
        }
        $existing = DB::table('group_private_storage_migrations')
            ->where('source_table', $table)
            ->where('source_id', $id)
            ->where('asset_role', $role)
            ->lockForUpdate()
            ->first();
        if ($existing !== null) {
            if ((string) $existing->sha256 !== $copy['checksum']
                || (string) $existing->target_path !== $copy['target_path']) {
                throw new RuntimeException('Existing storage migration registry entry conflicts with the verified copy.');
            }
            return;
        }
        $now = now();
        DB::table('group_private_storage_migrations')->insert([
            'tenant_id' => $tenantId,
            'group_id' => $groupId,
            'source_table' => $table,
            'source_id' => $id,
            'asset_role' => $role,
            'source_disk' => $copy['source_disk'],
            'source_path' => $copy['source_path'],
            'target_disk' => 'local',
            'target_path' => $copy['target_path'],
            'sha256' => $copy['checksum'],
            'bytes' => $copy['bytes'],
            'migrated_at' => $now,
            'source_deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array<string, mixed> $copy */
    private function copyAlreadyRegistered(string $table, int $id, string $role, array $copy): bool
    {
        if (! $copy['register']) {
            return true;
        }

        return DB::table('group_private_storage_migrations')
            ->where('source_table', $table)
            ->where('source_id', $id)
            ->where('asset_role', $role)
            ->where('sha256', $copy['checksum'])
            ->where('target_path', $copy['target_path'])
            ->exists();
    }

    private function deleteCommittedSources(): void
    {
        $query = DB::table('group_private_storage_migrations')
            ->whereNull('source_deleted_at')
            ->orderBy('id');
        if ($this->tenantId !== null) {
            $query->where('tenant_id', $this->tenantId);
        }
        $groups = $query->get()->groupBy(static fn (object $row): string => $row->source_disk . "\0" . $row->source_path);

        foreach ($groups as $rows) {
            $first = $rows->first();
            if ($first === null) {
                continue;
            }
            $diskName = (string) $first->source_disk;
            $path = (string) $first->source_path;
            if ($this->unmigratedSourceReferenceExists($diskName, $path)) {
                $this->totals['source_delete_deferred']++;
                $this->totals['failed']++;
                $this->warn("Source deletion deferred because an unmigrated row still references {$diskName}:{$path}.");
                continue;
            }
            try {
                $disk = Storage::disk($diskName);
                if (! $disk->exists($path)) {
                    $this->totals['source_already_absent']++;
                    $this->markSourcesDeleted($diskName, $path);
                    continue;
                }
                $deleted = $disk->delete($path);
                if (! $deleted || $disk->exists($path)) {
                    throw new RuntimeException("Storage adapter did not delete {$diskName}:{$path}.");
                }
                $this->markSourcesDeleted($diskName, $path);
                $this->totals['source_deleted']++;
            } catch (Throwable $exception) {
                $this->totals['failed']++;
                $this->error("Source deletion failed for {$diskName}:{$path}: {$exception->getMessage()}");
            }
        }
    }

    private function markSourcesDeleted(string $disk, string $path): void
    {
        DB::table('group_private_storage_migrations')
            ->where('source_disk', $disk)
            ->where('source_path', $path)
            ->whereNull('source_deleted_at')
            ->update(['source_deleted_at' => now(), 'updated_at' => now()]);
    }

    private function unmigratedSourceReferenceExists(string $disk, string $path): bool
    {
        $variants = $this->storedPathVariants($disk, $path);
        $mediaFiles = DB::table('group_media')
            ->whereIn('file_path', $variants)
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')
                    ->from('group_private_storage_migrations as migration')
                    ->whereColumn('migration.source_id', 'group_media.id')
                    ->where('migration.source_table', 'group_media')
                    ->where('migration.asset_role', 'file');
            });
        $mediaThumbnails = DB::table('group_media')
            ->whereIn('thumbnail_path', $variants)
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')
                    ->from('group_private_storage_migrations as migration')
                    ->whereColumn('migration.source_id', 'group_media.id')
                    ->where('migration.source_table', 'group_media')
                    ->where('migration.asset_role', 'thumbnail');
            });
        $files = DB::table('group_files')
            ->whereIn('file_path', $variants)
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')
                    ->from('group_private_storage_migrations as migration')
                    ->whereColumn('migration.source_id', 'group_files.id')
                    ->where('migration.source_table', 'group_files')
                    ->where('migration.asset_role', 'file');
            });
        $documents = DB::table('team_documents')->whereNull('group_file_id')->whereIn('file_path', $variants);
        return $mediaFiles->exists()
            || $mediaThumbnails->exists()
            || $files->exists()
            || $documents->exists();
    }

    /** @return list<string> */
    private function storedPathVariants(string $disk, string $path): array
    {
        $variants = [$path, '/' . $path];
        if ($disk === 'public') {
            $variants[] = 'storage/' . $path;
            $variants[] = '/storage/' . $path;
        } elseif ($disk === 'uploads') {
            $variants[] = 'uploads/' . $path;
            $variants[] = '/uploads/' . $path;
        } elseif ($disk === 'legacy_httpdocs') {
            $variants[] = '/' . $path;
        }

        return array_values(array_unique($variants));
    }

    private function validTeamDocumentOwner(object $row): bool
    {
        return DB::table('groups')
            ->where('id', (int) $row->group_id)
            ->where('tenant_id', (int) $row->tenant_id)
            ->exists()
            && DB::table('users')
                ->where('id', (int) $row->uploaded_by)
                ->where('tenant_id', (int) $row->tenant_id)
                ->exists();
    }

    private function safeDisplayName(string $title, string $sourcePath): string
    {
        $withoutSeparators = str_replace(['\\', '/'], ' ', $title);
        $name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $withoutSeparators) ?? '');
        if ($name === '') {
            $name = basename($sourcePath);
        }

        return mb_substr($name, 0, 255);
    }

    private function safeMime(mixed $value): string
    {
        $mime = strtolower(trim((string) $value));
        if ($mime === '' || mb_strlen($mime) > 50 || preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#', $mime) !== 1) {
            return 'application/octet-stream';
        }

        return $mime;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function issue(string $table, int $id, string $reason, string $message): void
    {
        $this->totals[$reason]++;
        if ($this->apply) {
            $this->totals['failed']++;
        }
        $this->warn("{$table} {$id}: {$message} [{$reason}]");
    }

    private function failure(string $table, int $id, Throwable $exception): void
    {
        $this->totals['failed']++;
        $this->error("{$table} {$id}: {$exception->getMessage()}");
    }

    private function renderSummary(): void
    {
        $this->newLine();
        $this->line('Groups private-storage migration summary:');
        foreach ($this->totals as $label => $count) {
            $this->line(sprintf('  %-32s %d', $label, $count));
        }
    }
}
