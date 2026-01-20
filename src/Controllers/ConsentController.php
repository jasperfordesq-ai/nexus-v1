<?php

declare(strict_types=1);

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Core\SEO;
use Nexus\Services\Enterprise\GdprService;

/**
 * Consent Controller
 *
 * Handles consent re-acceptance when terms/privacy are updated.
 * Users are redirected here by consent_check.php when their accepted
 * version is older than the current version.
 */
class ConsentController
{
    /**
     * GET /consent-required
     * Display the consent re-acceptance page
     */
    public function required(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        // Use user's tenant_id from session
        $userTenantId = $_SESSION['tenant_id'] ?? null;
        $gdprService = new GdprService($userTenantId);
        $outdatedConsents = $gdprService->getOutdatedRequiredConsents($_SESSION['user_id']);

        // If no outdated consents, redirect to dashboard
        if (empty($outdatedConsents)) {
            unset($_SESSION['_pending_consents']);
            header('Location: ' . TenantContext::getBasePath() . '/dashboard');
            exit;
        }

        SEO::setTitle('Terms Update - Action Required');

        View::render('consent/required', [
            'pageTitle' => 'Updated Terms & Conditions',
            'consents' => $outdatedConsents,
            'basePath' => TenantContext::getBasePath()
        ]);
    }

    /**
     * POST /consent/accept
     * Process consent acceptance via AJAX
     */
    public function accept(): void
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        // Verify CSRF token
        $input = json_decode(file_get_contents('php://input'), true);
        $csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!Csrf::verify($csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid security token']);
            exit;
        }

        $acceptedConsents = $input['consents'] ?? [];

        if (empty($acceptedConsents)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No consents provided']);
            exit;
        }

        try {
            // Use user's tenant_id from session
            $userTenantId = $_SESSION['tenant_id'] ?? null;
            $gdprService = new GdprService($userTenantId);
            $results = $gdprService->acceptMultipleConsents(
                $_SESSION['user_id'],
                $acceptedConsents
            );

            // Clear pending consents from session
            unset($_SESSION['_pending_consents']);

            // Log consent acceptance
            \Nexus\Models\ActivityLog::log(
                $_SESSION['user_id'],
                'consent_accepted',
                'User accepted updated terms: ' . implode(', ', $acceptedConsents)
            );

            echo json_encode([
                'success' => true,
                'message' => 'Thank you for accepting the updated terms.',
                'redirect' => TenantContext::getBasePath() . '/dashboard'
            ]);

        } catch (\Exception $e) {
            error_log("Consent acceptance error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save consent. Please try again.']);
        }
        exit;
    }

    /**
     * GET /consent/decline
     * Show warning about declining consent
     */
    public function decline(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        SEO::setTitle('Unable to Continue');

        View::render('consent/decline', [
            'pageTitle' => 'Unable to Continue Without Consent',
            'basePath' => TenantContext::getBasePath()
        ]);
    }
}
