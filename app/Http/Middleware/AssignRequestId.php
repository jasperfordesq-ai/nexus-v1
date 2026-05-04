<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->requestIdFromHeader($request) ?? (string) Str::uuid();

        $request->headers->set(self::HEADER, $requestId);
        $request->attributes->set('request_id', $requestId);

        Log::shareContext([
            'request_id' => $requestId,
        ]);

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $response = $this->renderException($request, $e);
        }

        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }

    private function requestIdFromHeader(Request $request): ?string
    {
        $requestId = trim((string) $request->headers->get(self::HEADER, ''));

        if ($requestId === '') {
            return null;
        }

        if (!preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $requestId)) {
            return null;
        }

        return $requestId;
    }

    private function renderException(Request $request, Throwable $e): Response
    {
        try {
            return app(ExceptionHandler::class)->render($request, $e);
        } catch (Throwable) {
            return response()->json(['message' => __('api.server_error')], 500);
        }
    }
}
