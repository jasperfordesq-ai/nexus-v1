<?php

class EventSeeder
{
    private $pdo;
    private $tenantId;
    private $userIds;
    private $groupIds;

    private $eventNames = [
        'Community Cleanup Day',
        'Skills Workshop: Home Repair Basics',
        'Weekly Coffee & Chat',
        'Neighborhood Potluck Dinner',
        'Volunteer Orientation Session',
        'Garden Tour & Plant Swap',
        'Tech Help Drop-in Hours',
        'Community Town Hall Meeting',
        'Kids Art Class',
        'Senior Social Hour',
        'Book Club Discussion',
        'Fitness in the Park',
        'Language Exchange Meetup',
        'Sustainable Living Workshop',
        'Local History Walk',
    ];

    public function __construct($pdo, $tenantId, $userIds, $groupIds)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        $this->userIds = $userIds;
        $this->groupIds = $groupIds;
    }

    public function seed($count = 20)
    {
        $eventIds = [];

        for ($i = 0; $i < $count && $i < count($this->eventNames); $i++) {
            $organizerId = $this->userIds[array_rand($this->userIds)];
            $eventName = $this->eventNames[$i];

            // Mix of past and future events
            $daysOffset = rand(-30, 60);
            $eventDate = date('Y-m-d', strtotime("+{$daysOffset} days"));
            $startTime = sprintf('%02d:00:00', rand(9, 18));

            $eventId = $this->createEvent([
                'title' => $eventName,
                'description' => "Join us for {$eventName}! Everyone welcome.",
                'organizer_id' => $organizerId,
                'group_id' => !empty($this->groupIds) ? $this->groupIds[array_rand($this->groupIds)] : null,
                'event_date' => $eventDate,
                'start_time' => $startTime,
                'location' => $this->randomLocation(),
            ]);

            if ($eventId) {
                $eventIds[] = $eventId;

                // Add random RSVPs
                $rsvpCount = rand(5, 30);
                $potentialAttendees = array_diff($this->userIds, [$organizerId]);
                shuffle($potentialAttendees);

                for ($j = 0; $j < $rsvpCount && $j < count($potentialAttendees); $j++) {
                    $this->addRSVP($eventId, $potentialAttendees[$j]);
                }
            }
        }

        return $eventIds;
    }

    private function createEvent($data)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO events (
                    tenant_id, title, description, organizer_id, group_id,
                    event_date, start_time, location, created_at
                ) VALUES (
                    :tenant_id, :title, :description, :organizer_id, :group_id,
                    :event_date, :start_time, :location, :created_at
                )
            ");

            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'title' => $data['title'],
                'description' => $data['description'],
                'organizer_id' => $data['organizer_id'],
                'group_id' => $data['group_id'],
                'event_date' => $data['event_date'],
                'start_time' => $data['start_time'],
                'location' => $data['location'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            echo "Warning: Could not create event {$data['title']}: " . $e->getMessage() . "\n";
            return null;
        }
    }

    private function addRSVP($eventId, $userId)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO event_rsvp (
                    tenant_id, event_id, user_id, status, created_at
                ) VALUES (
                    :tenant_id, :event_id, :user_id, :status, :created_at
                )
            ");

            $statuses = ['going', 'going', 'going', 'maybe', 'not_going']; // 60% going
            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'event_id' => $eventId,
                'user_id' => $userId,
                'status' => $statuses[array_rand($statuses)],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            // Silently skip duplicates
        }
    }

    private function randomLocation()
    {
        $locations = [
            'Community Center',
            'Central Park',
            'Public Library',
            'Town Hall',
            'Neighborhood Cafe',
            'Recreation Center',
            'Local School',
            'Community Garden',
        ];

        return $locations[array_rand($locations)];
    }
}
