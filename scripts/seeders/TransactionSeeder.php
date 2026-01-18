<?php

class TransactionSeeder
{
    private $pdo;
    private $tenantId;
    private $userIds;

    private $descriptions = [
        'Web design services',
        'Garden maintenance',
        'Dog walking',
        'Tutoring session',
        'Home cooked meal',
        'House cleaning',
        'Car repair',
        'Moving help',
        'Computer repair',
        'Babysitting',
    ];

    public function __construct($pdo, $tenantId, $userIds)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        $this->userIds = $userIds;
    }

    public function seed($count = 50)
    {
        $transactionIds = [];

        for ($i = 0; $i < $count; $i++) {
            // Pick random sender and receiver (must be different)
            $senderId = $this->userIds[array_rand($this->userIds)];
            $receiverId = $this->userIds[array_rand($this->userIds)];

            // Ensure different users
            while ($senderId === $receiverId) {
                $receiverId = $this->userIds[array_rand($this->userIds)];
            }

            $transactionId = $this->createTransaction([
                'sender_id' => $senderId,
                'giver_id' => $senderId,
                'receiver_id' => $receiverId,
                'amount' => rand(1, 20),
                'description' => $this->descriptions[array_rand($this->descriptions)],
                'status' => rand(0, 10) > 1 ? 'completed' : 'pending', // 90% completed
            ]);

            if ($transactionId) {
                $transactionIds[] = $transactionId;
            }
        }

        return $transactionIds;
    }

    private function createTransaction($data)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO transactions (
                    tenant_id, sender_id, giver_id, receiver_id, amount,
                    description, status, created_at
                ) VALUES (
                    :tenant_id, :sender_id, :giver_id, :receiver_id, :amount,
                    :description, :status, :created_at
                )
            ");

            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'sender_id' => $data['sender_id'],
                'giver_id' => $data['giver_id'],
                'receiver_id' => $data['receiver_id'],
                'amount' => $data['amount'],
                'description' => $data['description'],
                'status' => $data['status'],
                'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 90) . ' days')),
            ]);

            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            echo "Warning: Could not create transaction: " . $e->getMessage() . "\n";
            return null;
        }
    }
}
