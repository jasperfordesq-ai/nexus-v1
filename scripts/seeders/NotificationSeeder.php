<?php

class NotificationSeeder
{
    private $pdo;
    private $tenantId;
    private $userIds;

    private $notificationTypes = [
        ['type' => 'post_like', 'message' => 'liked your post'],
        ['type' => 'comment', 'message' => 'commented on your post'],
        ['type' => 'group_invite', 'message' => 'invited you to join a group'],
        ['type' => 'event_invite', 'message' => 'invited you to an event'],
        ['type' => 'badge_earned', 'message' => 'You earned a new badge!'],
        ['type' => 'transaction', 'message' => 'sent you time credits'],
        ['type' => 'review', 'message' => 'left you a review'],
        ['type' => 'mention', 'message' => 'mentioned you in a post'],
    ];

    public function __construct($pdo, $tenantId, $userIds)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        $this->userIds = $userIds;
    }

    public function seed($count = 50)
    {
        for ($i = 0; $i < $count; $i++) {
            $userId = $this->userIds[array_rand($this->userIds)];
            $actorId = $this->userIds[array_rand($this->userIds)];

            // Ensure different users (except for badge_earned which has no actor)
            $notification = $this->notificationTypes[array_rand($this->notificationTypes)];

            if ($notification['type'] !== 'badge_earned') {
                while ($userId === $actorId) {
                    $actorId = $this->userIds[array_rand($this->userIds)];
                }
            } else {
                $actorId = null;
            }

            $this->createNotification([
                'user_id' => $userId,
                'actor_id' => $actorId,
                'type' => $notification['type'],
                'message' => $notification['message'],
                'is_read' => rand(0, 10) > 3 ? 1 : 0, // 70% read
            ]);
        }
    }

    private function createNotification($data)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (
                    tenant_id, user_id, actor_id, type, message, is_read, created_at
                ) VALUES (
                    :tenant_id, :user_id, :actor_id, :type, :message, :is_read, :created_at
                )
            ");

            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'user_id' => $data['user_id'],
                'actor_id' => $data['actor_id'],
                'type' => $data['type'],
                'message' => $data['message'],
                'is_read' => $data['is_read'],
                'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days')),
            ]);
        } catch (Exception $e) {
            echo "Warning: Could not create notification: " . $e->getMessage() . "\n";
        }
    }
}
