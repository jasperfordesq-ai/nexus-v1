<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Federation — accessible (GOV.UK) frontend parity methods.
 *
 * Composed into AlphaController. Trait methods may call the controller's
 * private helpers ($this->view, $this->currentUserId, $this->assertTenantSlug,
 * $this->allowed, self::asStr) and the existing federation* helpers
 * ($this->federationPartnersForDisplay).
 *
 * Scope of this trait: the React "Federation Onboarding" 4-step guided wizard
 * (Welcome -> Privacy -> Communication -> Confirm) that the accessible frontend
 * was missing — it previously had only the single flat federation-opt-in form.
 * The wizard persists the SAME settings via the SAME service the React
 * FederationOnboardingPage / FederationV2Controller::setup uses
 * (FederationUserService::updateSettings + FederationAuditService::log), so the
 * data outcome is identical; only the step-by-step UX is added.
 *
 * Method names are module-prefixed (federationOnboarding*) and unique across
 * AlphaController and every sibling trait (verified: the generic onboarding*
 * member-onboarding methods are distinct names).
 */
trait FederationParity
{
    /** Wizard step slugs in order. */
    private const FEDERATION_ONBOARDING_STEPS = ['welcome', 'privacy', 'communication', 'confirm'];

    /** Boolean privacy/communication toggle keys handled by the wizard. */
    private const FEDERATION_ONBOARDING_TOGGLES = [
        'profile_visible_federated',
        'appear_in_federated_search',
        'show_skills_federated',
        'show_location_federated',
        'show_reviews_federated',
        'messaging_enabled_federated',
        'transactions_enabled_federated',
        'email_notifications',
    ];

    /**
     * Sensible defaults mirroring the React wizard's DEFAULT_SETTINGS — everything
     * shared by default EXCEPT location, which stays off (GDPR data-minimisation,
     * matching FederationV2Controller::setup show_location_federated default).
     *
     * @return array<string, mixed>
     */
    private static function federationOnboardingDefaults(): array
    {
        return [
            'profile_visible_federated'      => true,
            'appear_in_federated_search'     => true,
            'show_skills_federated'          => true,
            'show_location_federated'        => false,
            'show_reviews_federated'         => true,
            'messaging_enabled_federated'    => true,
            'transactions_enabled_federated' => true,
            'email_notifications'            => true,
            'service_reach'                  => 'local_only',
            'travel_radius_km'               => 25,
        ];
    }

    /**
     * Read the in-progress wizard settings bag from the session, merged over the
     * defaults so every key is always present for the views.
     *
     * @return array<string, mixed>
     */
    private function federationOnboardingBag(Request $request): array
    {
        $bag = (array) $request->session()->get('alpha_federation_onboarding', []);
        return array_merge(self::federationOnboardingDefaults(), $bag);
    }

    /**
     * Federation onboarding wizard — `/federation/onboarding` (GET).
     *
     * Renders the current wizard step. The step is taken from the validated
     * ?step query param (so Back/Next links are bookmarkable) and clamped to the
     * known step list. Already opted-in members are redirected straight to the
     * hub (idempotency — mirrors the React useEffect status check).
     */
    public function federationOnboarding(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(\App\Core\TenantContext::hasFeature('federation'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        // Idempotency: opted-in members skip the wizard and go to the hub.
        if (\App\Services\FederationUserService::hasOptedIn($userId)) {
            return redirect()->route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]);
        }

        $step = $this->allowed(self::asStr($request->query('step')), self::FEDERATION_ONBOARDING_STEPS, 'welcome');
        $bag = $this->federationOnboardingBag($request);

        // The confirmation step shows a short partner-community preview so members
        // can see who they will connect with before enabling.
        $partners = [];
        if ($step === 'confirm') {
            try {
                $partners = array_slice($this->federationPartnersForDisplay(\App\Core\TenantContext::getId()), 0, 5);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $stepIndex = array_search($step, self::FEDERATION_ONBOARDING_STEPS, true);
        $stepNumber = ($stepIndex === false ? 0 : (int) $stepIndex) + 1;

        return $this->view('accessible-frontend::federation-onboarding', [
            'title'              => __('govuk_alpha_federation.onboarding.page_title'),
            'tenantSlug'         => $tenantSlug,
            'tenant'             => \App\Core\TenantContext::get(),
            'activeNav'          => 'explore',
            'federationActiveTab' => 'overview',
            'step'               => $step,
            'stepNumber'         => $stepNumber,
            'totalSteps'         => count(self::FEDERATION_ONBOARDING_STEPS),
            'settings'           => $bag,
            'partners'           => $partners,
            'status'             => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * Federation onboarding wizard — `/federation/onboarding` (POST).
     *
     * Processes ONE step: persists that step's fields into the session bag and
     * advances to the next step. At the confirm step it finalises by opting the
     * member in and saving every preference through FederationUserService (the
     * same service FederationV2Controller::setup calls), then audit-logs the
     * opt-in exactly like the React/API path. PRG throughout — no double-submit.
     */
    public function federationOnboardingStore(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(\App\Core\TenantContext::hasFeature('federation'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        // Idempotency: if they already opted in (e.g. another tab), go to the hub.
        if (\App\Services\FederationUserService::hasOptedIn($userId)) {
            return redirect()->route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]);
        }

        $step = $this->allowed(self::asStr($request->input('step')), self::FEDERATION_ONBOARDING_STEPS, 'welcome');
        $bag = (array) $request->session()->get('alpha_federation_onboarding', []);

        // Persist the fields owned by the submitted step into the session bag.
        if ($step === 'privacy') {
            foreach (['profile_visible_federated', 'appear_in_federated_search', 'show_skills_federated', 'show_location_federated', 'show_reviews_federated'] as $key) {
                $bag[$key] = $request->boolean($key);
            }
        } elseif ($step === 'communication') {
            foreach (['messaging_enabled_federated', 'transactions_enabled_federated', 'email_notifications'] as $key) {
                $bag[$key] = $request->boolean($key);
            }
            $reachRaw = self::asStr($request->input('service_reach'));
            $bag['service_reach'] = in_array($reachRaw, ['local_only', 'remote_ok', 'travel_ok'], true) ? $reachRaw : 'local_only';
            $bag['travel_radius_km'] = max(0, min(500, (int) $request->input('travel_radius_km')));
        }

        $request->session()->put('alpha_federation_onboarding', $bag);

        // Confirm step → finalise.
        if ($step === 'confirm') {
            return $this->federationOnboardingFinish($request, $tenantSlug, $userId);
        }

        // Otherwise advance to the next step.
        $index = array_search($step, self::FEDERATION_ONBOARDING_STEPS, true);
        $nextIndex = ($index === false ? 0 : (int) $index) + 1;
        $next = self::FEDERATION_ONBOARDING_STEPS[$nextIndex] ?? 'confirm';

        return redirect()->route('govuk-alpha.federation.onboarding', [
            'tenantSlug' => $tenantSlug,
            'step'       => $next,
        ]);
    }

    /**
     * Finalise the wizard — opt in + persist all preferences, then audit-log.
     *
     * Mirrors FederationV2Controller::setup: checks the tenant gate, builds the
     * full settings array, calls FederationUserService::updateSettings, and logs
     * the user_federation_optin audit event on success.
     */
    private function federationOnboardingFinish(Request $request, string $tenantSlug, int $userId): RedirectResponse
    {
        $tenantId = \App\Core\TenantContext::getId();

        $feature = app(\App\Services\FederationFeatureService::class);
        $tenantEnabled = false;
        try {
            $tenantEnabled = $feature->isGloballyEnabled() && $feature->isTenantFederationEnabled($tenantId);
        } catch (\Throwable $e) {
            report($e);
        }
        if (!$tenantEnabled) {
            return redirect()->route('govuk-alpha.federation.onboarding', [
                'tenantSlug' => $tenantSlug,
                'step'       => 'confirm',
                'status'     => 'unavailable',
            ]);
        }

        $bag = $this->federationOnboardingBag($request);
        $current = \App\Services\FederationUserService::getUserSettings($userId);

        $reachRaw = self::asStr($bag['service_reach'] ?? 'local_only');
        $reach = in_array($reachRaw, ['local_only', 'remote_ok', 'travel_ok'], true) ? $reachRaw : 'local_only';

        $settings = array_merge($current, [
            'federation_optin'               => true,
            'profile_visible_federated'      => (bool) ($bag['profile_visible_federated'] ?? true),
            'appear_in_federated_search'     => (bool) ($bag['appear_in_federated_search'] ?? true),
            'show_skills_federated'          => (bool) ($bag['show_skills_federated'] ?? true),
            'show_location_federated'        => (bool) ($bag['show_location_federated'] ?? false),
            'show_reviews_federated'         => (bool) ($bag['show_reviews_federated'] ?? true),
            'messaging_enabled_federated'    => (bool) ($bag['messaging_enabled_federated'] ?? true),
            'transactions_enabled_federated' => (bool) ($bag['transactions_enabled_federated'] ?? true),
            'email_notifications'            => (bool) ($bag['email_notifications'] ?? true),
            'service_reach'                  => $reach,
            'travel_radius_km'               => max(0, min(500, (int) ($bag['travel_radius_km'] ?? 25))),
        ]);

        $ok = \App\Services\FederationUserService::updateSettings($userId, $settings);

        if ($ok) {
            // Audit-log the opt-in exactly like FederationV2Controller::setup.
            try {
                \App\Services\FederationAuditService::log(
                    'user_federation_optin',
                    $tenantId,
                    null,
                    $userId,
                    [],
                    \App\Services\FederationAuditService::LEVEL_INFO
                );
            } catch (\Throwable $e) {
                report($e);
            }
            $request->session()->forget('alpha_federation_onboarding');

            return redirect()->route('govuk-alpha.federation.index', [
                'tenantSlug' => $tenantSlug,
                'status'     => 'opted-in',
            ]);
        }

        return redirect()->route('govuk-alpha.federation.onboarding', [
            'tenantSlug' => $tenantSlug,
            'step'       => 'confirm',
            'status'     => 'optin-failed',
        ]);
    }
}
