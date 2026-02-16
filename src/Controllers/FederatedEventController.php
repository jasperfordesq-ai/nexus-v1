<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationFeatureService;
use Nexus\Services\FederationPartnershipService;
use Nexus\Services\FederationUserService;

/**
 * Federated Event Controller
 *
 * Browse and join events from partner timebanks
 */
class FederatedEventController
{
    /**
     * Federated events directory
     */
    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $tenantId = TenantContext::getId();
        $userId = $_SESSION['user_id'];

        // Check if federation is enabled
        $federationEnabled = FederationFeatureService::isGloballyEnabled()
            && FederationFeatureService::isTenantWhitelisted($tenantId)
            && FederationFeatureService::isTenantFederationEnabled($tenantId);

        if (!$federationEnabled) {
            View::render('federation/not-available', [
                'pageTitle' => 'Federation Not Available'
            ]);
            return;
        }

        // Get active partnerships with events enabled
        $partnerships = FederationPartnershipService::getTenantPartnerships($tenantId);
        $partnerTenantIds = [];
        foreach ($partnerships as $p) {
            if ($p['status'] === 'active' && $p['events_enabled']) {
                $partnerTenantIds[] = ($p['tenant_id'] == $tenantId)
                    ? $p['partner_tenant_id']
                    : $p['tenant_id'];
            }
        }

        // Build filters
        $filters = [
            'search' => $_GET['q'] ?? '',
            'tenant_id' => isset($_GET['tenant']) ? (int)$_GET['tenant'] : null,
            'remote_only' => isset($_GET['remote']),
            'upcoming_only' => !isset($_GET['past']),
            'limit' => 30,
            'offset' => (int)($_GET['offset'] ?? 0),
        ];

        // Get federated events
        $events = $this->getFederatedEvents($partnerTenantIds, $filters);

        // Get partner tenant info for filter dropdown
        $partnerTenants = $this->getPartnerTenantInfo($partnerTenantIds);

        // Get partner communities for scope switcher (if any)
        $partnerCommunities = array_map(fn($t) => [
            'id' => $t['id'],
            'name' => $t['name']
        ], $partnerTenants);

        $currentScope = $_GET['scope'] ?? 'all';

        $viewPath = 'federation/events';

        View::render($viewPath, [
            'events' => $events,
            'partnerTenants' => $partnerTenants,
            'filters' => $filters,
            'partnerCommunities' => $partnerCommunities,
            'currentScope' => $currentScope,
            'pageTitle' => 'Federated Events'
        ]);
    }

    /**
     * API endpoint for AJAX search
     */
    public function api()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $tenantId = TenantContext::getId();

        // Check federation enabled
        if (!FederationFeatureService::isTenantFederationEnabled($tenantId)) {
            echo json_encode(['error' => 'Federation not enabled', 'events' => []]);
            exit;
        }

        // Get partner tenant IDs with events enabled
        $partnerships = FederationPartnershipService::getTenantPartnerships($tenantId);
        $partnerTenantIds = [];
        foreach ($partnerships as $p) {
            if ($p['status'] === 'active' && $p['events_enabled']) {
                $partnerTenantIds[] = ($p['tenant_id'] == $tenantId)
                    ? $p['partner_tenant_id']
                    : $p['tenant_id'];
            }
        }

        $filters = [
            'search' => $_GET['q'] ?? '',
            'tenant_id' => isset($_GET['tenant']) ? (int)$_GET['tenant'] : null,
            'remote_only' => isset($_GET['remote']),
            'upcoming_only' => !isset($_GET['past']),
            'limit' => min((int)($_GET['limit'] ?? 30), 50),
            'offset' => max(0, (int)($_GET['offset'] ?? 0)),
        ];

        $events = $this->getFederatedEvents($partnerTenantIds, $filters);

        echo json_encode([
            'success' => true,
            'events' => $events,
            'hasMore' => count($events) >= $filters['limit'],
        ]);
        exit;
    }

    /**
     * View a federated event
     */
    public function show($eventId)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $tenantId = TenantContext::getId();
        $userId = $_SESSION['user_id'];
        $eventId = (int)$eventId;

        // Get the event with organizer and tenant info
        $event = Database::query(
            "SELECT e.*, u.name as organizer_name, u.avatar_url as organizer_avatar,
                    u.id as organizer_id, e.tenant_id as event_tenant_id,
                    t.name as tenant_name, t.domain as tenant_domain,
                    (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'going') as attendee_count
             FROM events e
             INNER JOIN users u ON e.user_id = u.id
             INNER JOIN tenants t ON e.tenant_id = t.id
             WHERE e.id = ?
             AND e.federated_visibility IN ('listed', 'joinable')",
            [$eventId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$event) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        // Check if from a partner tenant with events enabled
        $canView = false;
        $canJoin = false;
        $partnerships = FederationPartnershipService::getTenantPartnerships($tenantId);
        foreach ($partnerships as $p) {
            if ($p['status'] === 'active' && $p['events_enabled']) {
                $partnerTenant = ($p['tenant_id'] == $tenantId) ? $p['partner_tenant_id'] : $p['tenant_id'];
                if ($partnerTenant == $event['event_tenant_id']) {
                    $canView = true;
                    $canJoin = $event['federated_visibility'] === 'joinable';
                    break;
                }
            }
        }

        if (!$canView && $event['event_tenant_id'] != $tenantId) {
            http_response_code(403);
            View::render('errors/403', [
                'message' => 'You do not have permission to view this event.'
            ]);
            return;
        }

        // Check if user is attending
        $isAttending = $this->isUserAttending($eventId, $userId);

        View::render('federation/event-detail', [
            'event' => $event,
            'canJoin' => $canJoin && !$isAttending,
            'isAttending' => $isAttending,
            'pageTitle' => $event['title'] ?? 'Event'
        ]);
    }

    /**
     * Register for a federated event
     */
    public function register($eventId)
    {
        \Nexus\Core\Csrf::verifyOrDie();

        if (!isset($_SESSION['user_id'])) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
            }
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $tenantId = TenantContext::getId();
        $userId = $_SESSION['user_id'];
        $eventId = (int)$eventId;

        // Verify event exists and is joinable
        $event = Database::query(
            "SELECT e.*, e.tenant_id as event_tenant_id
             FROM events e
             WHERE e.id = ?
             AND e.federated_visibility = 'joinable'
             AND e.start_time > NOW()",
            [$eventId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$event) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'error' => 'Event not found or registration closed'], 404);
            }
            $_SESSION['flash_error'] = 'Event not found or registration is closed';
            header('Location: ' . TenantContext::getBasePath() . '/federation/events');
            exit;
        }

        // Check partnership allows events
        $canJoin = false;
        $partnerships = FederationPartnershipService::getTenantPartnerships($tenantId);
        foreach ($partnerships as $p) {
            if ($p['status'] === 'active' && $p['events_enabled']) {
                $partnerTenant = ($p['tenant_id'] == $tenantId) ? $p['partner_tenant_id'] : $p['tenant_id'];
                if ($partnerTenant == $event['event_tenant_id']) {
                    $canJoin = true;
                    break;
                }
            }
        }

        if (!$canJoin) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'error' => 'Events not enabled with this timebank'], 403);
            }
            $_SESSION['flash_error'] = 'Cannot register for events from this timebank';
            header('Location: ' . TenantContext::getBasePath() . '/federation/events');
            exit;
        }

        // Check max attendees
        if ($event['max_attendees']) {
            $attendeeCount = Database::query(
                "SELECT COUNT(*) as count FROM event_rsvps WHERE event_id = ? AND status = 'going'",
                [$eventId]
            )->fetch(\PDO::FETCH_ASSOC)['count'];

            if ($attendeeCount >= $event['max_attendees']) {
                if ($this->isAjax()) {
                    $this->jsonResponse(['success' => false, 'error' => 'Event is full'], 400);
                }
                $_SESSION['flash_error'] = 'This event is already full';
                header('Location: ' . TenantContext::getBasePath() . '/federation/events/' . $eventId);
                exit;
            }
        }

        // Check if already registered
        if ($this->isUserAttending($eventId, $userId)) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'error' => 'Already registered'], 400);
            }
            $_SESSION['flash_error'] = 'You are already registered for this event';
            header('Location: ' . TenantContext::getBasePath() . '/federation/events/' . $eventId);
            exit;
        }

        // Register the user
        try {
            Database::query(
                "INSERT INTO event_rsvps (tenant_id, event_id, user_id, status, is_federated, source_tenant_id, created_at)
                 VALUES (?, ?, ?, 'going', 1, ?, NOW())",
                [$event['event_tenant_id'], $eventId, $userId, $tenantId]
            );

            // Log the federated action
            \Nexus\Services\FederationAuditService::log(
                'federated_event_registration',
                $tenantId,
                $event['event_tenant_id'],
                $userId,
                ['event_id' => $eventId]
            );

            if ($this->isAjax()) {
                $this->jsonResponse(['success' => true, 'message' => 'Successfully registered!']);
            }

            $_SESSION['flash_success'] = 'Successfully registered for the event!';
            header('Location: ' . TenantContext::getBasePath() . '/federation/events/' . $eventId);
            exit;

        } catch (\Exception $e) {
            error_log("FederatedEventController::register error: " . $e->getMessage());
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'error' => 'Registration failed'], 500);
            }
            $_SESSION['flash_error'] = 'Registration failed. Please try again.';
            header('Location: ' . TenantContext::getBasePath() . '/federation/events/' . $eventId);
            exit;
        }
    }

    /**
     * Get federated events from partner tenants
     */
    private function getFederatedEvents(array $partnerTenantIds, array $filters): array
    {
        if (empty($partnerTenantIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($partnerTenantIds), '?'));
        $params = $partnerTenantIds;

        $sql = "SELECT e.id, e.title, e.description, e.location, e.start_time, e.end_time,
                       e.max_attendees, e.cover_image, e.allow_remote_attendance, e.tenant_id,
                       u.name as organizer_name, u.avatar_url as organizer_avatar,
                       t.name as tenant_name,
                       (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'going') as attendee_count
                FROM events e
                INNER JOIN users u ON e.user_id = u.id
                INNER JOIN tenants t ON e.tenant_id = t.id
                WHERE e.tenant_id IN ({$placeholders})
                AND e.federated_visibility IN ('listed', 'joinable')";

        // Apply upcoming only filter
        if ($filters['upcoming_only']) {
            $sql .= " AND e.start_time > NOW()";
        }

        // Apply tenant filter
        if (!empty($filters['tenant_id']) && in_array($filters['tenant_id'], $partnerTenantIds)) {
            $sql .= " AND e.tenant_id = ?";
            $params[] = $filters['tenant_id'];
        }

        // Apply remote filter
        if ($filters['remote_only']) {
            $sql .= " AND e.allow_remote_attendance = 1";
        }

        // Apply search filter
        if (!empty($filters['search'])) {
            $sql .= " AND (e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY e.start_time ASC LIMIT ? OFFSET ?";
        $params[] = $filters['limit'];
        $params[] = $filters['offset'];

        try {
            return Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("FederatedEventController::getFederatedEvents error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get partner tenant info for filter dropdown
     */
    private function getPartnerTenantInfo(array $tenantIds): array
    {
        if (empty($tenantIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($tenantIds), '?'));

        try {
            return Database::query(
                "SELECT id, name, domain FROM tenants WHERE id IN ({$placeholders}) ORDER BY name",
                $tenantIds
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if user is attending an event
     */
    private function isUserAttending(int $eventId, int $userId): bool
    {
        try {
            $result = Database::query(
                "SELECT id FROM event_rsvps WHERE event_id = ? AND user_id = ? AND status = 'going'",
                [$eventId, $userId]
            )->fetch();
            return $result !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if request is AJAX
     */
    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Send JSON response
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
