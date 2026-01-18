<?php

class UserSeeder
{
    private $pdo;
    private $tenantId;

    // Realistic first names
    private $firstNames = [
        'James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda',
        'William', 'Barbara', 'David', 'Elizabeth', 'Richard', 'Susan', 'Joseph', 'Jessica',
        'Thomas', 'Sarah', 'Charles', 'Karen', 'Christopher', 'Nancy', 'Daniel', 'Lisa',
        'Matthew', 'Betty', 'Anthony', 'Margaret', 'Donald', 'Sandra', 'Mark', 'Ashley',
        'Paul', 'Kimberly', 'Steven', 'Emily', 'Andrew', 'Donna', 'Kenneth', 'Michelle',
        'Joshua', 'Dorothy', 'Kevin', 'Carol', 'Brian', 'Amanda', 'George', 'Melissa',
        'Emma', 'Liam', 'Olivia', 'Noah', 'Ava', 'Ethan', 'Sophia', 'Mason', 'Isabella',
        'Lucas', 'Mia', 'Logan', 'Charlotte', 'Oliver', 'Amelia', 'Elijah', 'Harper'
    ];

    // Realistic last names
    private $lastNames = [
        'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis',
        'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas',
        'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'Perez', 'Thompson', 'White',
        'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson', 'Walker', 'Young',
        'Allen', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores',
        'Green', 'Adams', 'Nelson', 'Baker', 'Hall', 'Rivera', 'Campbell', 'Mitchell',
        'Carter', 'Roberts', 'Murphy', 'Stewart', 'Morris', 'Rogers', 'Reed', 'Cook'
    ];

    private $bios = [
        'Passionate about community building and sustainable living.',
        'Love helping others and volunteering in my free time.',
        'Retired teacher with a passion for lifelong learning.',
        'Small business owner supporting local communities.',
        'Environmental activist and community organizer.',
        'Parent of three and active neighborhood volunteer.',
        'Software developer interested in civic technology.',
        'Artist and creative professional sharing skills.',
        'Healthcare worker committed to community wellness.',
        'Student looking to gain experience and give back.',
    ];

    public function __construct($pdo, $tenantId)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
    }

    public function seed($count = 50)
    {
        $userIds = [];

        // Create admin user
        $adminId = $this->createUser([
            'email' => 'admin@nexus.test',
            'name' => 'Admin User',
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'role' => 'admin',
            'is_verified' => 1,
            'xp' => 5000,
            'level' => 10,
            'points' => 5000,
        ]);
        $userIds[] = $adminId;

        // Create test users
        for ($i = 1; $i <= min(5, $count - 1); $i++) {
            $userId = $this->createUser([
                'email' => "user{$i}@nexus.test",
                'name' => "Test User {$i}",
                'password' => password_hash('password', PASSWORD_BCRYPT),
                'role' => 'user',
                'is_verified' => 1,
                'xp' => rand(100, 1000),
                'level' => rand(1, 5),
                'points' => rand(50, 500),
            ]);
            $userIds[] = $userId;
        }

        // Create random users
        $remaining = $count - count($userIds);
        for ($i = 0; $i < $remaining; $i++) {
            $firstName = $this->firstNames[array_rand($this->firstNames)];
            $lastName = $this->lastNames[array_rand($this->lastNames)];
            $name = "{$firstName} {$lastName}";
            $email = strtolower($firstName . '.' . $lastName . rand(1, 999) . '@example.com');

            $userId = $this->createUser([
                'email' => $email,
                'name' => $name,
                'password' => password_hash('password', PASSWORD_BCRYPT),
                'role' => 'user',
                'bio' => $this->bios[array_rand($this->bios)],
                'location' => $this->randomLocation(),
                'is_verified' => rand(0, 10) > 2 ? 1 : 0, // 80% verified
                'xp' => rand(0, 2000),
                'level' => rand(1, 8),
                'points' => rand(0, 1000),
            ]);

            if ($userId) {
                $userIds[] = $userId;
            }
        }

        return $userIds;
    }

    private function createUser($data)
    {
        $defaults = [
            'tenant_id' => $this->tenantId,
            'bio' => null,
            'location' => null,
            'role' => 'user',
            'xp' => 0,
            'level' => 1,
            'points' => 0,
            'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 365) . ' days')),
        ];

        $userData = array_merge($defaults, $data);

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO users (
                    tenant_id, email, name, password, role, bio, location,
                    is_verified, xp, level, points, created_at
                ) VALUES (
                    :tenant_id, :email, :name, :password, :role, :bio, :location,
                    :is_verified, :xp, :level, :points, :created_at
                )
            ");

            $stmt->execute([
                'tenant_id' => $userData['tenant_id'],
                'email' => $userData['email'],
                'name' => $userData['name'],
                'password' => $userData['password'],
                'role' => $userData['role'],
                'bio' => $userData['bio'],
                'location' => $userData['location'],
                'is_verified' => $userData['is_verified'],
                'xp' => $userData['xp'],
                'level' => $userData['level'],
                'points' => $userData['points'],
                'created_at' => $userData['created_at'],
            ]);

            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            echo "Warning: Could not create user {$userData['email']}: " . $e->getMessage() . "\n";
            return null;
        }
    }

    private function randomLocation()
    {
        $cities = [
            'Dublin', 'Cork', 'Limerick', 'Galway', 'Waterford',
            'London', 'Manchester', 'Birmingham', 'Leeds', 'Glasgow',
            'New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix',
            'Toronto', 'Vancouver', 'Montreal', 'Calgary', 'Ottawa',
        ];

        return $cities[array_rand($cities)];
    }
}
