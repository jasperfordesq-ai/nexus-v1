<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Helpers\UrlHelper;
use App\Services\CommentService;
use App\Services\ReactionService;
use App\Services\SocialNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Resources — accessible (GOV.UK) frontend parity methods.
 *
 * Brings the accessible resource library up to functional parity with the
 * React ResourcesPage: hierarchical category tree, flat category filter,
 * cursor pagination, rich metadata (uploader / size / date / downloads),
 * file-type icons, an authenticated streamed download (with counter), a
 * file upload form, an owner/admin delete confirmation, and admin reorder.
 *
 * Each method calls the SAME data/logic the React API controllers
 * (ResourcePublicController / ResourceCategoryController) use, mirrored here
 * for server-rendered HTML-first pages. New method names are module-prefixed
 * (resources*) and unique across AlphaController and every sibling trait.
 */
trait ResourcesParity
{
    /** Allowed upload extensions — mirrors ResourcePublicController::store(). */
    private const RESOURCES_ALLOWED_EXTS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'jpg', 'png', 'gif', 'webp'];

    /** Max upload size in bytes (10MB) — mirrors ResourcePublicController::store(). */
    private const RESOURCES_MAX_BYTES = 10 * 1024 * 1024;

    /**
     * GET /resources/library
     *
     * The full resource library: category tree sidebar + flat category filter,
     * search, cursor pagination, and rich resource cards. Read-only data path
     * mirrors ResourcePublicController::index() + ResourceCategoryController::tree().
     */
    public function resourcesLibrary(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('resources'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $q = trim(self::asStr($request->query('q')));
        $categoryId = (int) self::asStr($request->query('category_id'));
        if ($categoryId < 0) {
            $categoryId = 0;
        }
        $cursor = trim(self::asStr($request->query('cursor')));
        $reorder = $request->query('reorder') === '1';

        $error = false;
        $resources = [];
        $hasMore = false;
        $nextCursor = null;
        $categoryTree = [];
        $flatCategories = [];

        try {
            $categoryTree = $this->resourcesCategoryTree($tenantId);
            $flatCategories = $this->resourcesFlatCategories($tenantId);
            [$resources, $hasMore, $nextCursor] = $this->resourcesFetchPage($tenantId, $q, $categoryId, $cursor);
        } catch (\Throwable $e) {
            report($e);
            $error = true;
        }

        // Batch-load reaction + comment counts for all resources on this page
        // to avoid N+1 queries (mirrors how blogreviews loads counts).
        $resourceIds = array_column($resources, 'id');
        $reactionCountsByResource = [];
        $commentCountsByResource = [];
        if (!empty($resourceIds)) {
            try {
                // Reaction counts: group by target_id for all resource ids.
                $reactionRows = DB::table('reactions')
                    ->whereIn('target_id', $resourceIds)
                    ->where('target_type', 'resource')
                    ->select('target_id', DB::raw('COUNT(*) as total'))
                    ->groupBy('target_id')
                    ->get();
                foreach ($reactionRows as $row) {
                    $reactionCountsByResource[(int) $row->target_id] = (int) $row->total;
                }
            } catch (\Throwable $e) {
                report($e);
            }
            try {
                // Comment counts (top-level + replies) — comments table.
                $commentRows = DB::table('comments')
                    ->whereIn('target_id', $resourceIds)
                    ->where('target_type', 'resource')
                    ->select('target_id', DB::raw('COUNT(*) as total'))
                    ->groupBy('target_id')
                    ->get();
                foreach ($commentRows as $row) {
                    $commentCountsByResource[(int) $row->target_id] = (int) $row->total;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $isAdmin = $this->resourcesUserIsAdmin();

        return $this->view('accessible-frontend::resources-library', [
            'title' => __('govuk_alpha_resources.library.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'status' => self::asStr($request->query('status')) ?: null,
            'resources' => $resources,
            'categoryTree' => $categoryTree,
            'flatCategories' => $flatCategories,
            'selectedCategory' => $categoryId,
            'searchQuery' => $q,
            'hasMore' => $hasMore,
            'nextCursor' => $nextCursor,
            'error' => $error,
            'isAdmin' => $isAdmin,
            'reorderMode' => $reorder && $isAdmin,
            'currentUserId' => $userId,
            'reactionCountsByResource' => $reactionCountsByResource,
            'commentCountsByResource' => $commentCountsByResource,
        ]);
    }

    /**
     * GET /resources/upload — upload form.
     */
    public function resourcesUploadForm(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('resources'), 403);
        if ($this->currentUserId() === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $flatCategories = [];
        try {
            $flatCategories = $this->resourcesFlatCategories($tenantId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::resources-upload', [
            'title' => __('govuk_alpha_resources.upload.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'status' => self::asStr($request->query('status')) ?: null,
            'flatCategories' => $flatCategories,
            'maxSizeLabel' => '10MB',
            'allowedLabel' => strtoupper(implode(', ', self::RESOURCES_ALLOWED_EXTS)),
        ]);
    }

    /**
     * POST /resources/upload — store an uploaded resource.
     *
     * Mirrors ResourcePublicController::store(): required title, valid file,
     * extension + MIME allowlist, 10MB cap, cryptographically-random filename,
     * tenant-scoped destination directory.
     */
    public function resourcesUpload(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('resources'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();

        $title = trim(self::asStr($request->input('title')));
        $description = trim(self::asStr($request->input('description')));
        $categoryRaw = self::asStr($request->input('category_id'));
        $categoryId = $categoryRaw !== '' ? (int) $categoryRaw : null;

        // Tenant-scope the category_id to prevent cross-tenant assignment (IDOR):
        // the form filters categories by tenant, but a direct POST could submit
        // any id. A category is only valid if it belongs to THIS tenant (the flat
        // `categories` table type=resource, or the `resource_categories` tree).
        // An id that matches neither is silently dropped (resource gets no category).
        if ($categoryId !== null) {
            $catOk = DB::table('categories')
                    ->where('id', $categoryId)->where('tenant_id', $tenantId)->exists()
                || DB::table('resource_categories')
                    ->where('id', $categoryId)->where('tenant_id', $tenantId)->exists();
            if (!$catOk) {
                $categoryId = null;
            }
        }

        $errors = [];
        if ($title === '') {
            $errors['title'] = __('govuk_alpha_resources.upload.error_title_required');
        }

        $file = $request->file('file');
        if ($file === null || is_array($file) || !$file->isValid()) {
            $errors['file'] = __('govuk_alpha_resources.upload.error_file_required');
        }

        $tmpPath = null;
        $ext = '';
        $fileSize = 0;
        $fileType = null;

        if (!isset($errors['file']) && $file !== null && !is_array($file)) {
            $tmpPath = $file->getPathname();
            if (!is_string($tmpPath) || !file_exists($tmpPath)) {
                $errors['file'] = __('govuk_alpha_resources.upload.error_upload_failed');
            } else {
                $fileSize = (int) filesize($tmpPath);
                if ($fileSize > self::RESOURCES_MAX_BYTES) {
                    $errors['file'] = __('govuk_alpha_resources.upload.error_too_large');
                } else {
                    $ext = strtolower((string) $file->getClientOriginalExtension());
                    if (!in_array($ext, self::RESOURCES_ALLOWED_EXTS, true)) {
                        $errors['file'] = __('govuk_alpha_resources.upload.error_type');
                    } else {
                        $finfo = new \finfo(FILEINFO_MIME_TYPE);
                        $detectedMime = $finfo->file($tmpPath) ?: null;
                        $allowedMimesByExt = [
                            'pdf'  => ['application/pdf'],
                            'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/x-cfb'],
                            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
                            'xls'  => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/x-cfb'],
                            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
                            'txt'  => ['text/plain'],
                            'csv'  => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
                            'jpg'  => ['image/jpeg'],
                            'png'  => ['image/png'],
                            'gif'  => ['image/gif'],
                            'webp' => ['image/webp'],
                        ];
                        if (!$detectedMime || !in_array($detectedMime, $allowedMimesByExt[$ext] ?? [], true)) {
                            $errors['file'] = __('govuk_alpha_resources.upload.error_type');
                        } else {
                            $fileType = $detectedMime;
                        }
                    }
                }
            }
        }

        if (!empty($errors)) {
            return redirect()
                ->route('govuk-alpha.resources.upload.form', ['tenantSlug' => $tenantSlug])
                ->withErrors($errors)
                ->withInput($request->only(['title', 'description', 'category_id']));
        }

        try {
            $filename = bin2hex(random_bytes(16)) . '.' . $ext;
            $uploadDir = base_path('httpdocs/uploads/' . $tenantId . '/resources');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $file->move($uploadDir, $filename);

            DB::table('resources')->insert([
                'tenant_id'   => $tenantId,
                'user_id'     => $userId,
                'category_id' => $categoryId,
                'title'       => $title,
                'description' => $description,
                'file_path'   => $filename,
                'file_type'   => $fileType,
                'file_size'   => $fileSize,
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return redirect()
                ->route('govuk-alpha.resources.upload.form', ['tenantSlug' => $tenantSlug])
                ->with('status', 'resource-upload-failed')
                ->withInput($request->only(['title', 'description', 'category_id']));
        }

        return redirect()->route('govuk-alpha.resources.library', [
            'tenantSlug' => $tenantSlug,
            'status' => 'resource-uploaded',
        ]);
    }

    /**
     * GET /resources/{id}/delete — owner/admin delete confirmation page.
     */
    public function resourcesDeleteConfirm(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('resources'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $resource = DB::table('resources')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        // Cross-tenant or missing → 404.
        abort_if($resource === null, 404);

        // Non-owner, non-admin → 403.
        abort_unless((int) $resource->user_id === $userId || $this->resourcesUserIsAdmin(), 403);

        return $this->view('accessible-frontend::resources-delete', [
            'title' => __('govuk_alpha_resources.delete.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'resourceId' => (int) $resource->id,
            'resourceTitle' => (string) ($resource->title ?? ''),
        ]);
    }

    /**
     * POST /resources/{id}/delete — delete a resource (owner or admin only).
     *
     * Mirrors ResourcePublicController::destroy(): tenant scope, ownership/admin
     * check, best-effort file removal constrained to the uploads directory.
     */
    public function resourcesDelete(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('resources'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $resource = DB::table('resources')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        abort_if($resource === null, 404);
        abort_unless((int) $resource->user_id === $userId || $this->resourcesUserIsAdmin(), 403);

        try {
            $filePath = self::asStr($resource->file_path ?? '');
            if ($filePath !== '') {
                $uploadsDir = realpath(base_path('httpdocs/uploads'));
                if (str_starts_with($filePath, '/uploads/')) {
                    $fullPath = realpath(base_path('httpdocs' . $filePath));
                } else {
                    $fullPath = realpath(base_path('httpdocs/uploads/' . $tenantId . '/resources/' . $filePath));
                }
                if ($fullPath && $uploadsDir && str_starts_with($fullPath, $uploadsDir) && file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }

            DB::table('resources')->where('id', $id)->where('tenant_id', $tenantId)->delete();
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('govuk-alpha.resources.library', [
                'tenantSlug' => $tenantSlug,
                'status' => 'resource-delete-failed',
            ]);
        }

        return redirect()->route('govuk-alpha.resources.library', [
            'tenantSlug' => $tenantSlug,
            'status' => 'resource-deleted',
        ]);
    }

    /**
     * GET /resources/{id}/download — authenticated streamed download.
     *
     * Mirrors ResourcePublicController::download(): tenant scope, path
     * containment inside the uploads directory, download-counter increment,
     * friendly filename derived from the title.
     */
    public function resourcesDownload(Request $request, string $tenantSlug, int $id): StreamedResponse|Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('resources'), 403);
        if ($this->currentUserId() === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $resource = DB::table('resources')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->select('id', 'file_path', 'file_type', 'title')
            ->first();

        abort_if($resource === null, 404);

        $filePath = self::asStr($resource->file_path ?? '');
        abort_if($filePath === '', 404);

        if (str_starts_with($filePath, '/uploads/')) {
            $fullPath = realpath(base_path('httpdocs' . $filePath));
        } else {
            $fullPath = realpath(base_path('httpdocs/uploads/' . $tenantId . '/resources/' . $filePath));
        }

        $uploadsDir = realpath(base_path('httpdocs/uploads'));
        abort_unless($fullPath && $uploadsDir && str_starts_with($fullPath, $uploadsDir) && file_exists($fullPath), 404);

        DB::table('resources')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->increment('downloads');

        $ext = pathinfo($fullPath, PATHINFO_EXTENSION);
        $safeTitle = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', self::asStr($resource->title ?? 'download'));
        $safeTitle = preg_replace('/\s+/', '_', trim((string) $safeTitle));
        $downloadName = ($safeTitle !== '' ? $safeTitle : 'download') . ($ext !== '' ? '.' . $ext : '');

        $mimeType = self::asStr($resource->file_type ?? '') ?: (mime_content_type($fullPath) ?: 'application/octet-stream');
        $fileSize = filesize($fullPath);

        return response()->streamDownload(function () use ($fullPath) {
            readfile($fullPath);
        }, $downloadName, [
            'Content-Type'   => $mimeType,
            'Content-Length' => (string) $fileSize,
            'Cache-Control'  => 'no-cache, must-revalidate',
        ]);
    }

    /**
     * POST /resources/reorder — admin: move one resource up or down.
     *
     * Mirrors ResourceCategoryController::reorder() (persists r.sort_order).
     * The HTML page submits a single {resource_id, direction} move; we compute
     * the new ordering server-side and persist every row's sort_order.
     */
    public function resourcesReorder(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('resources'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }
        // Reorder is admin-only — non-admins get 403 (matches the React gate).
        abort_unless($this->resourcesUserIsAdmin(), 403);

        $tenantId = TenantContext::getId();
        $resourceId = (int) self::asStr($request->input('resource_id'));
        $direction = $this->allowed(self::asStr($request->input('direction')), ['up', 'down'], 'up');

        // Preserve the current browse filters so the page returns to the same view.
        $redirectParams = array_filter([
            'tenantSlug' => $tenantSlug,
            'q' => trim(self::asStr($request->input('q'))) ?: null,
            'category_id' => ((int) self::asStr($request->input('category_id'))) ?: null,
            'reorder' => '1',
        ], static fn ($v) => $v !== null);

        try {
            // Current ordering, same sort key the index uses (sort_order, id desc).
            $rows = DB::table('resources')
                ->where('tenant_id', $tenantId)
                ->orderBy('sort_order')
                ->orderByDesc('id')
                ->pluck('id')
                ->map(static fn ($v) => (int) $v)
                ->all();

            $index = array_search($resourceId, $rows, true);
            if ($index === false) {
                abort(404);
            }

            $swapWith = $direction === 'up' ? $index - 1 : $index + 1;
            if ($swapWith >= 0 && $swapWith < count($rows)) {
                [$rows[$index], $rows[$swapWith]] = [$rows[$swapWith], $rows[$index]];

                DB::beginTransaction();
                try {
                    foreach ($rows as $position => $rid) {
                        DB::table('resources')
                            ->where('id', $rid)
                            ->where('tenant_id', $tenantId)
                            ->update(['sort_order' => $position]);
                    }
                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    throw $e;
                }
            }
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            $redirectParams['status'] = 'resource-reorder-failed';
            return redirect()->route('govuk-alpha.resources.library', $redirectParams);
        }

        return redirect()->route('govuk-alpha.resources.library', $redirectParams);
    }

    // =====================================================================
    // Social interactions — like/react + comment thread per resource
    // =====================================================================

    /**
     * Curated accessible reaction set for resource surfaces.
     * Subset of ReactionService::VALID_TYPES; matches the React SocialInteractionPanel.
     */
    private const ALPHA_RESOURCES_REACTIONS = [
        'like'      => "\u{1F44D}", // thumbs up
        'love'      => "\u{2764}\u{FE0F}", // heart
        'laugh'     => "\u{1F602}", // tears of joy
        'wow'       => "\u{1F62E}", // astonished
        'sad'       => "\u{1F622}", // crying
        'celebrate' => "\u{1F389}", // party popper
    ];

    /**
     * Toggle an emoji reaction on a resource.  Mirrors the React
     * SocialInteractionPanel like-button (targetType='resource').
     */
    public function resourcesReact(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('resources'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        // Scope the resource to this tenant to prevent cross-tenant reactions.
        $exists = DB::table('resources')->where('id', $id)->where('tenant_id', $tenantId)->exists();
        abort_if(!$exists, 404);

        $emoji = self::asStr($request->input('emoji'));
        $status = $this->resourcesToggleReaction($userId, $id, $emoji);

        return redirect()->route('govuk-alpha.resources.comments', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $status,
        ])->withFragment('resource-reactions');
    }

    /**
     * Comment thread for a resource: list + add-comment form.
     * Mirrors the React SocialInteractionPanel comment thread (targetType='resource').
     */
    public function resourcesComments(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('resources'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $resource = DB::table('resources')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first(['id', 'title', 'description', 'file_path']);

        abort_if($resource === null, 404);

        $comments = [];
        $reactions = ['counts' => [], 'total' => 0, 'user_reaction' => null];
        try {
            $comments = CommentService::getForEntity('resource', $id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            $reactions = app(ReactionService::class)->getReactions($id, 'resource', $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::resources-comments', [
            'title' => __('govuk_alpha_resources.social.comments_title', ['title' => (string) ($resource->title ?? '')]),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'resource' => (array) $resource,
            'resourceId' => $id,
            'comments' => is_array($comments) ? $comments : [],
            'commentsCount' => CommentService::countAll(is_array($comments) ? $comments : []),
            'currentUserId' => $userId,
            'alphaReactions' => self::ALPHA_RESOURCES_REACTIONS,
            'resourceReactionCounts' => (array) ($reactions['counts'] ?? []),
            'resourceReactionTotal' => (int) ($reactions['total'] ?? 0),
            'resourceUserReaction' => $reactions['user_reaction'] ?? null,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * POST — add a comment (or reply) to a resource.
     * Uses /comments/add path to avoid colliding with the GET comments thread.
     */
    public function resourcesStoreComment(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('resources'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $exists = DB::table('resources')->where('id', $id)->where('tenant_id', $tenantId)->exists();
        abort_if(!$exists, 404);

        $body = trim(self::asStr($request->input('body')));
        $parentRaw = self::asStr($request->input('parent_id'));
        $parentId = ctype_digit($parentRaw) && (int) $parentRaw > 0 ? (int) $parentRaw : null;

        if ($body === '') {
            return $this->resourcesCommentsRedirect($tenantSlug, $id, 'comment-invalid');
        }

        $status = 'comment-failed';
        try {
            $result = CommentService::addComment(
                $userId,
                (int) $tenantId,
                'resource',
                $id,
                mb_substr($body, 0, 5000),
                $parentId
            );
            $status = !empty($result['success']) ? ($parentId !== null ? 'reply-added' : 'comment-added') : 'comment-failed';
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->resourcesCommentsRedirect($tenantSlug, $id, $status);
    }

    /**
     * POST — delete a comment on a resource (owner-only; CommentService is owner-scoped).
     */
    public function resourcesDeleteComment(Request $request, string $tenantSlug, int $id, int $commentId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('resources'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $status = 'comment-delete-failed';
        try {
            $status = CommentService::delete($commentId, $userId) > 0 ? 'comment-deleted' : 'comment-delete-failed';
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->resourcesCommentsRedirect($tenantSlug, $id, $status, 'comments');
    }

    // ---------------------------------------------------------------
    // Private social helpers (resources-prefixed)
    // ---------------------------------------------------------------

    /**
     * Toggle a reaction on a resource; fires a best-effort like notification.
     * Only the curated accessible reaction set is accepted.
     */
    private function resourcesToggleReaction(int $userId, int $resourceId, string $emoji): string
    {
        if (!array_key_exists($emoji, self::ALPHA_RESOURCES_REACTIONS)
            || !in_array($emoji, ReactionService::VALID_TYPES, true)) {
            return 'reaction-failed';
        }

        try {
            $result = app(ReactionService::class)->toggleReaction($resourceId, 'resource', $emoji, $userId);
            $status = ($result['action'] ?? '') === 'removed' ? 'reaction-removed' : 'reaction-added';

            if ($status === 'reaction-added') {
                try {
                    $ownerId = SocialNotificationService::getContentOwnerId('resource', $resourceId);
                    if ($ownerId && $ownerId !== $userId) {
                        $recipient = \App\Models\User::query()
                            ->where('id', $ownerId)
                            ->where('tenant_id', TenantContext::getId())
                            ->first(['id', 'preferred_language']);
                        \App\I18n\LocaleContext::withLocale($recipient, function () use ($ownerId, $userId, $resourceId, $emoji): void {
                            SocialNotificationService::notifyLike($ownerId, $userId, 'resource', $resourceId, $emoji);
                        });
                    }
                } catch (\Throwable $e) {
                    Log::warning('Resources reaction notification failed: ' . $e->getMessage());
                }
            }

            return $status;
        } catch (\Throwable $e) {
            report($e);
            return 'reaction-failed';
        }
    }

    /**
     * Redirect back to the resource comment thread with a status + optional anchor.
     */
    private function resourcesCommentsRedirect(string $tenantSlug, int $id, string $status, ?string $fragment = 'comments'): RedirectResponse
    {
        $redirect = redirect()->route('govuk-alpha.resources.comments', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $status,
        ]);

        return $fragment !== null ? $redirect->withFragment($fragment) : $redirect;
    }

    // ---------------------------------------------------------------
    // Private helpers (resources-prefixed; unique across the controller)
    // ---------------------------------------------------------------

    /**
     * Whether the current user is a resources admin (mirrors the React check:
     * role in [admin, super_admin, tenant_admin] OR is_super_admin).
     */
    private function resourcesUserIsAdmin(): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }
        $role = (string) ($user->role ?? 'member');
        if (in_array($role, ['admin', 'super_admin', 'tenant_admin'], true)) {
            return true;
        }
        return (bool) ($user->is_super_admin ?? false);
    }

    /**
     * Hierarchical resource-category tree with per-node resource counts.
     * Mirrors ResourceCategoryController::tree() (the resource_categories table).
     *
     * @return array<int,array<string,mixed>>
     */
    private function resourcesCategoryTree(int $tenantId): array
    {
        $rows = DB::table('resource_categories as rc')
            ->leftJoin('resources as r', function ($join) {
                $join->on('r.category_id', '=', 'rc.id')
                     ->whereColumn('r.tenant_id', 'rc.tenant_id');
            })
            ->where('rc.tenant_id', $tenantId)
            ->select(
                'rc.id', 'rc.name', 'rc.slug', 'rc.parent_id', 'rc.sort_order',
                DB::raw('COUNT(r.id) as resource_count')
            )
            ->groupBy('rc.id', 'rc.name', 'rc.slug', 'rc.parent_id', 'rc.sort_order')
            ->orderBy('rc.sort_order')
            ->orderBy('rc.name')
            ->get();

        $items = $rows->map(static function ($c) {
            return [
                'id'             => (int) $c->id,
                'name'           => (string) $c->name,
                'parent_id'      => $c->parent_id ? (int) $c->parent_id : null,
                'resource_count' => (int) $c->resource_count,
            ];
        })->all();

        return $this->resourcesBuildTree($items, null);
    }

    /**
     * Recursively build a category tree from a flat list.
     *
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    private function resourcesBuildTree(array $items, ?int $parentId): array
    {
        $tree = [];
        foreach ($items as $item) {
            if (($item['parent_id'] ?? null) === $parentId) {
                $item['children'] = $this->resourcesBuildTree($items, (int) $item['id']);
                $tree[] = $item;
            }
        }
        return $tree;
    }

    /**
     * Flat resource-category list with counts (categories.type = 'resource').
     * Mirrors ResourcePublicController::categories().
     *
     * @return array<int,array<string,mixed>>
     */
    private function resourcesFlatCategories(int $tenantId): array
    {
        return DB::table('categories as c')
            ->leftJoin('resources as r', function ($join) use ($tenantId) {
                $join->on('r.category_id', '=', 'c.id')
                     ->where('r.tenant_id', $tenantId);
            })
            ->where('c.tenant_id', $tenantId)
            ->where('c.type', 'resource')
            ->select('c.id', 'c.name', 'c.color', DB::raw('COUNT(r.id) as resource_count'))
            ->groupBy('c.id', 'c.name', 'c.color')
            ->orderBy('c.name')
            ->get()
            ->map(static function ($row) {
                return [
                    'id'             => (int) $row->id,
                    'name'           => (string) $row->name,
                    'color'          => (string) ($row->color ?? 'blue'),
                    'resource_count' => (int) $row->resource_count,
                ];
            })
            ->all();
    }

    /**
     * Fetch one page of resources with rich metadata + cursor pagination.
     * Mirrors ResourcePublicController::index().
     *
     * @return array{0: array<int,array<string,mixed>>, 1: bool, 2: ?string}
     */
    private function resourcesFetchPage(int $tenantId, string $search, int $categoryId, string $cursor): array
    {
        $perPage = 20;

        $query = DB::table('resources as r')
            ->leftJoin('users as u', 'r.user_id', '=', 'u.id')
            ->leftJoin('categories as c', 'r.category_id', '=', 'c.id')
            ->where('r.tenant_id', $tenantId);

        if ($cursor !== '') {
            $decoded = base64_decode($cursor, true);
            if ($decoded !== false && ctype_digit((string) $decoded)) {
                $query->where('r.id', '<', (int) $decoded);
            }
        }

        if ($search !== '') {
            $term = '%' . $search . '%';
            $query->where(function ($w) use ($term) {
                $w->where('r.title', 'LIKE', $term)
                  ->orWhere('r.description', 'LIKE', $term);
            });
        }

        if ($categoryId > 0) {
            $query->where('r.category_id', $categoryId);
        }

        $items = $query
            ->orderByDesc('r.id')
            ->limit($perPage + 1)
            ->select(
                'r.id', 'r.title', 'r.description', 'r.file_path', 'r.file_type', 'r.file_size',
                'r.downloads', 'r.category_id', 'r.user_id', 'r.created_at', 'r.sort_order',
                DB::raw("CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as uploader_name"),
                'u.avatar_url as uploader_avatar',
                'c.name as category_name',
                'c.color as category_color'
            )
            ->get();

        $hasMore = $items->count() > $perPage;
        if ($hasMore) {
            $items->pop();
        }

        $nextCursor = $hasMore && $items->isNotEmpty()
            ? base64_encode((string) $items->last()->id)
            : null;

        $baseUrl = UrlHelper::getBaseUrl();

        $formatted = $items->map(static function ($row) use ($baseUrl, $tenantId) {
            $name = trim((string) ($row->uploader_name ?? ''));
            return [
                'id'           => (int) $row->id,
                'title'        => (string) ($row->title ?? ''),
                'description'  => (string) ($row->description ?? ''),
                'file_path'    => (string) ($row->file_path ?? ''),
                'file_type'    => $row->file_type,
                'file_size'    => (int) ($row->file_size ?? 0),
                'downloads'    => (int) ($row->downloads ?? 0),
                'created_at'   => $row->created_at,
                'uploader_id'  => (int) ($row->user_id ?? 0),
                'uploader_name' => $name !== '' ? $name : null,
                'category_name' => $row->category_name,
                'category_color' => (string) ($row->category_color ?? 'blue'),
            ];
        })->all();

        return [$formatted, $hasMore, $nextCursor];
    }
}
