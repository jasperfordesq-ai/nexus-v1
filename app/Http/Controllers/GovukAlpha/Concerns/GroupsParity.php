<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Services\GroupAnnouncementService;
use App\Services\GroupInviteService;
use App\Services\GroupNotificationPreferenceService;
use App\Services\GroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Groups — accessible (GOV.UK) frontend parity methods.
 *
 * Composed into AlphaController. Trait methods may call the controller's
 * private helpers ($this->view, $this->currentUserId, $this->assertTenantSlug,
 * $this->allowed, self::asStr). New method names MUST be module-prefixed and
 * unique across AlphaController and every sibling trait. Resolve services via
 * app(SomeService::class) rather than the constructor.
 *
 * This trait closes three React-parity gaps that the existing AlphaController
 * group methods do not cover, each as a standalone accessible page:
 *   - Invite members (link generation + email invites + revoke) — owner/admin only.
 *   - Per-group notification preferences — members + admins.
 *   - Avatar vs cover image management — owner/admin only.
 *
 * Every method calls the SAME services the React API controllers call
 * (GroupInviteController → GroupInviteService, GroupNotificationPrefController →
 * GroupNotificationPreferenceService, GroupsController → GroupService::updateImage),
 * so invite-link issuance, recipient-localised email fan-out, and permission
 * checks are never reimplemented here.
 */
trait GroupsParity
{
    // -----------------------------------------------------------------
    //  Shared guards
    // -----------------------------------------------------------------

    /**
     * Auth + feature gate for every accessible groups-parity action. Returns the
     * authenticated user id, or a redirect (login / 403) the caller must return.
     */
    private function groupsParityGuard(string $tenantSlug): int|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('groups'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        return $userId;
    }

    /**
     * Load a group the current user administers (owner/admin), or abort.
     * A group in another tenant resolves to null (tenant-global scope) → 404;
     * a group the user cannot modify → 403.
     *
     * @return array<string, mixed>
     */
    private function groupsParityManagedGroup(int $groupId, int $userId): array
    {
        $group = null;
        try {
            $group = GroupService::getById($groupId, $userId, false);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($group === null, 404);
        abort_unless(GroupService::canModify($groupId, $userId), 403);

        return $group;
    }

    // -----------------------------------------------------------------
    //  Invite members (link + email + revoke)  — owner/admin only
    //  Mirrors GroupInviteController + GroupDetailPage GroupInviteModal.
    // -----------------------------------------------------------------

    public function groupsInvite(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $userId = $this->groupsParityGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }
        $group = $this->groupsParityManagedGroup($id, $userId);

        /** @var GroupInviteService $service */
        $service = app(GroupInviteService::class);
        $pending = [];
        try {
            $pending = $service->getPendingInvites($id, $userId) ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        // A freshly generated link is flashed to the session so the page can
        // surface it once (it is also recoverable from the pending list below,
        // but a copyable URL right after generation matches the React UX).
        $generatedLink = self::asStr($request->session()->get('groups_invite_link'));

        return $this->view('accessible-frontend::groups-invite', [
            'title' => __('govuk_alpha_groups.invite.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'group' => $group,
            'pendingInvites' => is_array($pending) ? $pending : [],
            'generatedLink' => $generatedLink !== '' ? $generatedLink : null,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function groupsCreateInviteLink(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->groupsParityGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }
        // Permission is re-checked inside the service (canInvite → canModify).
        $back = fn (string $status): RedirectResponse => redirect()->route(
            'govuk-alpha.groups.invite',
            ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $status]
        );

        $expiryDays = (int) $request->input('expiry_days', 0);
        $expiryDays = $expiryDays >= 1 && $expiryDays <= 90 ? $expiryDays : null;

        /** @var GroupInviteService $service */
        $service = app(GroupInviteService::class);
        $result = null;
        try {
            $result = $service->createLink($id, $userId, $expiryDays);
        } catch (\Throwable $e) {
            report($e);
        }

        if (!is_array($result) || empty($result['invite_url'])) {
            $errors = $service->getErrors();
            $forbidden = ($errors[0]['code'] ?? '') === 'FORBIDDEN';
            return $back($forbidden ? 'invite-forbidden' : 'invite-link-failed');
        }

        $request->session()->flash('groups_invite_link', (string) $result['invite_url']);

        return $back('invite-link-created');
    }

    public function groupsSendInvites(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->groupsParityGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }
        $back = fn (string $status): RedirectResponse => redirect()->route(
            'govuk-alpha.groups.invite',
            ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $status]
        );

        // Parse the comma/newline-separated address list (mirrors the React split).
        $raw = self::asStr($request->input('emails'));
        $emails = array_values(array_filter(array_map(
            'trim',
            preg_split('/[,\r\n]+/', $raw) ?: []
        )));

        if ($emails === []) {
            return $back('invite-emails-required');
        }
        // Hard ceiling mirrors GroupInviteController (anti-relay-abuse).
        if (count($emails) > 50) {
            return $back('invite-emails-too-many');
        }

        $message = trim(self::asStr($request->input('message')));

        /** @var GroupInviteService $service */
        $service = app(GroupInviteService::class);
        $result = null;
        try {
            $result = $service->sendEmailInvites($id, $userId, $emails, $message);
        } catch (\Throwable $e) {
            report($e);
        }

        if (!is_array($result)) {
            $errors = $service->getErrors();
            $forbidden = ($errors[0]['code'] ?? '') === 'FORBIDDEN';
            return $back($forbidden ? 'invite-forbidden' : 'invite-email-failed');
        }

        return $back('invite-emails-sent');
    }

    public function groupsRevokeInvite(Request $request, string $tenantSlug, int $id, int $inviteId): RedirectResponse
    {
        $userId = $this->groupsParityGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }
        $back = fn (string $status): RedirectResponse => redirect()->route(
            'govuk-alpha.groups.invite',
            ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $status]
        );

        /** @var GroupInviteService $service */
        $service = app(GroupInviteService::class);
        $ok = false;
        try {
            $ok = $service->revokeInvite($id, $inviteId, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $back($ok ? 'invite-revoked' : 'invite-revoke-failed');
    }

    // -----------------------------------------------------------------
    //  Per-group notification preferences  — members + admins
    //  Mirrors GroupNotificationPrefController + GroupNotificationPrefs.tsx.
    // -----------------------------------------------------------------

    public function groupsNotificationPrefs(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $userId = $this->groupsParityGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }

        $group = null;
        try {
            $group = GroupService::getById($id, $userId, true);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($group === null, 404);
        // Same gate as the API controller: active member OR an admin/owner.
        abort_unless(
            GroupService::isActiveMember($id, $userId) || GroupService::canModify($id, $userId),
            403
        );

        $prefs = ['frequency' => 'instant', 'email_enabled' => true, 'push_enabled' => true];
        try {
            $prefs = GroupNotificationPreferenceService::get($userId, $id);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::groups-notifications', [
            'title' => __('govuk_alpha_groups.notifications.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'group' => $group,
            'prefFrequency' => $this->allowed(
                self::asStr($prefs['frequency'] ?? 'instant'),
                ['instant', 'digest', 'muted'],
                'instant'
            ),
            'prefEmailEnabled' => (bool) ($prefs['email_enabled'] ?? true),
            'prefPushEnabled' => (bool) ($prefs['push_enabled'] ?? true),
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function groupsUpdateNotificationPrefs(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->groupsParityGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }

        $group = null;
        try {
            $group = GroupService::getById($id, $userId, true);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($group === null, 404);
        abort_unless(
            GroupService::isActiveMember($id, $userId) || GroupService::canModify($id, $userId),
            403
        );

        $frequency = $this->allowed(
            self::asStr($request->input('frequency')),
            ['instant', 'digest', 'muted'],
            'instant'
        );

        $ok = true;
        try {
            GroupNotificationPreferenceService::set($userId, $id, [
                'frequency' => $frequency,
                // Unchecked checkboxes are simply absent from the POST body.
                'email_enabled' => $request->boolean('email_enabled'),
                'push_enabled' => $request->boolean('push_enabled'),
            ]);
        } catch (\Throwable $e) {
            report($e);
            $ok = false;
        }

        return redirect()->route('govuk-alpha.groups.notifications', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? 'prefs-saved' : 'prefs-failed',
        ]);
    }

    // -----------------------------------------------------------------
    //  Avatar + cover image management  — owner/admin only
    //  Mirrors GroupsController::uploadImage (type: avatar|cover) + the React
    //  GroupSettingsModal image handling.
    // -----------------------------------------------------------------

    public function groupsImage(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $userId = $this->groupsParityGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }
        $group = $this->groupsParityManagedGroup($id, $userId);

        return $this->view('accessible-frontend::groups-image', [
            'title' => __('govuk_alpha_groups.image.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'group' => $group,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function groupsUpdateImage(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->groupsParityGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }
        // updateImage re-checks ownership/tenant scope; this also 404s a
        // cross-tenant group and 403s a non-admin before we touch the upload.
        $this->groupsParityManagedGroup($id, $userId);

        $back = fn (string $status): RedirectResponse => redirect()->route(
            'govuk-alpha.groups.image',
            ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $status]
        );

        // Whitelist the target field; the React modal offers avatar + cover.
        $type = $this->allowed(self::asStr($request->input('type')), ['avatar', 'cover'], 'avatar');

        $file = $request->file('image');
        if ($file === null || is_array($file) || !$file->isValid()) {
            return $back('image-missing');
        }

        $ok = false;
        try {
            $imageUrl = \App\Core\ImageUploader::upload([
                'name' => $file->getClientOriginalName(),
                'type' => $file->getMimeType(),
                'tmp_name' => $file->getRealPath(),
                'error' => UPLOAD_ERR_OK,
                'size' => $file->getSize(),
            ], 'groups');

            if (is_string($imageUrl) && $imageUrl !== '') {
                $ok = GroupService::updateImage($id, $userId, $imageUrl, $type);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        if (!$ok) {
            return $back('image-failed');
        }

        return $back($type === 'cover' ? 'cover-updated' : 'avatar-updated');
    }

    // -----------------------------------------------------------------
    //  Group Announcements — list, create, edit, delete, pin/unpin
    //  Mirrors GroupsController announcements methods + GroupAnnouncementService.
    //  Permission rule: list visible to all active members; create/edit/delete/pin
    //  restricted to group owners/admins (GroupService::canModify).
    // -----------------------------------------------------------------

    /**
     * GET /{tenantSlug}/alpha/groups/{id}/announcements
     * List announcements; also renders create form for admins.
     */
    public function groupsAnnouncements(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $userId = $this->groupsParityGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }

        $group = null;
        try {
            $group = GroupService::getById($id, $userId, true);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($group === null, 404);
        abort_unless(
            GroupService::isActiveMember($id, $userId) || GroupService::canModify($id, $userId),
            403
        );

        $isAdmin = GroupService::canModify($id, $userId);

        /** @var GroupAnnouncementService $service */
        $service = app(GroupAnnouncementService::class);
        $result = null;
        try {
            $result = $service->list($id, $userId, ['limit' => 50, 'include_expired' => true]);
        } catch (\Throwable $e) {
            report($e);
        }
        $announcements = is_array($result) ? ($result['items'] ?? []) : [];

        return $this->view('accessible-frontend::group-announcements', [
            'title'         => __('govuk_alpha_groups.announcements.title'),
            'tenantSlug'    => $tenantSlug,
            'activeNav'     => 'explore',
            'group'         => $group,
            'announcements' => $announcements,
            'isAdmin'       => $isAdmin,
            'status'        => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * POST /{tenantSlug}/alpha/groups/{id}/announcements
     * Create a new announcement (admin only).
     */
    public function groupsCreateAnnouncement(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->groupsParityGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }

        $back = fn (string $status): RedirectResponse => redirect()->route(
            'govuk-alpha.groups.announcements',
            ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $status]
        );

        abort_unless(GroupService::canModify($id, $userId), 403);

        $data = [
            'title'      => self::asStr($request->input('title')),
            'content'    => self::asStr($request->input('content')),
            'is_pinned'  => $request->boolean('is_pinned'),
            'expires_at' => self::asStr($request->input('expires_at')) ?: null,
        ];

        /** @var GroupAnnouncementService $service */
        $service = app(GroupAnnouncementService::class);
        $result = null;
        try {
            $result = $service->create($id, $userId, $data);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($result === null) {
            $errors = $service->getErrors();
            $code   = $errors[0]['code'] ?? '';
            if ($code === 'FORBIDDEN') {
                return $back('ann-forbidden');
            }
            $field = $errors[0]['field'] ?? '';
            if ($field === 'title') {
                return $back('ann-title-required');
            }
            if ($field === 'content') {
                return $back('ann-content-required');
            }

            return $back('ann-create-failed');
        }

        return $back('ann-created');
    }

    /**
     * GET /{tenantSlug}/alpha/groups/{id}/announcements/{annId}/edit
     * Show edit form for a single announcement (admin only).
     */
    public function groupsEditAnnouncement(Request $request, string $tenantSlug, int $id, int $annId): Response|RedirectResponse
    {
        $userId = $this->groupsParityGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }

        $group = null;
        try {
            $group = GroupService::getById($id, $userId, false);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($group === null, 404);
        abort_unless(GroupService::canModify($id, $userId), 403);

        /** @var GroupAnnouncementService $service */
        $service = app(GroupAnnouncementService::class);
        $announcement = null;
        try {
            $announcement = $service->getById($id, $annId, $userId);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($announcement === null, 404);

        return $this->view('accessible-frontend::group-announcements-edit', [
            'title'        => __('govuk_alpha_groups.announcements.edit_heading'),
            'tenantSlug'   => $tenantSlug,
            'activeNav'    => 'explore',
            'group'        => $group,
            'announcement' => $announcement,
            'status'       => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * POST /{tenantSlug}/alpha/groups/{id}/announcements/{annId}/edit
     * Persist edits to an announcement (admin only, no-JS POST).
     */
    public function groupsUpdateAnnouncement(Request $request, string $tenantSlug, int $id, int $annId): RedirectResponse
    {
        $userId = $this->groupsParityGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }

        $backList = fn (string $status): RedirectResponse => redirect()->route(
            'govuk-alpha.groups.announcements',
            ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $status]
        );
        $backEdit = fn (string $status): RedirectResponse => redirect()->route(
            'govuk-alpha.groups.announcements.edit',
            ['tenantSlug' => $tenantSlug, 'id' => $id, 'annId' => $annId, 'status' => $status]
        );

        abort_unless(GroupService::canModify($id, $userId), 403);

        $data = [
            'title'      => self::asStr($request->input('title')),
            'content'    => self::asStr($request->input('content')),
            'is_pinned'  => $request->boolean('is_pinned'),
            'expires_at' => self::asStr($request->input('expires_at')) ?: null,
        ];

        /** @var GroupAnnouncementService $service */
        $service = app(GroupAnnouncementService::class);
        $result  = null;
        try {
            $result = $service->update($id, $annId, $userId, $data);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($result === null) {
            $errors = $service->getErrors();
            $code   = $errors[0]['code'] ?? '';
            if ($code === 'NOT_FOUND') {
                return $backList('ann-not-found');
            }
            if ($code === 'FORBIDDEN') {
                return $backList('ann-forbidden');
            }
            // Field-level validation → highlight the offending field on the edit
            // form (the edit view already renders these states; mirrors create).
            $field = $errors[0]['field'] ?? '';
            if ($field === 'title') {
                return $backEdit('ann-title-required');
            }
            if ($field === 'content') {
                return $backEdit('ann-content-required');
            }

            return $backEdit('ann-update-failed');
        }

        return $backList('ann-updated');
    }

    /**
     * POST /{tenantSlug}/alpha/groups/{id}/announcements/{annId}/delete
     * Delete an announcement (admin only, no-JS POST).
     */
    public function groupsDeleteAnnouncement(Request $request, string $tenantSlug, int $id, int $annId): RedirectResponse
    {
        $userId = $this->groupsParityGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }

        $back = fn (string $status): RedirectResponse => redirect()->route(
            'govuk-alpha.groups.announcements',
            ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $status]
        );

        abort_unless(GroupService::canModify($id, $userId), 403);

        /** @var GroupAnnouncementService $service */
        $service = app(GroupAnnouncementService::class);
        $ok      = false;
        try {
            $ok = $service->delete($id, $annId, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $back($ok ? 'ann-deleted' : 'ann-delete-failed');
    }

    /**
     * POST /{tenantSlug}/alpha/groups/{id}/announcements/{annId}/pin
     * Toggle pin/unpin on an announcement (admin only, no-JS POST).
     * Reads hidden input 'is_pinned' = '1' (pin) or '0' (unpin).
     */
    public function groupsPinAnnouncement(Request $request, string $tenantSlug, int $id, int $annId): RedirectResponse
    {
        $userId = $this->groupsParityGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }

        $back = fn (string $status): RedirectResponse => redirect()->route(
            'govuk-alpha.groups.announcements',
            ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $status]
        );

        abort_unless(GroupService::canModify($id, $userId), 403);

        $isPinned = (bool) $request->input('is_pinned', '0');

        /** @var GroupAnnouncementService $service */
        $service = app(GroupAnnouncementService::class);
        $result  = null;
        try {
            $result = $service->update($id, $annId, $userId, ['is_pinned' => $isPinned]);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($result === null) {
            $errors = $service->getErrors();
            $code   = $errors[0]['code'] ?? '';
            if ($code === 'NOT_FOUND') {
                return $back('ann-not-found');
            }

            return $back('ann-pin-failed');
        }

        return $back($isPinned ? 'ann-pinned' : 'ann-unpinned');
    }
}
