<?php

namespace Nexus\Controllers\Api;

use Nexus\Models\Event;
use Nexus\Models\EventRsvp;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiAuth;

class EventApiController
{
    use ApiAuth;

    private function jsonResponse($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    private function getUserId()
    {
        return $this->requireAuth();
    }

    public function index()
    {
        $this->getUserId(); // Ensure auth
        $events = Event::upcoming(TenantContext::getId());

        // Enrich
        foreach ($events as &$ev) {
            $ev['attendee_count'] = EventRsvp::getCount($ev['id'], 'going');
            $ev['my_status'] = EventRsvp::getUserStatus($ev['id'], $_SESSION['user_id']);
        }

        $this->jsonResponse(['data' => $events]);
    }

    public function rsvp()
    {
        $userId = $this->getUserId();
        $input = json_decode(file_get_contents('php://input'), true);

        $eventId = $input['event_id'] ?? null;
        $status = $input['status'] ?? null;

        if (!$eventId || !$status) {
            $this->jsonResponse(['error' => 'Missing fields'], 400);
        }

        try {
            EventRsvp::rsvp($eventId, $userId, $status);
            $this->jsonResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
