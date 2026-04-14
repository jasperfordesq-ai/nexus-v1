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
            // Uses raw sentry/sentry SDK (not sentry-laravel) since the Laravel
            // integration package is not installed. Falls back to app('sentry')
            // binding if sentry-laravel is ever added.
            $sentryClient = null;

            if (app()->bound('sentry')) {
                $sentryClient = app('sentry');
            } elseif (class_exists(\Sentry\SentrySdk::class)) {
                $hub = \Sentry\SentrySdk::getCurrentHub();
                if ($hub->getClient() !== null) {
                    $sentryClient = $hub;
                }
            }

            if ($sentryClient && $this->shouldReport($e)) {
                // Enrich with tenant context
                \Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
                    try {
                        $tenantId = \App\Core\TenantContext::getId();
                        if ($tenantId) {
                            $scope->setTag('tenant_id', (string) $tenantId);
                            $tenant = \App\Core\TenantContext::get();
                            if ($tenant) {
                                $scope->setTag('tenant_slug', $tenant['slug'] ?? '');
                                $scope->setContext('tenant', [
                                    'id' => $tenantId,
                                    'name' => $tenant['name'] ?? '',
                                    'slug' => $tenant['slug'] ?? '',
                                ]);
                            }
                        }
                    } catch (\Throwable) {
                        // Tenant context may not be available — skip silently
                    }

                    // Enrich with user context (ID only — no PII)
                    try {
                        $user = auth()->user();
                        if ($user) {
                            $scope->setUser([
                                'id' => (string) $user->id,
                                'role' => $user->role ?? 'unknown',
                            ]);
                        }
                    } catch (\Throwable) {
                        // Auth may not be available — skip silently
                    }
                });

                if ($sentryClient instanceof \Sentry\State\HubInterface) {
                    $sentryClient->captureException($e);
                } else {
                    $sentryClient->captureException($e);
                }
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
     *
     * Uses the v2 API envelope format: { "errors": [{ "code", "message", "field"? }] }
     * This ensures consistency between controller-thrown errors and exception-thrown errors.
     *
     * For ValidationException, field errors are mapped into the errors array so the
     * frontend can display per-field validation messages.
     */
    protected function renderJsonResponse(Request $request, Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            $errors = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errors[] = [
                        'code' => 'VALIDATION_ERROR',
                        'message' => $message,
                        'field' => $field,
                    ];
                }
            }
            return response()->json(['errors' => $errors], 422);
        }

        if ($e instanceof AuthenticationException) {
            return $this->v2Error('AUTH_REQUIRED', 'You must be logged in to access this resource.', 401);
        }

        if ($e instanceof ModelNotFoundException) {
            $model = class_basename($e->getModel());
            return $this->v2Error('NOT_FOUND', "{$model} not found.", 404);
        }

        if ($e instanceof NotFoundHttpException) {
            return $this->v2Error('NOT_FOUND', 'The requested resource was not found.', 404);
        }

        if ($e instanceof TooManyRequestsHttpException) {
            $retryAfter = $e->getHeaders()['Retry-After'] ?? null;
            $response = response()->json([
                'errors' => [[
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => __('api.rate_limit_exceeded'),
                ]],
            ], 429);

            if ($retryAfter !== null) {
                $response->header('Retry-After', (string) $retryAfter);
            }

            return $response;
        }

        if ($e instanceof HttpException) {
            $statusCode = $e->getStatusCode();
            $code = match (true) {
                $statusCode === 403 => 'FORBIDDEN',
                $statusCode === 405 => 'METHOD_NOT_ALLOWED',
                $statusCode >= 500 => 'SERVER_ERROR',
                default => "HTTP_{$statusCode}",
            };
            return $this->v2Error($code, $e->getMessage() ?: 'An error occurred.', $statusCode);
        }

        // Generic server error
        $response = [
            'errors' => [[
                'code' => 'SERVER_ERROR',
                'message' => __('api.unexpected_error'),
            ]],
        ];

        // In development, include debug details alongside the v2 envelope
        if (config('app.debug')) {
            $response['debug'] = [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())->take(10)->toArray(),
            ];
        }

        return response()->json($response, 500);
    }

    /**
     * Build a v2 API error response: { "errors": [{ "code", "message" }] }
     */
    private function v2Error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'errors' => [[
                'code' => $code,
                'message' => $message,
            ]],
        ], $status);
    }

    /**
     * Convert an authentication exception into a JSON response.
     */
    protected function unauthenticated($request, AuthenticationException $exception): JsonResponse
    {
        return $this->v2Error('AUTH_REQUIRED', 'You must be logged in to access this resource.', 401);
    }
}
