<?php

class GroupSeeder
{
    private $pdo;
    private $tenantId;
    private $userIds;

    private $groupNames = [
        'Community Gardeners', 'Tech Skills Exchange', 'Local Food Co-op',
        'Neighborhood Watch', 'Arts & Crafts Circle', 'Youth Mentorship Program',
        'Senior Support Network', 'Environmental Action Group', 'Book Club',
        'Language Exchange', 'Fitness & Wellness', 'DIY Home Repair',
        'Pet Owners Association', 'Parent Support Group', 'Entrepreneurs Network',
        'Music Makers', 'Photography Enthusiasts', 'Cooking Classes',
        'Mental Health Support', 'Career Development'
    ];

    private $descriptions = [
        'A welcoming community for people interested in %s. Join us to share knowledge and build connections!',
        'Connect with like-minded individuals who love %s. Everyone welcome!',
        'Dedicated to promoting %s in our community through collaboration and mutual support.',
        'Building a stronger community through %s. Come share your skills and learn from others!',
        'Passionate about %s? Join our growing community and make new friends!',
    ];

    public function __construct($pdo, $tenantId, $userIds)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        $this->userIds = $userIds;
    }

    public function seed($count = 10)
    {
        $groupIds = [];

        for ($i = 0; $i < $count && $i < count($this->groupNames); $i++) {
            $groupName = $this->groupNames[$i];
            $creatorId = $this->userIds[array_rand($this->userIds)];

            $groupId = $this->createGroup([
                'name' => $groupName,
                'slug' => $this->slugify($groupName),
                'description' => sprintf($this->descriptions[array_rand($this->descriptions)], strtolower($groupName)),
                'creator_id' => $creatorId,
                'privacy' => rand(0, 10) > 7 ? 'private' : 'public', // 30% private
            ]);

            if ($groupId) {
                $groupIds[] = $groupId;

                // Add creator as admin member
                $this->addMember($groupId, $creatorId, 'admin');

                // Add random members
                $memberCount = rand(3, 15);
                $potentialMembers = array_diff($this->userIds, [$creatorId]);
                shuffle($potentialMembers);

                for ($j = 0; $j < $memberCount && $j < count($potentialMembers); $j++) {
                    $this->addMember($groupId, $potentialMembers[$j], 'member');
                }
            }
        }

        return $groupIds;
    }

    private function createGroup($data)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO groups (
                    tenant_id, name, slug, description, creator_id, privacy,
                    created_at, cached_member_count
                ) VALUES (
                    :tenant_id, :name, :slug, :description, :creator_id, :privacy,
                    :created_at, 1
                )
            ");

            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'],
                'creator_id' => $data['creator_id'],
                'privacy' => $data['privacy'],
                'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(30, 365) . ' days')),
            ]);

            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            echo "Warning: Could not create group {$data['name']}: " . $e->getMessage() . "\n";
            return null;
        }
    }

    private function addMember($groupId, $userId, $role = 'member')
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO group_members (
                    tenant_id, group_id, user_id, role, joined_at
                ) VALUES (
                    :tenant_id, :group_id, :user_id, :role, :joined_at
                )
            ");

            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'group_id' => $groupId,
                'user_id' => $userId,
                'role' => $role,
                'joined_at' => date('Y-m-d H:i:s'),
            ]);

            // Update cached member count
            $updateStmt = $this->pdo->prepare("UPDATE groups SET cached_member_count = cached_member_count + 1 WHERE id = ?");
            $updateStmt->execute([$groupId]);

        } catch (Exception $e) {
            // Silently skip duplicates
        }
    }

    private function slugify($text)
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');
        return $text;
    }
}
