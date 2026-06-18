<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Services\SubAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Profile settings, auth, onboarding & notifications — accessible (GOV.UK) frontend parity methods.
 *
 * Composed into AlphaController. Trait methods may call the controller's
 * private helpers ($this->view, $this->currentUserId, $this->assertTenantSlug,
 * $this->allowed, self::asStr). New method names MUST be module-prefixed and
 * unique across AlphaController and every sibling trait. Resolve services via
 * app(SomeService::class) rather than the constructor.
 *
 * Scope of this trait (the two member-facing React settings tabs the audit
 * flagged as genuinely missing from the accessible frontend):
 *   1. Linked / sub-account management — React LinkedAccountsTab (SubAccountsManager).
 *      Backed by the SAME App\Services\SubAccountService the React API controller
 *      (SubAccountController) calls: request a link by email, approve incoming
 *      requests, change permissions, and revoke. Parent (children you manage) and
 *      child (accounts that manage you) relationships are both shown.
 *   2. Appearance / theme — React AppearanceSettings. Persists users.preferred_theme
 *      (enum light|dark|system) exactly as UsersController::updateTheme does.
 *
 * The other audited "gaps" were verified already-present (safeguarding revoke,
 * GDPR export, digest frequency, translation prefs, read-only sessions) or are
 * not parity gaps (per-session revoke does not exist in React either; trust-device
 * 2FA needs the shared two-factor flow which lives in AlphaController).
 */
trait SettingsAuthParity
{
    /** The theme values UsersController::updateTheme accepts. */
    private const SETTINGS_THEMES = ['light', 'dark', 'system'];

    /** The relationship types SubAccountService::RELATIONSHIP_TYPES accepts. */
    private const SETTINGS_LINK_TYPES = ['family', 'guardian', 'carer', 'organization'];

    /** The sub-account permission keys (SubAccountService::DEFAULT_PERMISSIONS). */
    private const SETTINGS_LINK_PERMISSIONS = [
        'can_view_activity',
        'can_manage_listings',
        'can_transact',
        'can_view_messages',
    ];

    // =====================================================================
    //  Linked / sub-account management (React LinkedAccountsTab)
    // =====================================================================

    /**
     * Linked-accounts settings page: the children this member manages and the
     * accounts (parents) that manage this member. Mirrors the React
     * SubAccountsManager which loads /v2/users/me/sub-accounts +
     * /v2/users/me/parent-accounts.
     */
    public function settingsLinkedAccounts(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $service = app(SubAccountService::class);

        try {
            $children = $this->settingsNormaliseRelationships($service->getChildAccounts($userId));
            $parents = $this->settingsNormaliseRelationships($service->getParentAccounts($userId));
        } catch (\Throwable $e) {
            report($e);
            $children = [];
            $parents = [];
        }

        return $this->view('accessible-frontend::settings-linked-accounts', [
            'title' => __('govuk_alpha_settings.linked.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'account',
            'children' => $children,
            'parents' => $parents,
            'linkTypes' => self::SETTINGS_LINK_TYPES,
            'permissionKeys' => self::SETTINGS_LINK_PERMISSIONS,
            'maxChildren' => SubAccountService::MAX_CHILDREN,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * Coerce one set of relationship rows into a flat, view-safe shape with a
     * decoded permissions map. The service returns DB rows (permissions is a
     * JSON string) so we normalise exactly like SubAccountController does.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function settingsNormaliseRelationships(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $perms = $row['permissions'] ?? [];
            if (is_string($perms)) {
                $decoded = json_decode($perms, true);
                $perms = is_array($decoded) ? $decoded : [];
            } elseif (! is_array($perms)) {
                $perms = [];
            }

            $first = trim((string) ($row['first_name'] ?? ''));
            $last = trim((string) ($row['last_name'] ?? ''));
            $name = trim($first . ' ' . $last);

            $permissionsFlags = [];
            foreach (self::SETTINGS_LINK_PERMISSIONS as $key) {
                $permissionsFlags[$key] = (bool) ($perms[$key] ?? false);
            }

            $out[] = [
                'relationship_id' => (int) ($row['relationship_id'] ?? 0),
                'user_id' => (int) ($row['user_id'] ?? 0),
                'name' => $name !== '' ? $name : (string) ($row['email'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'avatar_url' => (string) ($row['avatar_url'] ?? ''),
                'relationship_type' => (string) ($row['relationship_type'] ?? 'family'),
                'status' => (string) ($row['status'] ?? 'pending'),
                'permissions' => $permissionsFlags,
            ];
        }

        return $out;
    }

    /** Request a new linked (child) account by email. Mirrors SubAccountController::requestRelationship. */
    public function settingsRequestLinkedAccount(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $email = trim(self::asStr($request->input('email')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()
                ->route('govuk-alpha.settings.linked-accounts', ['tenantSlug' => $tenantSlug, 'status' => 'link-email-invalid'])
                ->withFragment('request');
        }

        $type = $this->allowed($request->input('relationship_type'), self::SETTINGS_LINK_TYPES, 'family');

        // Checkboxes that are unticked do not post, so read every known key as a
        // boolean to honour the parent's full intent. The service intersects
        // against its DEFAULT_PERMISSIONS, so unknown keys are dropped safely.
        $permissions = [];
        foreach (self::SETTINGS_LINK_PERMISSIONS as $key) {
            $permissions[$key] = $request->boolean('perm_' . $key);
        }

        try {
            // Resolve the child by email within this tenant (same lookup the API
            // controller does before calling the service).
            $childUserId = (int) (DB::table('users')
                ->where('tenant_id', TenantContext::getId())
                ->where('email', $email)
                ->value('id') ?? 0);

            if ($childUserId <= 0) {
                return redirect()
                    ->route('govuk-alpha.settings.linked-accounts', ['tenantSlug' => $tenantSlug, 'status' => 'link-user-not-found'])
                    ->withFragment('request');
            }

            $service = app(SubAccountService::class);
            $relationshipId = $service->requestRelationship($userId, $childUserId, $type, $permissions);
            if ($relationshipId === null) {
                $code = strtoupper((string) ($service->getErrors()[0]['code'] ?? ''));
                $status = match (true) {
                    str_contains($code, 'SELF') => 'link-self',
                    str_contains($code, 'EXIST') => 'link-exists',
                    str_contains($code, 'MAX') || str_contains($code, 'LIMIT') => 'link-max',
                    default => 'link-failed',
                };

                return redirect()
                    ->route('govuk-alpha.settings.linked-accounts', ['tenantSlug' => $tenantSlug, 'status' => $status])
                    ->withFragment('request');
            }
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('govuk-alpha.settings.linked-accounts', ['tenantSlug' => $tenantSlug, 'status' => 'link-failed'])
                ->withFragment('request');
        }

        return redirect()
            ->route('govuk-alpha.settings.linked-accounts', ['tenantSlug' => $tenantSlug, 'status' => 'link-requested'])
            ->withFragment('children');
    }

    /** Approve an incoming link request (this member is the child). Mirrors SubAccountController::approveRelationship. */
    public function settingsApproveLinkedAccount(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $relationshipId = (int) $request->input('relationship_id');
        try {
            $ok = $relationshipId > 0 && app(SubAccountService::class)->approveRelationship($userId, $relationshipId);
            $status = $ok ? 'link-approved' : 'link-failed';
        } catch (\Throwable $e) {
            report($e);
            $status = 'link-failed';
        }

        return redirect()
            ->route('govuk-alpha.settings.linked-accounts', ['tenantSlug' => $tenantSlug, 'status' => $status])
            ->withFragment('parents');
    }

    /** Update the permissions on a linked (child) account. Mirrors SubAccountController::updatePermissions. */
    public function settingsUpdateLinkedPermissions(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $relationshipId = (int) $request->input('relationship_id');
        if ($relationshipId <= 0) {
            return redirect()
                ->route('govuk-alpha.settings.linked-accounts', ['tenantSlug' => $tenantSlug, 'status' => 'link-failed'])
                ->withFragment('children');
        }

        $permissions = [];
        foreach (self::SETTINGS_LINK_PERMISSIONS as $key) {
            $permissions[$key] = $request->boolean('perm_' . $key);
        }

        try {
            $ok = app(SubAccountService::class)->updatePermissions($userId, $relationshipId, $permissions);
            $status = $ok ? 'link-permissions-saved' : 'link-failed';
        } catch (\Throwable $e) {
            report($e);
            $status = 'link-failed';
        }

        return redirect()
            ->route('govuk-alpha.settings.linked-accounts', ['tenantSlug' => $tenantSlug, 'status' => $status])
            ->withFragment('children');
    }

    /** Revoke (remove) a linked relationship — works for both parent and child rows. Mirrors SubAccountController::revokeRelationship. */
    public function settingsRevokeLinkedAccount(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $relationshipId = (int) $request->input('relationship_id');
        try {
            $ok = $relationshipId > 0 && app(SubAccountService::class)->revokeRelationship($userId, $relationshipId);
            $status = $ok ? 'link-revoked' : 'link-failed';
        } catch (\Throwable $e) {
            report($e);
            $status = 'link-failed';
        }

        return redirect()
            ->route('govuk-alpha.settings.linked-accounts', ['tenantSlug' => $tenantSlug, 'status' => $status])
            ->withFragment('children');
    }

    // =====================================================================
    //  Appearance / theme (React AppearanceSettings)
    // =====================================================================

    /** Appearance settings page: choose light / dark / system theme. */
    public function settingsAppearance(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $current = (string) (DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', TenantContext::getId())
            ->value('preferred_theme') ?? 'system');
        if (! in_array($current, self::SETTINGS_THEMES, true)) {
            $current = 'system';
        }

        return $this->view('accessible-frontend::settings-appearance', [
            'title' => __('govuk_alpha_settings.appearance.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'account',
            'currentTheme' => $current,
            'themes' => self::SETTINGS_THEMES,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** Persist the chosen theme to users.preferred_theme (mirrors UsersController::updateTheme). */
    public function settingsUpdateAppearance(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $theme = $this->allowed($request->input('theme'), self::SETTINGS_THEMES, null);
        if ($theme === null) {
            return redirect()->route('govuk-alpha.settings.appearance', ['tenantSlug' => $tenantSlug, 'status' => 'appearance-invalid']);
        }

        try {
            DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', TenantContext::getId())
                ->update(['preferred_theme' => $theme, 'updated_at' => now()]);
            $status = 'appearance-saved';
        } catch (\Throwable $e) {
            report($e);
            $status = 'appearance-failed';
        }

        return redirect()->route('govuk-alpha.settings.appearance', ['tenantSlug' => $tenantSlug, 'status' => $status]);
    }
}
