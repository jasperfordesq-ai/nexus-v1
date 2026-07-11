<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saved collections & appreciation — accessible (GOV.UK) frontend parity methods.
 *
 * Composed into AlphaController. Trait methods may call the controller's
 * private helpers ($this->view, $this->currentUserId, $this->assertTenantSlug,
 * $this->allowed, self::asStr). New method names MUST be module-prefixed and
 * unique across AlphaController and every sibling trait. Resolve services via
 * app(SomeService::class) rather than the constructor.
 *
 * Delivers the React-parity saved-collections + appreciation experiences that
 * the core AlphaController only covers as a flat bookmark list:
 *   - savedMyCollections / savedCreateCollection / savedUpdateCollection /
 *     savedDeleteCollection: the /me/collections grid + CRUD (parity with
 *     react-frontend/src/pages/profile/MyCollectionsPage.tsx).
 *   - savedCollectionDetail / savedRemoveItem: paginated saved-item list with
 *     per-item remove (parity with CollectionDetailPage.tsx).
 *   - savedPublicCollections: another member's public collections
 *     (parity with UserCollectionsView.tsx).
 *   - savedAppreciationWall / savedSendAppreciation / savedReactAppreciation:
 *     a member's public thank-you wall with send form + heart/clap/star
 *     reactions (parity with AppreciationWallPage.tsx + AppreciationModal.tsx).
 *
 * All methods call the same tenant-scoped services the React v2 API uses
 * (App\Services\Social\SavedCollectionService and
 * App\Services\Social\AppreciationService) — no money/auth/notification logic
 * is reimplemented here.
 */
trait SavedCollectionsParity
{
    /** Reaction types an appreciation can carry (mirrors AppreciationService::ALLOWED_REACTIONS). */
    private const SAVED_REACTIONS = ['heart', 'clap', 'star'];

    // =====================================================================
    //  My collections — grid + create
    // =====================================================================

    /**
     * GET /me/collections — the authenticated member's saved collections.
     * Mirrors MyCollectionsPage.tsx: a grid of collections with colour dot,
     * item count, description and public flag, plus an inline create form.
     */
    public function savedMyCollections(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $collections = [];
        try {
            $rows = app(\App\Services\Social\SavedCollectionService::class)->getUserCollections($userId, false);
            $collections = array_map([$this, 'savedCollectionToArray'], $rows);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::saved-collections', [
            'title' => __('govuk_alpha_saved.collections.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'saved',
            'collections' => $collections,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * POST /me/collections — create a new collection.
     */
    public function savedCreateCollection(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $name = trim(self::asStr($request->input('name')));
        $description = trim(self::asStr($request->input('description')));
        $isPublic = $request->boolean('is_public');

        if ($name === '') {
            return redirect()->route('govuk-alpha.saved.collections', [
                'tenantSlug' => $tenantSlug,
                'status' => 'collection-name-required',
            ]);
        }

        try {
            app(\App\Services\Social\SavedCollectionService::class)->createCollection(
                $userId,
                $name,
                $description !== '' ? $description : null,
                $isPublic,
            );
            $status = 'collection-created';
        } catch (\Throwable $e) {
            report($e);
            $status = 'collection-failed';
        }

        return redirect()->route('govuk-alpha.saved.collections', [
            'tenantSlug' => $tenantSlug,
            'status' => $status,
        ]);
    }

    /**
     * POST /me/collections/{id}/update — rename / edit a collection.
     * Cross-tenant or non-owner collections raise a ModelNotFoundException in
     * the service (it scopes by user_id + tenant_id) -> 404.
     */
    public function savedUpdateCollection(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $name = trim(self::asStr($request->input('name')));
        $description = trim(self::asStr($request->input('description')));

        if ($name === '') {
            return redirect()->route('govuk-alpha.saved.collection-detail', [
                'tenantSlug' => $tenantSlug,
                'id' => $id,
                'status' => 'collection-name-required',
            ]);
        }

        try {
            app(\App\Services\Social\SavedCollectionService::class)->updateCollection($id, $userId, [
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'is_public' => $request->boolean('is_public'),
            ]);
            $status = 'collection-updated';
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (\Throwable $e) {
            report($e);
            $status = 'collection-failed';
        }

        return redirect()->route('govuk-alpha.saved.collection-detail', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $status,
        ]);
    }

    /**
     * POST /me/collections/{id}/delete — delete a collection (and its items).
     */
    public function savedDeleteCollection(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        try {
            app(\App\Services\Social\SavedCollectionService::class)->deleteCollection($id, $userId);
            $status = 'collection-deleted';
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (\Throwable $e) {
            report($e);
            $status = 'collection-failed';
        }

        return redirect()->route('govuk-alpha.saved.collections', [
            'tenantSlug' => $tenantSlug,
            'status' => $status,
        ]);
    }

    // =====================================================================
    //  Collection detail — paginated items + per-item remove
    // =====================================================================

    /**
     * GET /me/collections/{id} — paginated saved items in one collection.
     * Mirrors CollectionDetailPage.tsx: each item shows a (linked) preview
     * title, type tag, save date and note, plus a remove button. The owner
     * sees their own private collections; public collections are viewable by
     * anyone (the service enforces the 403 for private non-owner access).
     */
    public function savedCollectionDetail(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $page = max(1, (int) $request->query('page', '1'));
        $perPage = 20;

        $collection = null;
        $items = [];
        $meta = ['current_page' => 1, 'last_page' => 1, 'per_page' => $perPage, 'total' => 0];
        try {
            $result = app(\App\Services\Social\SavedCollectionService::class)
                ->getSavedItems($id, $userId, $page, $perPage);
            $collection = $this->savedCollectionToArray($result['collection']);
            $items = array_map([$this, 'savedItemToArray'], $result['data']);
            $meta = $result['meta'] + $meta;
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Private collection viewed by a non-owner -> 403 from the service.
            abort($e->getStatusCode());
        } catch (\Throwable $e) {
            report($e);
        }

        $isOwner = $collection !== null && (int) ($collection['user_id'] ?? 0) === $userId;

        return $this->view('accessible-frontend::saved-collection-detail', [
            'title' => $collection['name'] ?? __('govuk_alpha_saved.detail.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'saved',
            'collection' => $collection,
            'items' => $items,
            'meta' => $meta,
            'currentPage' => $page,
            'isOwner' => $isOwner,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * POST /me/collections/{id}/items/{itemId}/remove — remove one saved item.
     * The service scopes the saved item by user_id + tenant_id, so attempting
     * to remove another user's item is a no-op (returns false -> not-found
     * status).
     */
    public function savedRemoveItem(Request $request, string $tenantSlug, int $id, int $itemId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $ok = false;
        try {
            $ok = app(\App\Services\Social\SavedCollectionService::class)->unsaveItem($itemId, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.saved.collection-detail', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? 'item-removed' : 'item-remove-failed',
        ]);
    }

    // =====================================================================
    //  Public collections of another member
    // =====================================================================

    /**
     * GET /users/{userId}/collections — another member's public collections.
     * Mirrors UserCollectionsView.tsx. Only public collections show.
     */
    public function savedPublicCollections(Request $request, string $tenantSlug, int $userId): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        if ($this->currentUserId() === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $owner = $this->savedLookupMember($userId);
        if ($owner === null) {
            abort(404);
        }

        $collections = [];
        try {
            $rows = app(\App\Services\Social\SavedCollectionService::class)->getUserCollections($userId, true);
            $collections = array_map([$this, 'savedCollectionToArray'], $rows);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::saved-public-collections', [
            'title' => __('govuk_alpha_saved.public.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'members',
            'ownerId' => $userId,
            'ownerName' => $owner['name'],
            'collections' => $collections,
        ]);
    }

    // =====================================================================
    //  Appreciation wall — view, send, react
    // =====================================================================

    /**
     * GET /users/{userId}/appreciations — a member's public thank-you wall.
     * Mirrors AppreciationWallPage.tsx: each note shows sender, message, date
     * and heart/clap/star reaction buttons with counts; a send form lets the
     * viewer thank this member (parity with AppreciationModal.tsx).
     */
    public function savedAppreciationWall(Request $request, string $tenantSlug, int $userId): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $viewerId = $this->currentUserId();
        if ($viewerId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $member = $this->savedLookupMember($userId);
        if ($member === null) {
            abort(404);
        }

        $page = max(1, (int) $request->query('page', '1'));
        $perPage = 20;

        $items = [];
        $meta = ['current_page' => 1, 'last_page' => 1, 'per_page' => $perPage, 'total' => 0];
        try {
            $result = app(\App\Services\Social\AppreciationService::class)
                ->getReceivedAppreciations($userId, $page, $perPage, true);
            $items = array_map([$this, 'savedAppreciationToArray'], $result['data']);
            $meta = $result['meta'] + $meta;
            $this->savedAttachMyReactions($items, $viewerId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::saved-appreciations', [
            'title' => __('govuk_alpha_saved.wall.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'members',
            'ownerId' => $userId,
            'ownerName' => $member['name'],
            'isSelf' => $userId === $viewerId,
            'appreciations' => $items,
            'reactionTypes' => self::SAVED_REACTIONS,
            'meta' => $meta,
            'currentPage' => $page,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * POST /users/{userId}/appreciations — send a thank-you to this member.
     * Calls AppreciationService::send (which handles self-thank guards, the
     * daily rate limit and the recipient-locale-wrapped notification).
     */
    public function savedSendAppreciation(Request $request, string $tenantSlug, int $userId): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $viewerId = $this->currentUserId();
        if ($viewerId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $member = $this->savedLookupMember($userId);
        if ($member === null) {
            abort(404);
        }

        $message = trim(self::asStr($request->input('message')));
        $isPublic = $request->has('is_public') ? $request->boolean('is_public') : true;

        if ($message === '') {
            return redirect()->route('govuk-alpha.saved.appreciations', [
                'tenantSlug' => $tenantSlug,
                'userId' => $userId,
                'status' => 'appreciation-message-required',
            ]);
        }

        $status = 'appreciation-sent';
        try {
            app(\App\Services\Social\AppreciationService::class)->send(
                $viewerId,
                $userId,
                $message,
                'general',
                null,
                $isPublic,
            );
        } catch (\App\Exceptions\SafeguardingPolicyException $e) {
            $status = $e->reasonCode === 'SAFEGUARDING_POLICY_UNAVAILABLE'
                ? 'appreciation-safeguarding-unavailable'
                : 'appreciation-safeguarding-restricted';
        } catch (\DomainException $e) {
            $status = match ($e->getMessage()) {
                'cannot_thank_self'   => 'appreciation-self',
                'message_too_long'    => 'appreciation-too-long',
                'rate_limit_exceeded' => 'appreciation-rate-limited',
                default               => 'appreciation-failed',
            };
        } catch (\Throwable $e) {
            report($e);
            $status = 'appreciation-failed';
        }

        return redirect()->route('govuk-alpha.saved.appreciations', [
            'tenantSlug' => $tenantSlug,
            'userId' => $userId,
            'status' => $status,
        ]);
    }

    /**
     * POST /appreciations/{id}/react — toggle a heart/clap/star reaction.
     * The service scopes the appreciation by tenant_id (cross-tenant -> 404).
     */
    public function savedReactAppreciation(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $viewerId = $this->currentUserId();
        if ($viewerId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $reaction = $this->allowed($request->input('reaction_type'), self::SAVED_REACTIONS, '');

        // Resolve where to return: the wall this reaction belongs to.
        $appreciation = DB::table('appreciations')
            ->where('id', $id)
            ->where('tenant_id', TenantContext::getId())
            ->select(['receiver_id'])
            ->first();
        if ($appreciation === null) {
            abort(404);
        }
        $ownerId = (int) $request->input('owner_id');
        if ($ownerId <= 0) {
            $ownerId = (int) $appreciation->receiver_id;
        }

        $status = 'reaction-updated';
        if ($reaction === '') {
            $status = 'reaction-failed';
        } else {
            try {
                app(\App\Services\Social\AppreciationService::class)->react($id, $viewerId, (string) $reaction);
            } catch (ModelNotFoundException) {
                abort(404);
            } catch (\Throwable $e) {
                report($e);
                $status = 'reaction-failed';
            }
        }

        return redirect()->to(
            route('govuk-alpha.saved.appreciations', [
                'tenantSlug' => $tenantSlug,
                'userId' => $ownerId,
                'status' => $status,
            ]) . '#appreciation-' . $id
        );
    }

    // =====================================================================
    //  Private helpers (prefixed; safe array coercion for blades)
    // =====================================================================

    /**
     * Look up a member in the current tenant by id. Returns null when absent
     * (so callers 404) — keeps cross-tenant/non-existent ids out of the view.
     *
     * @return array{id:int,name:string}|null
     */
    private function savedLookupMember(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        $row = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', TenantContext::getId())
            ->select(['id', 'name'])
            ->first();
        if ($row === null) {
            return null;
        }
        return [
            'id' => (int) $row->id,
            'name' => self::asStr($row->name),
        ];
    }

    /**
     * Coerce a model / stdClass / array into a plain associative array.
     *
     * Eloquent stores attributes in a protected $attributes array, so a raw
     * (array) cast yields mangled keys. This reads attributes via the model's
     * own toArray() and merges in any dynamically-attached properties
     * (->sender, ->preview) that the services set after hydration.
     *
     * @return array<string,mixed>
     */
    private function savedToAssoc(mixed $value, array $dynamicKeys = []): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value instanceof \Illuminate\Database\Eloquent\Model) {
            $out = $value->attributesToArray();
            foreach ($dynamicKeys as $key) {
                // Dynamic, non-attribute properties (e.g. ->sender, ->preview)
                // attached by the service after hydration.
                $out[$key] = $value->getAttribute($key) ?? ($value->{$key} ?? null);
            }
            return $out;
        }
        if (is_object($value)) {
            return (array) $value;
        }
        return [];
    }

    /**
     * Normalise a SavedCollection (model or array) into a blade-safe array.
     *
     * @return array<string,mixed>
     */
    private function savedCollectionToArray(mixed $c): array
    {
        $a = $this->savedToAssoc($c);
        return [
            'id' => (int) ($a['id'] ?? 0),
            'user_id' => (int) ($a['user_id'] ?? 0),
            'name' => self::asStr($a['name'] ?? ''),
            'description' => isset($a['description']) ? self::asStr($a['description']) : '',
            'color' => self::asStr($a['color'] ?? '') ?: '#6366f1',
            'icon' => self::asStr($a['icon'] ?? '') ?: 'bookmark',
            'items_count' => (int) ($a['items_count'] ?? 0),
            'is_public' => (bool) ($a['is_public'] ?? false),
        ];
    }

    /**
     * Normalise a SavedItem (model, with optional ->preview) into a blade-safe array.
     *
     * @return array<string,mixed>
     */
    private function savedItemToArray(mixed $i): array
    {
        $a = $this->savedToAssoc($i, ['preview']);
        $preview = $a['preview'] ?? null;
        $previewArr = is_array($preview) ? $preview : (is_object($preview) ? (array) $preview : null);
        return [
            'id' => (int) ($a['id'] ?? 0),
            'item_type' => self::asStr($a['item_type'] ?? ''),
            'item_id' => (int) ($a['item_id'] ?? 0),
            'note' => isset($a['note']) ? self::asStr($a['note']) : '',
            'saved_at' => self::asStr($a['saved_at'] ?? ''),
            'preview_title' => $previewArr !== null ? self::asStr($previewArr['title'] ?? '') : '',
        ];
    }

    /**
     * Normalise an Appreciation (model, with ->sender attached) into a blade-safe array.
     *
     * @return array<string,mixed>
     */
    private function savedAppreciationToArray(mixed $a): array
    {
        $row = $this->savedToAssoc($a, ['sender']);
        $sender = $row['sender'] ?? null;
        $senderArr = is_array($sender) ? $sender : (is_object($sender) ? (array) $sender : null);
        return [
            'id' => (int) ($row['id'] ?? 0),
            'sender_id' => (int) ($row['sender_id'] ?? 0),
            'receiver_id' => (int) ($row['receiver_id'] ?? 0),
            'message' => self::asStr($row['message'] ?? ''),
            'is_public' => (bool) ($row['is_public'] ?? false),
            'reactions_count' => (int) ($row['reactions_count'] ?? 0),
            'created_at' => self::asStr($row['created_at'] ?? ''),
            'sender_name' => $senderArr !== null ? self::asStr($senderArr['name'] ?? '') : '',
            'my_reaction' => null,
        ];
    }

    /**
     * Attach the viewer's own reaction to each appreciation (React's
     * `my_reaction`). One scoped query over the visible ids.
     *
     * @param array<int,array<string,mixed>> $items  (by reference)
     */
    private function savedAttachMyReactions(array &$items, int $viewerId): void
    {
        if (empty($items)) {
            return;
        }
        $ids = array_values(array_filter(array_map(static fn ($i) => (int) ($i['id'] ?? 0), $items)));
        if (empty($ids)) {
            return;
        }
        try {
            $reactions = DB::table('appreciation_reactions')
                ->where('user_id', $viewerId)
                ->where('tenant_id', TenantContext::getId())
                ->whereIn('appreciation_id', $ids)
                ->pluck('reaction_type', 'appreciation_id');
            foreach ($items as &$item) {
                $aid = (int) ($item['id'] ?? 0);
                $item['my_reaction'] = $reactions[$aid] ?? null;
            }
            unset($item);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
