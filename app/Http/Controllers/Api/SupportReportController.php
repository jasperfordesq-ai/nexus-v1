<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Models\SupportReport;
use App\Models\User;
use App\Services\SupportReportNotificationService;
use App\Services\SupportReportSentryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SupportReportController extends BaseApiController
{
    protected bool $isV2Api = true;

    private const ALLOWED_IMPACTS = ['blocked', 'major', 'minor', 'cosmetic'];
    private const FILTERED = '[filtered]';
    private const MAX_DIAGNOSTIC_DEPTH = 6;
    private const MAX_DIAGNOSTIC_ITEMS = 80;
    private const MAX_DIAGNOSTIC_STRING_LENGTH = 2000;
    private const SENSITIVE_KEY_PATTERN = '/(authorization|password|passcode|token|secret|cookie|csrf|session|email|phone|address|credit|card|cvv|iban|sort_code)/i';

    public function store(Request $request): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $validator = Validator::make($request->all(), [
            'summary' => ['required', 'string', 'min:3', 'max:180'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'impact' => ['required', 'string', 'in:' . implode(',', self::ALLOWED_IMPACTS)],
            'module' => ['nullable', 'string', 'max:100'],
            'route' => ['nullable', 'string', 'max:255'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'sentry_event_id' => ['nullable', 'string', 'max:191'],
            'sentry_issue_url' => ['nullable', 'string', 'max:2048'],
            'include_diagnostics' => ['sometimes', 'boolean'],
            'diagnostics' => ['nullable', 'array'],
        ], [
            'summary.required' => __('api.support_reports_summary_required'),
            'summary.max' => __('api.support_reports_summary_max'),
            'description.required' => __('api.support_reports_description_required'),
            'description.min' => __('api.support_reports_description_min'),
            'description.max' => __('api.support_reports_description_max'),
            'impact.required' => __('api.support_reports_impact_required'),
            'impact.in' => __('api.support_reports_impact_invalid'),
        ]);

        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->messages() as $field => $messages) {
                $errors[] = [
                    'code' => 'VALIDATION_FAILED',
                    'message' => (string) ($messages[0] ?? __('api.validation_failed')),
                    'field' => $field,
                ];
            }

            return $this->respondWithErrors($errors, 422);
        }

        $validated = $validator->validated();
        $includeDiagnostics = (bool) ($validated['include_diagnostics'] ?? false);
        $diagnostics = $includeDiagnostics
            ? $this->normaliseDiagnostics($validated['diagnostics'] ?? null)
            : null;

        $report = SupportReport::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'reference' => $this->generateReference(),
            'source' => 'in_app',
            'summary' => trim((string) $validated['summary']),
            'description' => trim((string) $validated['description']),
            'impact' => (string) $validated['impact'],
            'status' => 'open',
            'module' => $this->nullableString($validated['module'] ?? null),
            'route' => $this->nullableString($validated['route'] ?? null),
            'page_url' => $this->nullableString($validated['page_url'] ?? null),
            'sentry_event_id' => $this->nullableString($validated['sentry_event_id'] ?? null),
            'sentry_issue_url' => $this->nullableString($validated['sentry_issue_url'] ?? null),
            'diagnostics' => $diagnostics,
            'user_agent' => $this->nullableString($request->userAgent(), 512),
            'ip_hash' => $this->hashIpAddress($request->ip()),
        ]);

        try {
            $sentryEventId = app(SupportReportSentryService::class)->captureCreated(
                $report,
                User::query()->find($userId),
                $this->nullableString($validated['sentry_event_id'] ?? null, 191),
            );

            if ($sentryEventId !== null) {
                $report->sentry_event_id = $sentryEventId;
                $report->save();
            }
        } catch (\Throwable $e) {
            Log::warning('[SupportReportController] support report Sentry capture failed', [
                'report_id' => $report->id,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            SupportReportNotificationService::notifyCreated($report);
        } catch (\Throwable $e) {
            Log::warning('[SupportReportController] support report notification failed', [
                'report_id' => $report->id,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->respondWithData([
            'report' => [
                'id' => $report->id,
                'reference' => $report->reference,
                'status' => $report->status,
                'impact' => $report->impact,
                'summary' => $report->summary,
                'created_at' => $report->created_at?->toIso8601String(),
            ],
        ], null, 201);
    }

    private function generateReference(): string
    {
        do {
            $reference = 'NXR-' . now()->format('ymd') . '-' . Str::upper(Str::random(6));
        } while (SupportReport::withoutGlobalScopes()->where('reference', $reference)->exists());

        return $reference;
    }

    private function normaliseDiagnostics(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        return [
            'captured_at' => now()->toIso8601String(),
            'payload' => $this->redactDiagnosticValue($value),
        ];
    }

    private function redactDiagnosticValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth > self::MAX_DIAGNOSTIC_DEPTH) {
            return '[truncated]';
        }

        if (is_array($value)) {
            $redacted = [];
            $count = 0;
            foreach ($value as $key => $item) {
                if ($count >= self::MAX_DIAGNOSTIC_ITEMS) {
                    $redacted['__truncated'] = true;
                    break;
                }

                $safeKey = is_int($key) ? $key : $this->redactDiagnosticKey((string) $key);
                $redacted[$safeKey] = is_string($key) && preg_match(self::SENSITIVE_KEY_PATTERN, $key)
                    ? self::FILTERED
                    : $this->redactDiagnosticValue($item, $depth + 1);
                $count++;
            }

            return $redacted;
        }

        if (is_string($value)) {
            return $this->redactDiagnosticString($value);
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return $this->redactDiagnosticString((string) $value);
    }

    private function redactDiagnosticKey(string $key): string
    {
        if (preg_match(self::SENSITIVE_KEY_PATTERN, $key)) {
            return self::FILTERED;
        }

        return Str::limit($key, 120, '');
    }

    private function redactDiagnosticString(string $value): string
    {
        $redacted = preg_replace('/Bearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer ' . self::FILTERED, $value) ?? $value;
        $redacted = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', self::FILTERED, $redacted) ?? $redacted;

        return Str::limit($redacted, self::MAX_DIAGNOSTIC_STRING_LENGTH, '');
    }

    private function nullableString(mixed $value, int $maxLength = 2048): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }

        return Str::limit($string, $maxLength, '');
    }

    private function hashIpAddress(?string $ipAddress): ?string
    {
        if (!$ipAddress) {
            return null;
        }

        $key = config('app.key') ?: 'nexus-support-report-ip-hash';

        return hash_hmac('sha256', $ipAddress, (string) $key);
    }
}
