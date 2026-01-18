<?php

class ListingSeeder
{
    private $pdo;
    private $tenantId;
    private $userIds;

    private $offers = [
        ['title' => 'Web Design Services', 'description' => 'Can help design or redesign your website', 'category' => 'Technology'],
        ['title' => 'Gardening Help', 'description' => 'Experienced gardener offering planting and maintenance', 'category' => 'Home & Garden'],
        ['title' => 'Math Tutoring', 'description' => 'Tutoring for high school math students', 'category' => 'Education'],
        ['title' => 'Dog Walking', 'description' => 'Love dogs! Happy to walk yours during the day', 'category' => 'Pets'],
        ['title' => 'Resume Writing', 'description' => 'Professional resume and cover letter help', 'category' => 'Career'],
        ['title' => 'Home Cooked Meals', 'description' => 'Will cook healthy meals for seniors or busy families', 'category' => 'Food'],
        ['title' => 'Guitar Lessons', 'description' => 'Beginner to intermediate guitar instruction', 'category' => 'Music'],
        ['title' => 'House Cleaning', 'description' => 'Thorough and reliable cleaning services', 'category' => 'Home & Garden'],
        ['title' => 'Photography', 'description' => 'Event and portrait photography', 'category' => 'Arts'],
        ['title' => 'Car Maintenance', 'description' => 'Basic car repairs and oil changes', 'category' => 'Transportation'],
    ];

    private $requests = [
        ['title' => 'Need Help Moving', 'description' => 'Looking for strong helpers to move furniture', 'category' => 'Home & Garden'],
        ['title' => 'Seeking Language Exchange', 'description' => 'Want to practice Spanish conversation', 'category' => 'Education'],
        ['title' => 'Computer Repair Needed', 'description' => 'Laptop won\'t start, need tech help', 'category' => 'Technology'],
        ['title' => 'Babysitter Wanted', 'description' => 'Occasional evening babysitting for 2 kids', 'category' => 'Childcare'],
        ['title' => 'Ride to Airport', 'description' => 'Need transportation next Tuesday morning', 'category' => 'Transportation'],
        ['title' => 'Carpentry Work', 'description' => 'Building custom shelves, need experienced carpenter', 'category' => 'Home & Garden'],
        ['title' => 'Tax Preparation Help', 'description' => 'First time filing self-employment taxes', 'category' => 'Financial'],
        ['title' => 'Pet Sitting', 'description' => 'Going on vacation, need someone to watch my cat', 'category' => 'Pets'],
        ['title' => 'Lawn Mowing', 'description' => 'Weekly lawn maintenance needed', 'category' => 'Home & Garden'],
        ['title' => 'Piano Lessons', 'description' => 'Adult beginner looking for patient teacher', 'category' => 'Music'],
    ];

    public function __construct($pdo, $tenantId, $userIds)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        $this->userIds = $userIds;
    }

    public function seed($count = 30)
    {
        $listingIds = [];
        $halfCount = ceil($count / 2);

        // Create offers
        for ($i = 0; $i < $halfCount && $i < count($this->offers); $i++) {
            $userId = $this->userIds[array_rand($this->userIds)];
            $offer = $this->offers[$i];

            $listingId = $this->createListing([
                'user_id' => $userId,
                'type' => 'offer',
                'title' => $offer['title'],
                'description' => $offer['description'],
                'category' => $offer['category'],
                'time_estimate' => rand(1, 10),
            ]);

            if ($listingId) {
                $listingIds[] = $listingId;
            }
        }

        // Create requests
        for ($i = 0; $i < ($count - $halfCount) && $i < count($this->requests); $i++) {
            $userId = $this->userIds[array_rand($this->userIds)];
            $request = $this->requests[$i];

            $listingId = $this->createListing([
                'user_id' => $userId,
                'type' => 'request',
                'title' => $request['title'],
                'description' => $request['description'],
                'category' => $request['category'],
                'time_estimate' => rand(1, 10),
            ]);

            if ($listingId) {
                $listingIds[] = $listingId;
            }
        }

        return $listingIds;
    }

    private function createListing($data)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO listings (
                    tenant_id, user_id, type, title, description, category,
                    time_estimate, status, created_at
                ) VALUES (
                    :tenant_id, :user_id, :type, :title, :description, :category,
                    :time_estimate, :status, :created_at
                )
            ");

            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'user_id' => $data['user_id'],
                'type' => $data['type'],
                'title' => $data['title'],
                'description' => $data['description'],
                'category' => $data['category'],
                'time_estimate' => $data['time_estimate'],
                'status' => rand(0, 10) > 2 ? 'active' : 'completed', // 80% active
                'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 60) . ' days')),
            ]);

            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            echo "Warning: Could not create listing {$data['title']}: " . $e->getMessage() . "\n";
            return null;
        }
    }
}
