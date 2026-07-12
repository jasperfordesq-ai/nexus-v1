<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Backward-compatible facade for the retired team-document storage model.
 *
 * New writes and reads use the canonical private GroupFileService pipeline.
 * The legacy team_documents table is retained only as a migration registry for
 * pre-existing public files; no endpoint returns a physical storage path.
 */
final class TeamDocumentService
{
    private const FOLDER = 'team-documents';

    /** @var list<array{code: string, message: string, field?: string}> */
    private array $errors = [];

    public function __construct(
        private readonly GroupFileService $fileService = new GroupFileService(),
    ) {}

    /** @return list<array{code: string, message: string, field?: string}> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array{items: list<array<string, mixed>>, cursor: string|null, has_more: bool}
     */
    public function getDocuments(int $groupId, array $filters = [], ?int $userId = null): array
    {
        $this->errors = [];
        if ($userId === null || $userId < 1) {
            $this->errors[] = [
                'code' => 'FORBIDDEN',
                'message' => __('api.group_member_required_view_discussions'),
            ];

            return ['items' => [], 'cursor' => null, 'has_more' => false];
        }

        $cursor = $filters['cursor'] ?? null;
        // The retired endpoint originally accepted a plain numeric cursor. Keep
        // that input compatible while emitting only the canonical opaque cursor.
        if (is_string($cursor) && ctype_digit($cursor)) {
            $cursor = base64_encode($cursor);
        }

        $result = $this->fileService->list($groupId, $userId, [
            'limit' => max(1, min((int) ($filters['limit'] ?? 50), 100)),
            'cursor' => $cursor,
            'folder' => self::FOLDER,
        ]);
        if ($result === null) {
            $this->errors = $this->fileService->getErrors();

            return ['items' => [], 'cursor' => null, 'has_more' => false];
        }

        $items = array_map(
            static fn (array $file): array => [
                'id' => (int) $file['id'],
                'group_id' => (int) $file['group_id'],
                'title' => (string) ($file['description'] ?: $file['file_name']),
                // Compatibility field now points at an authenticated controller
                // route. It never reveals the private filesystem key.
                'file_path' => sprintf(
                    '/api/v2/groups/%d/files/%d/download',
                    (int) $file['group_id'],
                    (int) $file['id'],
                ),
                'download_url' => sprintf(
                    '/api/v2/groups/%d/files/%d/download',
                    (int) $file['group_id'],
                    (int) $file['id'],
                ),
                'file_type' => (string) $file['file_type'],
                'file_size' => (int) $file['file_size'],
                'uploaded_by' => (int) $file['uploaded_by'],
                'created_at' => (string) $file['created_at'],
                'capabilities' => $file['capabilities'],
            ],
            $result['items'],
        );

        return [
            'items' => $items,
            'cursor' => $result['cursor'],
            'has_more' => $result['has_more'],
        ];
    }

    /**
     * Store a compatibility upload through the canonical private Groups file
     * validator, quota checks, authorization, and storage compensation.
     */
    public function upload(int $groupId, int $userId, array $fileData, ?string $title = null): ?int
    {
        $this->errors = [];
        $temporaryPath = $fileData['tmp_name'] ?? null;
        if (! is_string($temporaryPath) || $temporaryPath === '' || ! is_file($temporaryPath)) {
            $this->errors[] = [
                'code' => 'VALIDATION_ERROR',
                'message' => __('svc_notifications_2.team_document.no_file_provided'),
                'field' => 'file',
            ];

            return null;
        }

        $uploadError = (int) ($fileData['error'] ?? UPLOAD_ERR_OK);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $this->errors[] = [
                'code' => 'VALIDATION_ERROR',
                'message' => __('svc_notifications_2.team_document.file_upload_failed'),
                'field' => 'file',
            ];

            return null;
        }

        $name = (string) ($fileData['name'] ?? basename($temporaryPath));
        $file = new UploadedFile(
            $temporaryPath,
            $name,
            is_string($fileData['type'] ?? null) ? $fileData['type'] : null,
            $uploadError,
            true,
        );

        $result = $this->fileService->upload($groupId, $userId, [
            'file' => $file,
            'folder' => self::FOLDER,
            'description' => $title,
        ]);
        if ($result === null) {
            $this->errors = $this->fileService->getErrors();

            return null;
        }

        return (int) $result['id'];
    }

    /** Delete only canonical files created/migrated for this compatibility surface. */
    public function delete(int $documentId, int $userId): bool
    {
        $this->errors = [];
        $tenantId = (int) TenantContext::getId();
        $legacy = DB::table('team_documents')
            ->where('id', $documentId)
            ->where('tenant_id', $tenantId)
            ->first(['id', 'group_file_id']);
        if ($legacy !== null && $legacy->group_file_id === null) {
            // An unmigrated public record is not safe to treat as a canonical
            // group_files ID: the two auto-increment domains can collide.
            $this->errors[] = [
                'code' => 'RESOURCE_NOT_FOUND',
                'message' => __('svc_notifications_2.team_document.document_not_found'),
            ];

            return false;
        }
        $fileId = $legacy !== null && $legacy->group_file_id !== null
            ? (int) $legacy->group_file_id
            : $documentId;

        $file = DB::table('group_files')
            ->where('id', $fileId)
            ->where('tenant_id', $tenantId)
            ->where('folder', self::FOLDER)
            ->first(['id', 'group_id']);

        if ($file === null) {
            $this->errors[] = [
                'code' => 'RESOURCE_NOT_FOUND',
                'message' => __('svc_notifications_2.team_document.document_not_found'),
            ];

            return false;
        }

        if (! $this->fileService->delete((int) $file->group_id, $fileId, $userId)) {
            $this->errors = $this->fileService->getErrors();

            return false;
        }

        DB::table('team_documents')
            ->where('tenant_id', $tenantId)
            ->where('group_file_id', $fileId)
            ->delete();

        return true;
    }
}
