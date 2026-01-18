<?php

class BadgeSeeder
{
    private $pdo;
    private $tenantId;
    private $userIds;

    private $badges = [
        ['name' => 'Welcome Badge', 'description' => 'Joined the community', 'icon' => '👋'],
        ['name' => 'First Post', 'description' => 'Made your first post', 'icon' => '✍️'],
        ['name' => 'Community Helper', 'description' => 'Helped 10 community members', 'icon' => '🤝'],
        ['name' => 'Active Contributor', 'description' => 'Posted 20 times', 'icon' => '⭐'],
        ['name' => 'Event Organizer', 'description' => 'Organized your first event', 'icon' => '📅'],
        ['name' => 'Volunteer Champion', 'description' => 'Logged 50 volunteer hours', 'icon' => '🏆'],
        ['name' => 'Skill Sharer', 'description' => 'Created 5 skill offers', 'icon' => '🎯'],
        ['name' => 'Conversation Starter', 'description' => 'Started 10 discussions', 'icon' => '💬'],
        ['name' => 'Trusted Member', 'description' => 'Received 5-star reviews', 'icon' => '⭐⭐⭐⭐⭐'],
        ['name' => 'Group Leader', 'description' => 'Founded a community group', 'icon' => '👥'],
    ];

    public function __construct($pdo, $tenantId, $userIds)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        $this->userIds = $userIds;
    }

    public function seed()
    {
        // Award random badges to random users
        foreach ($this->userIds as $userId) {
            // Each user gets 1-4 badges
            $badgeCount = rand(1, 4);

            $shuffledBadges = $this->badges;
            shuffle($shuffledBadges);

            for ($i = 0; $i < $badgeCount; $i++) {
                $badge = $shuffledBadges[$i];

                $this->awardBadge([
                    'user_id' => $userId,
                    'name' => $badge['name'],
                    'description' => $badge['description'],
                    'icon' => $badge['icon'],
                ]);
            }
        }
    }

    private function awardBadge($data)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO user_badges (
                    tenant_id, user_id, name, description, icon, earned_at
                ) VALUES (
                    :tenant_id, :user_id, :name, :description, :icon, :earned_at
                )
            ");

            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'user_id' => $data['user_id'],
                'name' => $data['name'],
                'description' => $data['description'],
                'icon' => $data['icon'],
                'earned_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 180) . ' days')),
            ]);
        } catch (Exception $e) {
            // Silently skip duplicates
        }
    }
}
