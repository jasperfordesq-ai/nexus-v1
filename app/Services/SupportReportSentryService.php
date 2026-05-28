<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\SupportReport;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Sentry\Severity;
use Sentry\State\Scope;

class SupportReportSentryService
{
    public function captureCreated(SupportReport $report, ?User $user, ?string $frontendEventId = null): ?string
    {
        if (!class_exists(\Sentry\SentrySdk::class)) {
            return null;
        }

        $hub = \Sentry\SentrySdk::getCurrentHub();
        if ($hub->getClient() === null) {
            return null;
        }

        try {
            $eventId = \Sentry\withScope(function (Scope $scope) use ($report, $user, $frontendEventId) {
                $scope->setTag('source', 'support_report');
                $scope->setTag('support_report_reference', (string) $report->reference);
                $scope->setTag('support_report_impact', (string) $report->impact);
                $scope->setTag('support_report_status', (string) $report->status);
                $scope->setTag('tenant_id', (string) $report->tenant_id);

                if ($report->route) {
                    $scope->setTag('route', (string) $report->route);
                }

                if ($user) {
                    $scope->setUser(['id' => (string) $user->id]);
                }

                $scope->setFingerprint(['support-report', (string) $report->reference]);
                $scope->setContext('support_report', [
                    'id' => $report->id,
                    'reference' => $report->reference,
                    'summary' => $report->summary,
                    'impact' => $report->impact,
                    'status' => $report->status,
                    'source' => $report->source,
                    'route' => $report->route,
                    'page_url' => $report->page_url,
                    'frontend_event_id' => $frontendEventId,
                    'has_diagnostics' => $report->diagnostics !== null,
                ]);

                if ($report->diagnostics !== null) {
                    $scope->setExtra('support_report_diagnostics', $report->diagnostics);
                }

                return \Sentry\captureMessage(
                    sprintf('Nexus support report %s: %s', $report->reference, $report->summary),
                    Severity::warning(),
                );
            });

            return $eventId === null ? null : (string) $eventId;
        } catch (\Throwable $e) {
            Log::warning('[SupportReportSentryService] support report Sentry capture failed', [
                'report_id' => $report->id,
                'tenant_id' => $report->tenant_id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
