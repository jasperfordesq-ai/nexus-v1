<?php

declare(strict_types=1);

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\Auth;
use Nexus\Core\Csrf;
use Nexus\Services\CookieConsentService;
use Nexus\Services\CookieInventoryService;

/**
 * Cookie Consent API Controller
 *
 * REST API endpoints for cookie consent management.
 * Handles consent recording, retrieval, updates, and withdrawal.
 */
class CookieConsentController
{
    /**
     * Send JSON response
     *
     * @param mixed $data Data to send
     * @param int $status HTTP status code
     * @return void
     */
    private function jsonResponse($data, int $status = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    /**
     * Get JSON input from request body
     *
     * @return array Decoded JSON data
     */
    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $decoded = json_decode($input, true);
        return $decoded ?? [];
    }

    /**
     * Verify CSRF token from request
     *
     * @param array $input Request data
     * @return bool True if valid
     */
    private function verifyCsrf(array $input): bool
    {
        $token = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return Csrf::verify($token);
    }

    /**
     * GET /api/cookie-consent
     * Get current user's consent status
     *
     * @return void
     */
    public function show(): void
    {
        try {
            $userId = Auth::id();
            $sessionId = session_id();

            $consent = CookieConsentService::getConsent($userId, $sessionId);

            if ($consent) {
                $this->jsonResponse([
                    'success' => true,
                    'consent' => [
                        'id' => (int) $consent['id'],
                        'essential' => (bool) $consent['essential'],
                        'functional' => (bool) $consent['functional'],
                        'analytics' => (bool) $consent['analytics'],
                        'marketing' => (bool) $consent['marketing'],
                        'created_at' => $consent['created_at'],
                        'expires_at' => $consent['expires_at'],
                        'version' => $consent['consent_version']
                    ]
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'consent' => null,
                    'message' => 'No consent record found'
                ], 404);
            }
        } catch (\Exception $e) {
            error_log("Cookie consent retrieval error: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to retrieve consent status'
            ], 500);
        }
    }

    /**
     * POST /api/cookie-consent
     * Record new cookie consent
     *
     * Expected body:
     * {
     *   "functional": true|false,
     *   "analytics": true|false,
     *   "marketing": true|false,
     *   "csrf_token": "..."
     * }
     *
     * @return void
     */
    public function store(): void
    {
        $input = $this->getJsonInput();

        // Verify CSRF token
        if (!$this->verifyCsrf($input)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Invalid security token'
            ], 403);
        }

        try {
            // Record consent
            $consent = CookieConsentService::recordConsent([
                'functional' => $input['functional'] ?? false,
                'analytics' => $input['analytics'] ?? false,
                'marketing' => $input['marketing'] ?? false,
                'source' => $input['source'] ?? 'web'
            ]);

            // Log activity
            if (Auth::check()) {
                \Nexus\Models\ActivityLog::log(
                    Auth::id(),
                    'cookie_consent_saved',
                    'Cookie consent preferences saved'
                );
            }

            $this->jsonResponse([
                'success' => true,
                'message' => 'Consent preferences saved successfully',
                'consent' => $consent
            ], 201);

        } catch (\Exception $e) {
            error_log("Cookie consent save error: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to save consent preferences'
            ], 500);
        }
    }

    /**
     * PUT /api/cookie-consent/{id}
     * Update existing consent
     *
     * @param int $id Consent ID
     * @return void
     */
    public function update(int $id): void
    {
        $input = $this->getJsonInput();

        // Verify CSRF token
        if (!$this->verifyCsrf($input)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Invalid security token'
            ], 403);
        }

        try {
            $categories = [
                'functional' => $input['functional'] ?? false,
                'analytics' => $input['analytics'] ?? false,
                'marketing' => $input['marketing'] ?? false
            ];

            $success = CookieConsentService::updateConsent($id, $categories);

            if ($success) {
                // Log activity
                if (Auth::check()) {
                    \Nexus\Models\ActivityLog::log(
                        Auth::id(),
                        'cookie_consent_updated',
                        'Cookie consent preferences updated'
                    );
                }

                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Consent preferences updated successfully',
                    'consent' => array_merge(['id' => $id], $categories)
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Consent record not found'
                ], 404);
            }

        } catch (\Exception $e) {
            error_log("Cookie consent update error: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to update consent preferences'
            ], 500);
        }
    }

    /**
     * DELETE /api/cookie-consent/{id}
     * Withdraw consent
     *
     * @param int $id Consent ID
     * @return void
     */
    public function withdraw(int $id): void
    {
        $input = $this->getJsonInput();

        // Verify CSRF token
        if (!$this->verifyCsrf($input)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Invalid security token'
            ], 403);
        }

        try {
            $success = CookieConsentService::withdrawConsent($id);

            if ($success) {
                // Log activity
                if (Auth::check()) {
                    \Nexus\Models\ActivityLog::log(
                        Auth::id(),
                        'cookie_consent_withdrawn',
                        'Cookie consent withdrawn'
                    );
                }

                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Consent withdrawn successfully'
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Consent record not found'
                ], 404);
            }

        } catch (\Exception $e) {
            error_log("Cookie consent withdrawal error: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to withdraw consent'
            ], 500);
        }
    }

    /**
     * GET /api/cookie-consent/inventory
     * Get cookie inventory for banner display
     *
     * @return void
     */
    public function inventory(): void
    {
        try {
            $tenantId = TenantContext::getId();
            $cookies = CookieInventoryService::getAllCookies($tenantId);
            $counts = CookieInventoryService::getCookieCounts($tenantId);
            $tenantSettings = CookieConsentService::getTenantSettings($tenantId);

            $this->jsonResponse([
                'success' => true,
                'cookies' => $cookies,
                'counts' => $counts,
                'settings' => $tenantSettings
            ]);

        } catch (\Exception $e) {
            error_log("Cookie inventory retrieval error: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to retrieve cookie inventory'
            ], 500);
        }
    }

    /**
     * GET /api/cookie-consent/check/{category}
     * Check if user has consented to a specific category
     *
     * @param string $category Category to check (functional, analytics, marketing)
     * @return void
     */
    public function check(string $category): void
    {
        $validCategories = ['essential', 'functional', 'analytics', 'marketing'];

        if (!in_array($category, $validCategories)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Invalid category'
            ], 400);
        }

        try {
            $hasConsent = CookieConsentService::hasConsent($category);

            $this->jsonResponse([
                'success' => true,
                'category' => $category,
                'hasConsent' => $hasConsent
            ]);

        } catch (\Exception $e) {
            error_log("Cookie consent check error: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to check consent status'
            ], 500);
        }
    }
}
