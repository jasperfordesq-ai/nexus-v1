<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Models\SupportReport;
use App\Services\CookieConsentService;
use App\Services\SupportReportNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * CookieSupportParity — GOV.UK cookie banner + "Report a problem with this page".
 *
 * Both are HTML-first (work with NO JavaScript via plain form POSTs):
 *  - Cookie consent records the member's choice in the same `cookie_consents`
 *    backend the React app uses (GDPR audit trail) AND sets a first-party cookie
 *    so the banner stays dismissed. Granular control lives on the cookie settings
 *    page (GET /cookies). Mirrors React's CookieConsentContext categories
 *    (essential always-on, analytics opt-in, functional on).
 *  - "Report a problem" routes by login: signed-in members file a structured
 *    `support_reports` row (same triage queue as React's ReportProblemButton),
 *    signed-out visitors are sent to the contact form pre-filled with the page URL.
 */
trait CookieSupportParity
{
    /** First-party cookie that records "a cookie choice has been made" (value: all|essential). */
    private const ALPHA_COOKIE_NAME = 'nexus_alpha_cookie_consent';
    private const ALPHA_COOKIE_DAYS = 180;
    private const SUPPORT_IMPACTS = ['blocked', 'major', 'minor', 'cosmetic'];

    /**
     * Record a cookie-consent choice from the banner ('accept'/'reject') or the
     * granular settings page ('save' + analytics=yes|no). No JavaScript required.
     */
    public function storeCookieConsent(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);

        $choice = self::asStr($request->input('cookies'));
        $analytics = match ($choice) {
            'accept' => true,
            'reject' => false,
            default  => self::asStr($request->input('analytics')) === 'yes',
        };

        // Functional cookies (language + theme persistence) are required for the
        // accessible service to work, so only analytics is optional here.
        try {
            app(CookieConsentService::class)->storeConsent(
                $this->currentUserId(),
                (int) (TenantContext::getId() ?? 0),
                $request->ip(),
                ['functional' => true, 'analytics' => $analytics, 'marketing' => false],
            );
        } catch (\Throwable $e) {
            report($e);
        }

        // Presence of this cookie = a choice has been made → banner stays hidden.
        Cookie::queue(Cookie::make(
            self::ALPHA_COOKIE_NAME,
            $analytics ? 'all' : 'essential',
            self::ALPHA_COOKIE_DAYS * 24 * 60,
            '/',
            null,
            $request->isSecure(),
            false,   // readable by no-JS + the layout (httpOnly off)
            false,
            'lax',
        ));

        // The settings page redirects to itself with a success banner; the banner's
        // accept/reject redirects back to the page the member was on (GOV.UK shows a
        // one-off confirmation message there via the flashed choice).
        if ($choice === 'save') {
            return redirect()
                ->route('govuk-alpha.cookies', ['tenantSlug' => $tenantSlug, 'status' => 'saved']);
        }

        $returnTo = $this->alphaSafePath(
            self::asStr($request->input('return')),
            route('govuk-alpha.home', ['tenantSlug' => $tenantSlug]),
        );

        return redirect($returnTo)->with('alpha_cookie_choice', $analytics ? 'accepted' : 'rejected');
    }

    /** GOV.UK "Change your cookie settings" page (granular analytics toggle). */
    public function cookieSettings(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);

        return $this->view('accessible-frontend::cookie-settings', [
            'title' => __('govuk_alpha.cookie_settings.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => '',
            'analyticsOn' => $request->cookie(self::ALPHA_COOKIE_NAME) === 'all',
            'hasChoice' => $request->cookie(self::ALPHA_COOKIE_NAME) !== null,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * "Report a problem with this page" entry point. Signed-in members get the
     * structured report form; signed-out visitors go to the contact form, with the
     * page they were on pre-filled so the report still carries that context.
     */
    public function reportProblem(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);

        $pageUrl = $this->alphaSafePath(
            self::asStr($request->query('return')),
            route('govuk-alpha.home', ['tenantSlug' => $tenantSlug]),
        );

        if ($this->currentUserId() === null) {
            return redirect()->route('govuk-alpha.contact', [
                'tenantSlug' => $tenantSlug,
                'problem_url' => $pageUrl,
            ]);
        }

        return $this->view('accessible-frontend::report-problem', [
            'title' => __('govuk_alpha.report_problem.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => '',
            'pageUrl' => $pageUrl,
            'impacts' => self::SUPPORT_IMPACTS,
            'status' => self::asStr($request->query('status')) ?: null,
            'reference' => self::asStr($request->query('ref')) ?: null,
        ]);
    }

    /** Submit a structured problem report → support_reports (signed-in only). */
    public function storeReportProblem(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $summary = trim(self::asStr($request->input('summary')));
        $description = trim(self::asStr($request->input('description')));
        $impact = self::asStr($request->input('impact'));
        $pageUrl = $this->alphaSafePath(
            self::asStr($request->input('page_url')),
            route('govuk-alpha.home', ['tenantSlug' => $tenantSlug]),
        );

        $errors = [];
        if (mb_strlen($summary) < 3 || mb_strlen($summary) > 180) {
            $errors['summary'] = __('govuk_alpha.report_problem.errors.summary');
        }
        if (mb_strlen($description) < 10 || mb_strlen($description) > 5000) {
            $errors['description'] = __('govuk_alpha.report_problem.errors.description');
        }
        if (! in_array($impact, self::SUPPORT_IMPACTS, true)) {
            $errors['impact'] = __('govuk_alpha.report_problem.errors.impact');
        }

        if ($errors !== []) {
            return redirect()
                ->route('govuk-alpha.report-problem', ['tenantSlug' => $tenantSlug, 'return' => $pageUrl, 'status' => 'invalid'])
                ->withErrors($errors)
                ->withInput();
        }

        try {
            $report = SupportReport::create([
                'tenant_id'   => (int) (TenantContext::getId() ?? 0),
                'user_id'     => $userId,
                'reference'   => $this->generateSupportReference(),
                'source'      => 'accessible',
                'summary'     => $summary,
                'description' => $description,
                'impact'      => $impact,
                'status'      => 'open',
                'page_url'    => Str::limit($pageUrl, 2048, ''),
                'route'       => optional($request->route())->getName(),
                'user_agent'  => Str::limit((string) $request->userAgent(), 512, ''),
                'ip_hash'     => $this->hashReportIp($request->ip()),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('govuk-alpha.report-problem', ['tenantSlug' => $tenantSlug, 'return' => $pageUrl, 'status' => 'failed'])
                ->withInput();
        }

        try {
            SupportReportNotificationService::notifyCreated($report);
        } catch (\Throwable $e) {
            report($e); // notification failure must not lose the report
        }

        return redirect()->route('govuk-alpha.report-problem', [
            'tenantSlug' => $tenantSlug,
            'return' => $pageUrl,
            'status' => 'sent',
            'ref' => $report->reference,
        ]);
    }

    /**
     * Only allow a same-site absolute path (no scheme, no protocol-relative) to be
     * used as a return/redirect target — prevents open-redirect abuse.
     */
    private function alphaSafePath(?string $input, string $fallback): string
    {
        $input = trim((string) $input);
        if ($input !== '' && str_starts_with($input, '/') && ! str_starts_with($input, '//')) {
            return $input;
        }

        return $fallback;
    }

    private function generateSupportReference(): string
    {
        do {
            $reference = 'NXR-' . now()->format('ymd') . '-' . Str::upper(Str::random(6));
        } while (SupportReport::withoutGlobalScopes()->where('reference', $reference)->exists());

        return $reference;
    }

    private function hashReportIp(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) (config('app.key') ?: 'nexus-support-report-ip-hash'));
    }
}
