<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Application Exception Handler
 *
 * All API responses are JSON.  In production, internal details are hidden;
 * in development, full stack traces are included.
 */
class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Sentry integration — report to Sentry in production
            if (app()->bound('sentry') && $this->shouldReport($e)) {
                app('sentry')->captureException($e);
            }
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * Since NEXUS is API-only, every response is JSON.
     */
    public function render($request, Throwable $e): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        // Always return JSON for API requests
        if ($this->shouldReturnJson($request, $e)) {
            return $this->renderJsonResponse($request, $e);
        }

        // Fallback — still return JSON since this is an API-only app
        return $this->renderJsonResponse($request, $e);
    }

    /**
     * Determine if the exception should be rendered as JSON.
     */
    protected function shouldReturnJson($request, Throwable $e): bool
    {
        // NEXUS is API-only — always return JSON
        return true;
    }

    /**
     * Build a structured JSON error response.
     */
    protected function renderJsonResponse(Request $request, Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'You must be logged in to access this resource.',
            ], 401);
        }

        if ($e instanceof ModelNotFoundException) {
            $model = class_basename($e->getModel());
            return response()->json([
                'error' => 'Not found',
                'message' => "{$model} not found.",
            ], 404);
        }

        if ($e instanceof NotFoundHttpException) {
            return response()->json([
                'error' => 'Not found',
                'message' => 'The requested resource was not found.',
            ], 404);
        }

        if ($e instanceof TooManyRequestsHttpException) {
            return response()->json([
                'error' => 'Too many requests',
                'message' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $e->getHeaders()['Retry-After'] ?? null,
            ], 429);
        }

        if ($e instanceof HttpException) {
            return response()->json([
                'error' => 'HTTP error',
                'message' => $e->getMessage() ?: 'An error occurred.',
            ], $e->getStatusCode());
        }

        // Generic server error
        $status = 500;
        $response = [
            'error' => 'Server error',
            'message' => 'An unexpected error occurred.',
        ];

        // In development, include debug details
        if (config('app.debug')) {
            $response['exception'] = get_class($e);
            $response['message'] = $e->getMessage();
            $response['file'] = $e->getFile();
            $response['line'] = $e->getLine();
            $response['trace'] = collect($e->getTrace())->take(10)->toArray();
        }

        return response()->json($response, $status);
    }

    /**
     * Convert an authentication exception into a JSON response.
     */
    protected function unauthenticated($request, AuthenticationException $exception): JsonResponse
    {
        return response()->json([
            'error' => 'Unauthenticated',
            'message' => 'You must be logged in to access this resource.',
        ], 401);
    }
}
