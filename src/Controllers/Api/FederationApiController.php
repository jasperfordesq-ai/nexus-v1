<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Helpers\CorsHelper;
use Nexus\Middleware\FederationApiMiddleware;
use Nexus\Services\FederationGateway;
use Nexus\Services\FederationSearchService;
use Nexus\Services\FederationUserService;
use Nexus\Services\FederationAuditService;
use Nexus\Services\FederationJwtService;

/**
 * FederationApiController
 *
 * External REST API for federation partner integrations.
 * Allows partner timebanks to query members, listings, and send messages/transactions.
 */
class FederationApiController
{
    /**
     * API root - returns API info and available endpoints
     * GET /api/v1/federation
     */
    public function index(): void
    {
        // No auth required for API info
        FederationApiMiddleware::sendSuccess([
            'api' => 'Federation API',
            'version' => '1.0',
            'documentation' => '/docs/api/federation',
            'endpoints' => [
                'GET /api/v1/federation/timebanks' => 'List partner timebanks',
                'GET /api/v1/federation/members' => 'Search federated members',
                'GET /api/v1/federation/members/{id}' => 'Get member profile',
                'GET /api/v1/federation/listings' => 'Search federated listings',
                'GET /api/v1/federation/listings/{id}' => 'Get listing details',
                'POST /api/v1/federation/messages' => 'Send federated message',
                'POST /api/v1/federation/transactions' => 'Initiate time credit transfer',
            ]
        ]);
    }

    /**
     * List available partner timebanks
     * GET /api/v1/federation/timebanks
     */
    public function timebanks(): void
    {
        if (!FederationApiMiddleware::authenticate()) return;
        if (!FederationApiMiddleware::requirePermission('timebanks:read')) return;

        $partnerTenantId = FederationApiMiddleware::getPartnerTenantId();
        $db = Database::getInstance();

        // Get active partnerships for this partner
        $stmt = $db->prepare("
            SELECT
                t.id,
                t.name,
                t.tagline,
                t.city,
                t.country,
                t.timezone,
                fp.status as partnership_status,
                fp.created_at as partnership_since,
                (SELECT COUNT(*) FROM federation_user_settings fus
                 WHERE fus.tenant_id = t.id AND fus.opted_in = 1) as member_count
            FROM federation_partnerships fp
            JOIN tenants t ON (
                (fp.tenant_id = ? AND t.id = fp.partner_tenant_id) OR
                (fp.partner_tenant_id = ? AND t.id = fp.tenant_id)
            )
            WHERE fp.status = 'active'
            ORDER BY t.name ASC
        ");
        $stmt->execute([$partnerTenantId, $partnerTenantId]);
        $timebanks = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        FederationApiMiddleware::sendSuccess([
            'data' => array_map(function($tb) {
                return [
                    'id' => (int)$tb['id'],
                    'name' => $tb['name'],
                    'tagline' => $tb['tagline'],
                    'location' => [
                        'city' => $tb['city'],
                        'country' => $tb['country'],
                        'timezone' => $tb['timezone']
                    ],
                    'member_count' => (int)$tb['member_count'],
                    'partnership_status' => $tb['partnership_status'],
                    'partnership_since' => $tb['partnership_since']
                ];
            }, $timebanks),
            'count' => count($timebanks)
        ]);
    }

    /**
     * Search federated members
     * GET /api/v1/federation/members
     *
     * Query params:
     * - q: Search query (name, skills)
     * - timebank_id: Filter by specific timebank
     * - skills: Comma-separated skill tags
     * - location: City/region filter
     * - page: Page number (default 1)
     * - per_page: Results per page (default 20, max 100)
     */
    public function members(): void
    {
        if (!FederationApiMiddleware::authenticate()) return;
        if (!FederationApiMiddleware::requirePermission('members:read')) return;

        $partnerTenantId = FederationApiMiddleware::getPartnerTenantId();

        // Parse query parameters
        $query = $_GET['q'] ?? '';
        $timebankId = isset($_GET['timebank_id']) ? (int)$_GET['timebank_id'] : null;
        $skills = !empty($_GET['skills']) ? explode(',', $_GET['skills']) : [];
        $location = $_GET['location'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));

        $db = Database::getInstance();

        // Build query for federated members
        $sql = "
            SELECT SQL_CALC_FOUND_ROWS
                u.id,
                u.username,
                u.first_name,
                u.last_name,
                u.avatar,
                u.city,
                u.region,
                u.country,
                u.bio,
                u.skills,
                u.created_at,
                fus.tenant_id,
                fus.privacy_level,
                fus.service_reach,
                t.name as timebank_name
            FROM users u
            JOIN federation_user_settings fus ON fus.user_id = u.id
            JOIN tenants t ON t.id = fus.tenant_id
            JOIN federation_partnerships fp ON (
                (fp.tenant_id = ? AND fp.partner_tenant_id = fus.tenant_id) OR
                (fp.partner_tenant_id = ? AND fp.tenant_id = fus.tenant_id)
            )
            WHERE fus.opted_in = 1
            AND fus.show_in_search = 1
            AND fp.status = 'active'
            AND fus.tenant_id != ?
        ";
        $params = [$partnerTenantId, $partnerTenantId, $partnerTenantId];

        // Apply filters
        if (!empty($query)) {
            $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.skills LIKE ?)";
            $searchTerm = "%{$query}%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        if ($timebankId) {
            $sql .= " AND fus.tenant_id = ?";
            $params[] = $timebankId;
        }

        if (!empty($skills)) {
            foreach ($skills as $skill) {
                $sql .= " AND u.skills LIKE ?";
                $params[] = "%{$skill}%";
            }
        }

        if (!empty($location)) {
            $sql .= " AND (u.city LIKE ? OR u.region LIKE ? OR u.country LIKE ?)";
            $locationTerm = "%{$location}%";
            $params = array_merge($params, [$locationTerm, $locationTerm, $locationTerm]);
        }

        $sql .= " ORDER BY u.first_name ASC, u.last_name ASC";
        $sql .= " LIMIT " . (($page - 1) * $perPage) . ", " . $perPage;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $members = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Get total count
        $totalStmt = $db->query("SELECT FOUND_ROWS()");
        $total = (int)$totalStmt->fetchColumn();

        // Format response
        $formattedMembers = array_map(function($m) {
            $showLocation = $m['privacy_level'] !== 'discovery';
            return [
                'id' => (int)$m['id'],
                'username' => $m['username'],
                'name' => trim($m['first_name'] . ' ' . $m['last_name']),
                'avatar' => $m['avatar'] ? "/uploads/avatars/{$m['avatar']}" : null,
                'bio' => $m['bio'],
                'skills' => $m['skills'] ? explode(',', $m['skills']) : [],
                'location' => $showLocation ? [
                    'city' => $m['city'],
                    'region' => $m['region'],
                    'country' => $m['country']
                ] : null,
                'timebank' => [
                    'id' => (int)$m['tenant_id'],
                    'name' => $m['timebank_name']
                ],
                'service_reach' => $m['service_reach'],
                'privacy_level' => $m['privacy_level'],
                'joined' => $m['created_at']
            ];
        }, $members);

        FederationApiMiddleware::sendPaginated($formattedMembers, $total, $page, $perPage);
    }

    /**
     * Get member profile
     * GET /api/v1/federation/members/{id}
     */
    public function member(int $id): void
    {
        if (!FederationApiMiddleware::authenticate()) return;
        if (!FederationApiMiddleware::requirePermission('members:read')) return;

        $partnerTenantId = FederationApiMiddleware::getPartnerTenantId();
        $db = Database::getInstance();

        // Get member with federation settings
        $stmt = $db->prepare("
            SELECT
                u.id,
                u.username,
                u.first_name,
                u.last_name,
                u.avatar,
                u.city,
                u.region,
                u.country,
                u.bio,
                u.skills,
                u.created_at,
                fus.tenant_id,
                fus.privacy_level,
                fus.service_reach,
                fus.accepts_messages,
                fus.accepts_transactions,
                t.name as timebank_name
            FROM users u
            JOIN federation_user_settings fus ON fus.user_id = u.id
            JOIN tenants t ON t.id = fus.tenant_id
            JOIN federation_partnerships fp ON (
                (fp.tenant_id = ? AND fp.partner_tenant_id = fus.tenant_id) OR
                (fp.partner_tenant_id = ? AND fp.tenant_id = fus.tenant_id)
            )
            WHERE u.id = ?
            AND fus.opted_in = 1
            AND fus.profile_visible = 1
            AND fp.status = 'active'
        ");
        $stmt->execute([$partnerTenantId, $partnerTenantId, $id]);
        $member = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$member) {
            FederationApiMiddleware::sendError(404, 'Member not found or not accessible', 'MEMBER_NOT_FOUND');
            return;
        }

        $showLocation = $member['privacy_level'] !== 'discovery';

        FederationApiMiddleware::sendSuccess([
            'data' => [
                'id' => (int)$member['id'],
                'username' => $member['username'],
                'name' => trim($member['first_name'] . ' ' . $member['last_name']),
                'avatar' => $member['avatar'] ? "/uploads/avatars/{$member['avatar']}" : null,
                'bio' => $member['bio'],
                'skills' => $member['skills'] ? explode(',', $member['skills']) : [],
                'location' => $showLocation ? [
                    'city' => $member['city'],
                    'region' => $member['region'],
                    'country' => $member['country']
                ] : null,
                'timebank' => [
                    'id' => (int)$member['tenant_id'],
                    'name' => $member['timebank_name']
                ],
                'service_reach' => $member['service_reach'],
                'privacy_level' => $member['privacy_level'],
                'accepts_messages' => (bool)$member['accepts_messages'],
                'accepts_transactions' => (bool)$member['accepts_transactions'],
                'joined' => $member['created_at']
            ]
        ]);
    }

    /**
     * Search federated listings
     * GET /api/v1/federation/listings
     *
     * Query params:
     * - q: Search query
     * - type: offer|request
     * - timebank_id: Filter by timebank
     * - category: Category filter
     * - page, per_page: Pagination
     */
    public function listings(): void
    {
        if (!FederationApiMiddleware::authenticate()) return;
        if (!FederationApiMiddleware::requirePermission('listings:read')) return;

        $partnerTenantId = FederationApiMiddleware::getPartnerTenantId();

        $query = $_GET['q'] ?? '';
        $type = $_GET['type'] ?? '';
        $timebankId = isset($_GET['timebank_id']) ? (int)$_GET['timebank_id'] : null;
        $category = $_GET['category'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));

        $db = Database::getInstance();

        $sql = "
            SELECT SQL_CALC_FOUND_ROWS
                l.id,
                l.title,
                l.description,
                l.type,
                l.category,
                l.rate,
                l.created_at,
                l.user_id,
                u.first_name,
                u.last_name,
                u.avatar,
                l.tenant_id,
                t.name as timebank_name
            FROM listings l
            JOIN users u ON u.id = l.user_id
            JOIN tenants t ON t.id = l.tenant_id
            JOIN federation_user_settings fus ON fus.user_id = l.user_id AND fus.tenant_id = l.tenant_id
            JOIN federation_partnerships fp ON (
                (fp.tenant_id = ? AND fp.partner_tenant_id = l.tenant_id) OR
                (fp.partner_tenant_id = ? AND fp.tenant_id = l.tenant_id)
            )
            WHERE l.status = 'active'
            AND fus.opted_in = 1
            AND fp.status = 'active'
            AND l.tenant_id != ?
        ";
        $params = [$partnerTenantId, $partnerTenantId, $partnerTenantId];

        if (!empty($query)) {
            $sql .= " AND (l.title LIKE ? OR l.description LIKE ?)";
            $searchTerm = "%{$query}%";
            $params = array_merge($params, [$searchTerm, $searchTerm]);
        }

        if (!empty($type) && in_array($type, ['offer', 'request'])) {
            $sql .= " AND l.type = ?";
            $params[] = $type;
        }

        if ($timebankId) {
            $sql .= " AND l.tenant_id = ?";
            $params[] = $timebankId;
        }

        if (!empty($category)) {
            $sql .= " AND l.category = ?";
            $params[] = $category;
        }

        $sql .= " ORDER BY l.created_at DESC";
        $sql .= " LIMIT " . (($page - 1) * $perPage) . ", " . $perPage;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $listings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $totalStmt = $db->query("SELECT FOUND_ROWS()");
        $total = (int)$totalStmt->fetchColumn();

        $formattedListings = array_map(function($l) {
            return [
                'id' => (int)$l['id'],
                'title' => $l['title'],
                'description' => $l['description'],
                'type' => $l['type'],
                'category' => $l['category'],
                'rate' => $l['rate'],
                'owner' => [
                    'id' => (int)$l['user_id'],
                    'name' => trim($l['first_name'] . ' ' . $l['last_name']),
                    'avatar' => $l['avatar'] ? "/uploads/avatars/{$l['avatar']}" : null
                ],
                'timebank' => [
                    'id' => (int)$l['tenant_id'],
                    'name' => $l['timebank_name']
                ],
                'created_at' => $l['created_at']
            ];
        }, $listings);

        FederationApiMiddleware::sendPaginated($formattedListings, $total, $page, $perPage);
    }

    /**
     * Get listing details
     * GET /api/v1/federation/listings/{id}
     */
    public function listing(int $id): void
    {
        if (!FederationApiMiddleware::authenticate()) return;
        if (!FederationApiMiddleware::requirePermission('listings:read')) return;

        $partnerTenantId = FederationApiMiddleware::getPartnerTenantId();
        $db = Database::getInstance();

        $stmt = $db->prepare("
            SELECT
                l.*,
                u.first_name,
                u.last_name,
                u.avatar,
                u.city,
                t.name as timebank_name
            FROM listings l
            JOIN users u ON u.id = l.user_id
            JOIN tenants t ON t.id = l.tenant_id
            JOIN federation_user_settings fus ON fus.user_id = l.user_id AND fus.tenant_id = l.tenant_id
            JOIN federation_partnerships fp ON (
                (fp.tenant_id = ? AND fp.partner_tenant_id = l.tenant_id) OR
                (fp.partner_tenant_id = ? AND fp.tenant_id = l.tenant_id)
            )
            WHERE l.id = ?
            AND l.status = 'active'
            AND fus.opted_in = 1
            AND fp.status = 'active'
        ");
        $stmt->execute([$partnerTenantId, $partnerTenantId, $id]);
        $listing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$listing) {
            FederationApiMiddleware::sendError(404, 'Listing not found or not accessible', 'LISTING_NOT_FOUND');
            return;
        }

        FederationApiMiddleware::sendSuccess([
            'data' => [
                'id' => (int)$listing['id'],
                'title' => $listing['title'],
                'description' => $listing['description'],
                'type' => $listing['type'],
                'category' => $listing['category'],
                'rate' => $listing['rate'],
                'owner' => [
                    'id' => (int)$listing['user_id'],
                    'name' => trim($listing['first_name'] . ' ' . $listing['last_name']),
                    'avatar' => $listing['avatar'] ? "/uploads/avatars/{$listing['avatar']}" : null,
                    'city' => $listing['city']
                ],
                'timebank' => [
                    'id' => (int)$listing['tenant_id'],
                    'name' => $listing['timebank_name']
                ],
                'created_at' => $listing['created_at'],
                'updated_at' => $listing['updated_at']
            ]
        ]);
    }

    /**
     * Send federated message
     * POST /api/v1/federation/messages
     *
     * Body:
     * - recipient_id: Target user ID
     * - subject: Message subject
     * - body: Message content
     * - sender_id: Sender user ID (from partner timebank)
     */
    public function sendMessage(): void
    {
        if (!FederationApiMiddleware::authenticate()) return;
        if (!FederationApiMiddleware::requirePermission('messages:write')) return;

        $partnerTenantId = FederationApiMiddleware::getPartnerTenantId();
        $input = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        $required = ['recipient_id', 'subject', 'body', 'sender_id'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                FederationApiMiddleware::sendError(400, "Missing required field: {$field}", 'VALIDATION_ERROR');
                return;
            }
        }

        $db = Database::getInstance();

        // Verify recipient exists and accepts messages
        $stmt = $db->prepare("
            SELECT u.id, u.first_name, fus.tenant_id, fus.accepts_messages
            FROM users u
            JOIN federation_user_settings fus ON fus.user_id = u.id
            JOIN federation_partnerships fp ON (
                (fp.tenant_id = ? AND fp.partner_tenant_id = fus.tenant_id) OR
                (fp.partner_tenant_id = ? AND fp.tenant_id = fus.tenant_id)
            )
            WHERE u.id = ?
            AND fus.opted_in = 1
            AND fp.status = 'active'
        ");
        $stmt->execute([$partnerTenantId, $partnerTenantId, $input['recipient_id']]);
        $recipient = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$recipient) {
            FederationApiMiddleware::sendError(404, 'Recipient not found or not accessible', 'RECIPIENT_NOT_FOUND');
            return;
        }

        if (!$recipient['accepts_messages']) {
            FederationApiMiddleware::sendError(403, 'Recipient does not accept federated messages', 'MESSAGES_DISABLED');
            return;
        }

        // Create the message
        $stmt = $db->prepare("
            INSERT INTO messages
            (sender_id, recipient_id, subject, body, is_federated, sender_tenant_id, created_at)
            VALUES (?, ?, ?, ?, 1, ?, NOW())
        ");
        $stmt->execute([
            $input['sender_id'],
            $input['recipient_id'],
            $input['subject'],
            $input['body'],
            $partnerTenantId
        ]);
        $messageId = $db->lastInsertId();

        // Log the action
        FederationAuditService::log(
            $partnerTenantId,
            $recipient['tenant_id'],
            'api_message_sent',
            "API message sent to user {$input['recipient_id']}",
            ['message_id' => $messageId]
        );

        FederationApiMiddleware::sendSuccess([
            'message_id' => (int)$messageId,
            'status' => 'sent'
        ], 201);
    }

    /**
     * Initiate time credit transfer
     * POST /api/v1/federation/transactions
     *
     * Body:
     * - recipient_id: Target user ID
     * - amount: Hours to transfer
     * - description: Transaction description
     * - sender_id: Sender user ID
     */
    public function createTransaction(): void
    {
        if (!FederationApiMiddleware::authenticate()) return;
        if (!FederationApiMiddleware::requirePermission('transactions:write')) return;

        $partnerTenantId = FederationApiMiddleware::getPartnerTenantId();
        $input = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        $required = ['recipient_id', 'amount', 'description', 'sender_id'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || $input[$field] === '') {
                FederationApiMiddleware::sendError(400, "Missing required field: {$field}", 'VALIDATION_ERROR');
                return;
            }
        }

        $amount = (float)$input['amount'];
        if ($amount <= 0 || $amount > 100) {
            FederationApiMiddleware::sendError(400, 'Amount must be between 0 and 100 hours', 'INVALID_AMOUNT');
            return;
        }

        $db = Database::getInstance();

        // Verify recipient accepts transactions
        $stmt = $db->prepare("
            SELECT u.id, u.first_name, fus.tenant_id, fus.accepts_transactions
            FROM users u
            JOIN federation_user_settings fus ON fus.user_id = u.id
            JOIN federation_partnerships fp ON (
                (fp.tenant_id = ? AND fp.partner_tenant_id = fus.tenant_id) OR
                (fp.partner_tenant_id = ? AND fp.tenant_id = fus.tenant_id)
            )
            WHERE u.id = ?
            AND fus.opted_in = 1
            AND fus.privacy_level = 'economic'
            AND fp.status = 'active'
        ");
        $stmt->execute([$partnerTenantId, $partnerTenantId, $input['recipient_id']]);
        $recipient = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$recipient) {
            FederationApiMiddleware::sendError(404, 'Recipient not found or not accessible', 'RECIPIENT_NOT_FOUND');
            return;
        }

        if (!$recipient['accepts_transactions']) {
            FederationApiMiddleware::sendError(403, 'Recipient does not accept federated transactions', 'TRANSACTIONS_DISABLED');
            return;
        }

        // Create pending transaction (requires confirmation flow in production)
        $stmt = $db->prepare("
            INSERT INTO transactions
            (sender_id, receiver_id, amount, description, status, is_federated, sender_tenant_id, receiver_tenant_id, created_at)
            VALUES (?, ?, ?, ?, 'pending', 1, ?, ?, NOW())
        ");
        $stmt->execute([
            $input['sender_id'],
            $input['recipient_id'],
            $amount,
            $input['description'],
            $partnerTenantId,
            $recipient['tenant_id']
        ]);
        $transactionId = $db->lastInsertId();

        // Log the action
        FederationAuditService::log(
            $partnerTenantId,
            $recipient['tenant_id'],
            'api_transaction_initiated',
            "API transaction initiated: {$amount} hours to user {$input['recipient_id']}",
            ['transaction_id' => $transactionId, 'amount' => $amount]
        );

        FederationApiMiddleware::sendSuccess([
            'transaction_id' => (int)$transactionId,
            'status' => 'pending',
            'amount' => $amount,
            'note' => 'Transaction requires recipient confirmation'
        ], 201);
    }

    /**
     * OAuth 2.0 Token Endpoint
     * POST /api/v1/federation/oauth/token
     *
     * Supports client_credentials grant type for machine-to-machine auth.
     * Partners exchange their platform_id + signing_secret for a JWT token.
     *
     * Request (form-urlencoded or JSON):
     * - grant_type: "client_credentials"
     * - client_id: Platform ID
     * - client_secret: Signing secret
     * - scope: Space-separated list of requested scopes (optional)
     */
    public function oauthToken(): void
    {
        // Handle CORS preflight and set headers for token endpoint
        CorsHelper::handlePreflight([], ['POST', 'OPTIONS'], ['Content-Type', 'Authorization']);
        CorsHelper::setHeaders([], ['POST', 'OPTIONS'], ['Content-Type', 'Authorization']);

        // Handle token request
        $result = FederationJwtService::handleTokenRequest();

        if (isset($result['error'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        }

        http_response_code(200);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        header('Pragma: no-cache');
        echo json_encode($result);
        exit;
    }

    /**
     * Test webhook signature verification
     * POST /api/v1/federation/webhooks/test
     *
     * Partners can use this endpoint to verify their webhook signing is correct.
     * Send a signed request and receive confirmation of signature validity.
     *
     * Required headers:
     * - X-Federation-Platform-ID: Your platform ID
     * - X-Federation-Timestamp: ISO 8601 timestamp
     * - X-Federation-Signature: HMAC-SHA256 signature
     */
    public function testWebhook(): void
    {
        // Check for required headers
        $platformId = $_SERVER['HTTP_X_FEDERATION_PLATFORM_ID'] ?? '';
        $timestamp = $_SERVER['HTTP_X_FEDERATION_TIMESTAMP'] ?? '';
        $signature = $_SERVER['HTTP_X_FEDERATION_SIGNATURE'] ?? '';

        if (empty($platformId) || empty($timestamp) || empty($signature)) {
            FederationApiMiddleware::sendError(400, 'Missing required headers', 'MISSING_HEADERS');
            return;
        }

        // Validate timestamp
        $requestTime = strtotime($timestamp);
        if ($requestTime === false) {
            if (is_numeric($timestamp)) {
                $requestTime = (int)$timestamp;
            } else {
                FederationApiMiddleware::sendError(400, 'Invalid timestamp format', 'INVALID_TIMESTAMP');
                return;
            }
        }

        $timeDiff = abs(time() - $requestTime);
        if ($timeDiff > 300) {
            FederationApiMiddleware::sendError(401, 'Timestamp expired (max 5 minutes)', 'TIMESTAMP_EXPIRED');
            return;
        }

        // Look up platform
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT id, name, signing_secret, platform_id
            FROM federation_api_keys
            WHERE platform_id = ?
            AND status = 'active'
        ");
        $stmt->execute([$platformId]);
        $partner = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$partner) {
            FederationApiMiddleware::sendError(404, 'Platform not found', 'PLATFORM_NOT_FOUND');
            return;
        }

        if (empty($partner['signing_secret'])) {
            FederationApiMiddleware::sendError(400, 'HMAC signing not configured for this platform', 'SIGNING_NOT_CONFIGURED');
            return;
        }

        // Verify signature
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_SERVER['REQUEST_URI'];
        $body = file_get_contents('php://input') ?: '';

        $stringToSign = implode("\n", [$method, $path, $timestamp, $body]);
        $expectedSignature = hash_hmac('sha256', $stringToSign, $partner['signing_secret']);
        $signatureValid = hash_equals($expectedSignature, $signature);

        if (!$signatureValid) {
            FederationApiMiddleware::sendSuccess([
                'valid' => false,
                'message' => 'Signature verification failed',
                'debug' => [
                    'platform_id' => $platformId,
                    'timestamp_age_seconds' => $timeDiff,
                    'method' => $method,
                    'path' => $path,
                    'body_length' => strlen($body),
                    'expected_signature_preview' => substr($expectedSignature, 0, 16) . '...',
                    'received_signature_preview' => substr($signature, 0, 16) . '...',
                    'hint' => 'Ensure you are signing: METHOD\\nPATH\\nTIMESTAMP\\nBODY'
                ]
            ]);
            return;
        }

        FederationApiMiddleware::sendSuccess([
            'valid' => true,
            'message' => 'Signature verified successfully',
            'platform' => [
                'id' => $partner['platform_id'],
                'name' => $partner['name']
            ],
            'timestamp_age_seconds' => $timeDiff
        ]);
    }
}
