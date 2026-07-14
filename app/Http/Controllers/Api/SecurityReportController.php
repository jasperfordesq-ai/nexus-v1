<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class SecurityReportController
{
    public function csp(Request $request): Response
    {
        if ((int) $request->server('CONTENT_LENGTH', 0) > 32 * 1024) {
            abort(413);
        }
        $report = json_decode($request->getContent(), true);
        if (! is_array($report)) {
            return response()->noContent();
        }
        if (array_is_list($report)) {
            $report = is_array($report[0] ?? null) ? $report[0] : [];
        }
        $body = $report['csp-report'] ?? $report['body'] ?? $report;
        $body = is_array($body) ? $body : [];
        Log::warning('security.csp_violation', [
            'document' => $this->originAndPath($body['document-uri'] ?? $body['documentURL'] ?? null),
            'blocked' => $this->originAndPath($body['blocked-uri'] ?? $body['blockedURL'] ?? null),
            'directive' => mb_substr((string) ($body['violated-directive'] ?? $body['effectiveDirective'] ?? ''), 0, 120),
            'source_file' => $this->originAndPath($body['source-file'] ?? $body['sourceFile'] ?? null),
            'line' => (int) ($body['line-number'] ?? $body['lineNumber'] ?? 0),
            'request_id' => $request->attributes->get('request_id'),
        ]);

        return response()->noContent();
    }

    private function originAndPath(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') return null;
        $parts = parse_url($value);
        if ($parts === false) return null;
        return mb_substr(($parts['scheme'] ?? '') . '://' . ($parts['host'] ?? '') . ($parts['path'] ?? ''), 0, 500);
    }
}
