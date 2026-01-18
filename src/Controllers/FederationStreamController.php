<?php

namespace Nexus\Controllers;

use Nexus\Core\Auth;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationUserService;
use Nexus\Services\FederationRealtimeService;
use Nexus\Services\PusherService;

/**
 * FederationStreamController
 *
 * Handles Server-Sent Events (SSE) for real-time federation notifications.
 * This is the fallback when Pusher is not configured.
 */
class FederationStreamController
{
    /**
     * SSE stream endpoint for federation events
     * GET /federation/stream
     */
    public function stream(): void
    {
        // Require authentication
        $user = Auth::user();
        if (!$user) {
            http_response_code(401);
            echo "data: " . json_encode(['error' => 'Unauthorized']) . "\n\n";
            exit;
        }

        $userId = $user['id'];
        $tenantId = TenantContext::getId();

        // Check if user is opted into federation
        if (!FederationUserService::isUserOptedIn($userId, $tenantId)) {
            http_response_code(403);
            echo "data: " . json_encode(['error' => 'Not opted into federation']) . "\n\n";
            exit;
        }

        // Set SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable nginx buffering
        header('Access-Control-Allow-Origin: *');

        // Disable output buffering
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Send initial connection event
        $this->sendEvent('connected', [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'method' => 'sse',
            'timestamp' => date('c'),
        ]);

        // Get last event ID from client (for reconnection)
        $lastEventId = $_SERVER['HTTP_LAST_EVENT_ID'] ?? $_GET['lastEventId'] ?? null;

        // Stream loop - keep connection open
        $startTime = time();
        $maxDuration = 300; // 5 minutes max connection time
        $heartbeatInterval = 30; // Send heartbeat every 30 seconds
        $lastHeartbeat = time();

        while (true) {
            // Check connection duration
            if ((time() - $startTime) > $maxDuration) {
                $this->sendEvent('reconnect', ['reason' => 'max_duration']);
                break;
            }

            // Check if client disconnected
            if (connection_aborted()) {
                break;
            }

            // Get pending events for this user
            $events = FederationRealtimeService::getPendingEvents($userId, $tenantId, $lastEventId);

            if (!empty($events)) {
                $eventIds = [];
                foreach ($events as $event) {
                    $eventIds[] = $event['id'];
                    $lastEventId = $event['id'];

                    $data = json_decode($event['event_data'], true) ?: [];
                    $this->sendEvent($event['event_type'], $data, $event['id']);
                }

                // Mark events as delivered
                FederationRealtimeService::markEventsDelivered($eventIds);
            }

            // Send heartbeat to keep connection alive
            if ((time() - $lastHeartbeat) >= $heartbeatInterval) {
                $this->sendEvent('heartbeat', ['timestamp' => date('c')]);
                $lastHeartbeat = time();
            }

            // Flush output
            if (ob_get_level()) {
                ob_flush();
            }
            flush();

            // Sleep briefly to prevent CPU spin
            usleep(500000); // 500ms
        }
    }

    /**
     * Get connection info for client
     * GET /federation/stream/info
     */
    public function info(): void
    {
        $user = Auth::user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $tenantId = TenantContext::getId();
        $method = FederationRealtimeService::getConnectionMethod();

        $response = [
            'method' => $method,
            'available' => true,
        ];

        if ($method === 'pusher') {
            $config = PusherService::getConfig();
            $response['pusher'] = [
                'key' => $config['key'],
                'cluster' => $config['cluster'],
                'channel' => FederationRealtimeService::getUserFederationChannel($user['id'], $tenantId),
            ];
        } else {
            $response['sse'] = [
                'endpoint' => '/federation/stream',
            ];
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    /**
     * Send an SSE event
     */
    private function sendEvent(string $eventType, array $data, ?int $id = null): void
    {
        if ($id !== null) {
            echo "id: {$id}\n";
        }
        echo "event: {$eventType}\n";
        echo "data: " . json_encode($data) . "\n\n";
    }

    /**
     * Pusher channel auth endpoint
     * POST /federation/pusher/auth
     */
    public function pusherAuth(): void
    {
        $user = Auth::user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $channelName = $_POST['channel_name'] ?? '';
        $socketId = $_POST['socket_id'] ?? '';

        if (empty($channelName) || empty($socketId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing channel_name or socket_id']);
            return;
        }

        $tenantId = TenantContext::getId();
        $auth = FederationRealtimeService::authFederationChannel(
            $channelName,
            $socketId,
            $user['id'],
            $tenantId
        );

        if ($auth === null) {
            http_response_code(403);
            echo json_encode(['error' => 'Channel authorization failed']);
            return;
        }

        header('Content-Type: application/json');
        echo $auth;
    }

    /**
     * Test endpoint - send a test notification
     * POST /federation/stream/test (development only)
     */
    public function test(): void
    {
        $user = Auth::user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $tenantId = TenantContext::getId();

        // Queue a test event
        FederationRealtimeService::broadcastActivityEvent(
            $user['id'],
            $tenantId,
            'test',
            [
                'message' => 'This is a test notification',
                'from' => 'System',
            ]
        );

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Test notification queued',
            'method' => FederationRealtimeService::getConnectionMethod(),
        ]);
    }
}
