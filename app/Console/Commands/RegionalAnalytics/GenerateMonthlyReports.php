<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands\RegionalAnalytics;

use App\Core\TenantContext;
use App\Services\RegionalAnalytics\RegionalAnalyticsService;
use App\Services\RegionalAnalytics\RegionalReportPdfGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * AG59 — Generate monthly regional analytics reports for active subscriptions.
 *
 * Runs on the 1st of each month at 06:00 (registered in bootstrap/app.php).
 * Per active subscription:
 *   - Resolves last calendar month
 *   - Builds the bucketed payload (privacy-preserving)
 *   - Renders PDF and stores it
 *   - Inserts a regional_analytics_reports row
 *   - Emails the PDF link to recipient_emails
 */
class GenerateMonthlyReports extends Command
{
    protected $signature = 'regional-analytics:generate-monthly {--subscription= : Limit to a single subscription id}';

    protected $description = 'Generate monthly regional analytics reports for active subscriptions';

    public function handle(
        RegionalAnalyticsService $analytics,
        RegionalReportPdfGenerator $pdf
    ): int {
        $now = Carbon::now();
        $periodStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $periodEnd = $now->copy()->subMonthNoOverflow()->endOfMonth();
        $periodLabel = $periodStart->format('Y-m');

        $query = DB::table('regional_analytics_subscriptions')
            ->whereIn('status', ['active', 'trialing']);

        if ($subId = $this->option('subscription')) {
            $query->where('id', (int) $subId);
        }

        $subs = $query->get();
        $this->info(sprintf('Generating reports for %d subscription(s) — period %s', $subs->count(), $periodLabel));

        foreach ($subs as $sub) {
            try {
                TenantContext::setById((int) $sub->tenant_id);

                $modules = is_string($sub->enabled_modules ?? null)
                    ? (json_decode($sub->enabled_modules, true) ?: [])
                    : [];

                $payload = $analytics->buildDashboardPayload(
                    (int) $sub->tenant_id,
                    'last_30d',
                    $modules
                );
                // Override the period to be the previous calendar month.
                $payload['period'] = $periodLabel;
                $payload['period_start'] = $periodStart->toDateString();
                $payload['period_end'] = $periodEnd->toDateString();

                $reportId = DB::table('regional_analytics_reports')->insertGetId([
                    'subscription_id' => $sub->id,
                    'tenant_id' => $sub->tenant_id,
                    'report_type' => 'monthly_summary',
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'status' => 'queued',
                    'recipient_emails' => json_encode([$sub->contact_email]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $fileUrl = $pdf->generateAndStore($payload, (int) $sub->id, $periodLabel);

                DB::table('regional_analytics_reports')->where('id', $reportId)->update([
                    'status' => 'generated',
                    'generated_at' => now(),
                    'file_url' => $fileUrl,
                    'payload_json' => json_encode($payload),
                    'updated_at' => now(),
                ]);

                $this->maybeMail($sub, $fileUrl, $periodLabel);

                DB::table('regional_analytics_reports')->where('id', $reportId)->update([
                    'status' => 'sent',
                    'updated_at' => now(),
                ]);

                $this->info(sprintf('  - subscription %d: report %d generated', $sub->id, $reportId));
            } catch (\Throwable $e) {
                $this->error(sprintf('  - subscription %d failed: %s', $sub->id, $e->getMessage()));
                if (isset($reportId)) {
                    DB::table('regional_analytics_reports')->where('id', $reportId)->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'updated_at' => now(),
                    ]);
                }
            } finally {
                TenantContext::clear();
            }
        }

        return self::SUCCESS;
    }

    private function maybeMail(object $sub, string $fileUrl, string $periodLabel): void
    {
        try {
            $email = (string) ($sub->contact_email ?? '');
            if ($email === '') {
                return;
            }
            Mail::raw(
                "Your regional analytics report for {$periodLabel} is ready.\n\nDownload: {$fileUrl}\n\nAll figures are bucketed per the Project NEXUS privacy guarantees.",
                function ($m) use ($email, $periodLabel) {
                    $m->to($email)->subject("Regional analytics report — {$periodLabel}");
                }
            );
        } catch (\Throwable $e) {
            // Mail errors should not fail the run.
        }
    }
}
