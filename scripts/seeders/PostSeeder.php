<?php

class PostSeeder
{
    private $pdo;
    private $tenantId;
    private $userIds;
    private $groupIds;

    private $postTemplates = [
        "Just finished volunteering at the community garden! 🌱 Great to see everyone working together.",
        "Looking for someone to teach me basic home repair skills. Can offer web design in exchange!",
        "Huge thank you to everyone who helped with the neighborhood cleanup yesterday! 💪",
        "Does anyone have experience with organic gardening? Would love to learn more!",
        "Sharing some fresh vegetables from my garden. First come, first served! 🥕🥬",
        "New to the community and excited to get involved. What groups should I join?",
        "Free piano lessons available on weekends. Message me if interested! 🎹",
        "Had an amazing experience at today's skill-share workshop. Thank you to the organizers!",
        "Looking to start a weekly study group. Anyone interested in learning Spanish together?",
        "Can someone help me move some furniture this weekend? Happy to return the favor!",
        "Our community potluck was a huge success! Thanks to everyone who brought delicious food! 🍲",
        "Reminder: Community meeting tomorrow at 7pm. All welcome to attend and share ideas!",
        "Just joined and loving the positive energy here. Looking forward to connecting with you all!",
        "Does anyone need help with their taxes? I'm a CPA and happy to volunteer my time.",
        "Found a lost dog near the park. Please share if you recognize this pup! 🐕",
    ];

    public function __construct($pdo, $tenantId, $userIds, $groupIds)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        $this->userIds = $userIds;
        $this->groupIds = $groupIds;
    }

    public function seed($count = 100)
    {
        $postIds = [];

        for ($i = 0; $i < $count; $i++) {
            $userId = $this->userIds[array_rand($this->userIds)];
            $content = $this->postTemplates[array_rand($this->postTemplates)];

            // 30% chance of group post
            $groupId = (rand(0, 10) < 3 && !empty($this->groupIds))
                ? $this->groupIds[array_rand($this->groupIds)]
                : null;

            $postId = $this->createPost($userId, $content, $groupId);

            if ($postId) {
                $postIds[] = $postId;

                // Add random likes (0-20 likes per post)
                $likeCount = rand(0, 20);
                $potentialLikers = array_diff($this->userIds, [$userId]);
                shuffle($potentialLikers);

                for ($j = 0; $j < $likeCount && $j < count($potentialLikers); $j++) {
                    $this->addLike($postId, $potentialLikers[$j]);
                }
            }
        }

        return $postIds;
    }

    private function createPost($userId, $content, $groupId = null)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO feed_posts (
                    tenant_id, user_id, group_id, content, created_at
                ) VALUES (
                    :tenant_id, :user_id, :group_id, :content, :created_at
                )
            ");

            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'user_id' => $userId,
                'group_id' => $groupId,
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 90) . ' days')),
            ]);

            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            echo "Warning: Could not create post: " . $e->getMessage() . "\n";
            return null;
        }
    }

    private function addLike($postId, $userId)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO post_likes (
                    tenant_id, post_id, user_id, created_at
                ) VALUES (
                    :tenant_id, :post_id, :user_id, :created_at
                )
            ");

            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'post_id' => $postId,
                'user_id' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            // Silently skip duplicates
        }
    }
}
