<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Exceptions\EventAnalyticsException;
use App\Http\Resources\EventAnalyticsResource;
use App\I18n\LocaleContext;
use App\Models\User;
use App\Services\EventAnalyticsQueryService;
use App\Support\Events\EventAnalyticsCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** HTML-first organizer analytics with the same privacy-safe ledger contract. */
trait EventAnalyticsParity
{
    public function eventsAnalytics(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response|RedirectResponse {
        $actor = $this->eventsAnalyticsActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        try {
            $summary = EventAnalyticsResource::fromSummary(
                app(EventAnalyticsQueryService::class)->summary($id, $actor),
            );
        } catch (EventAnalyticsException $exception) {
            return $this->eventsAnalyticsFailure($exception, $tenantSlug, $id);
        }

        $response = $this->view('accessible-frontend::event-analytics', [
            'title' => __('govuk_alpha.events.analytics.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'eventId' => $id,
            'summary' => $summary,
        ]);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');
        $response->setVary('Cookie', false);

        return $response;
    }

    public function eventsAnalyticsExport(
        Request $request,
        string $tenantSlug,
        int $id,
    ): StreamedResponse|RedirectResponse {
        $actor = $this->eventsAnalyticsActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        try {
            $summary = EventAnalyticsResource::fromSummary(
                app(EventAnalyticsQueryService::class)->summary($id, $actor, 'csv_export'),
            );
        } catch (EventAnalyticsException $exception) {
            return $this->eventsAnalyticsFailure($exception, $tenantSlug, $id);
        }

        $headers = LocaleContext::withLocale($actor, static fn (): array => [
            __('event_analytics.csv.metric'),
            __('event_analytics.csv.value'),
            __('event_analytics.csv.suppressed'),
        ]);
        $rows = EventAnalyticsCsv::rows($summary);
        $response = response()->streamDownload(
            static function () use ($headers, $rows): void {
                $stream = fopen('php://output', 'wb');
                if ($stream === false) {
                    return;
                }
                fwrite($stream, "\xEF\xBB\xBF");
                fputcsv($stream, $headers);
                foreach ($rows as $row) {
                    fputcsv($stream, $row);
                }
                fclose($stream);
            },
            "event-{$id}-analytics.csv",
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'private, no-store',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
        $response->setVary('Cookie', false);

        return $response;
    }

    private function eventsAnalyticsActor(string $tenantSlug): User|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', compact('tenantSlug'));
        }

        return $this->accessibleEventActor($userId);
    }

    private function eventsAnalyticsFailure(
        EventAnalyticsException $exception,
        string $tenantSlug,
        int $id,
    ): RedirectResponse {
        if (in_array($exception->reasonCode, [
            'event_analytics_event_not_found',
            'event_analytics_actor_invalid',
        ], true)) {
            abort(404);
        }
        if (in_array($exception->reasonCode, [
            'event_analytics_schema_unavailable',
            'event_analytics_feature_disabled',
            'event_analytics_tenant_context_missing',
        ], true)) {
            abort(503);
        }

        return redirect()
            ->route('govuk-alpha.events.show', compact('tenantSlug', 'id'))
            ->withErrors(['analytics' => __('govuk_alpha.events.analytics.load_error')]);
    }

}
