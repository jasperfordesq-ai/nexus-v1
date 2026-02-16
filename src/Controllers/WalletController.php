<?php

namespace Nexus\Controllers;

use Nexus\Models\Transaction;
use Nexus\Models\User;
use Nexus\Core\View;
use Nexus\Middleware\TenantModuleMiddleware;

class WalletController
{
    /**
     * Check if wallet module is enabled
     */
    private function checkFeature()
    {
        TenantModuleMiddleware::require('wallet');
    }

    public function index()
    {
        $this->checkFeature();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $user = User::findById($userId);

        // Robust retry logic - handles temporary DB issues without destroying session
        if (!$user) {
            $maxRetries = 3;
            for ($i = 0; $i < $maxRetries && !$user; $i++) {
                usleep(200000); // 200ms delay between retries
                $user = User::findById($userId);
            }
        }

        // If still not found after retries, log and redirect but DON'T destroy session
        if (!$user) {
            error_log("WalletController::index - User ID {$userId} not found after retries. Possible DB issue or deleted user.");
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login?error=session_check_failed');
            exit;
        }

        $transactions = Transaction::getHistory($userId);

        // Pre-fill Transfer Form (from profile page "Send Credits" button)
        $prefillRecipient = null;
        if (isset($_GET['to'])) {
            $recipient = User::findById($_GET['to']);
            if ($recipient) {
                $prefillRecipient = [
                    'id' => $recipient['id'],
                    'username' => $recipient['username'] ?? '',
                    'display_name' => $recipient['name'] ?? ($recipient['first_name'] . ' ' . $recipient['last_name']),
                    'avatar_url' => $recipient['avatar_url'] ?? ''
                ];
            }
        }

        View::render('wallet/index', [
            'user' => $user,
            'transactions' => $transactions,
            'pageTitle' => 'My Wallet',
            'prefillRecipient' => $prefillRecipient
        ]);
    }

    public function transfer()
    {
        \Nexus\Core\Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $sender = User::findById($userId);

        // Robust retry logic - handles temporary DB issues without destroying session
        if (!$sender) {
            $maxRetries = 3;
            for ($i = 0; $i < $maxRetries && !$sender; $i++) {
                usleep(200000); // 200ms delay between retries
                $sender = User::findById($userId);
            }
        }

        // If still not found after retries, log and redirect but DON'T destroy session
        if (!$sender) {
            error_log("WalletController::transfer - User ID {$userId} not found after retries. Possible DB issue or deleted user.");
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login?error=session_check_failed');
            exit;
        }

        // Support username (new), recipient_id (fallback for users without username), and email (legacy)
        $receiverUsername = $_POST['username'] ?? '';
        $receiverId = $_POST['recipient_id'] ?? '';
        $receiverEmail = $_POST['email'] ?? '';
        $amount = (int) ($_POST['amount'] ?? 0);
        $description = $_POST['description'] ?? '';

        // Find receiver - prefer username, then ID, then email
        $receiver = null;
        if ($amount > 0 && ($receiverUsername || $receiverId || $receiverEmail)) {
            if ($receiverUsername) {
                $receiver = User::findByUsername($receiverUsername);
            } elseif ($receiverId) {
                $receiver = User::findById($receiverId);
            } elseif ($receiverEmail) {
                $receiver = User::findByEmail($receiverEmail);
            }
            if ($receiver) {
                try {
                    $transactionId = Transaction::create($_SESSION['user_id'], $receiver['id'], $amount, $description);

                    // Instant Notification
                    \Nexus\Models\Notification::create(
                        $receiver['id'],
                        "You received $amount credits from " . $sender['name'],
                        "/wallet",
                        'money'
                    );

                    // Refetch Receiver to get updated balance
                    $updatedReceiver = User::findById($receiver['id']);
                    $newBalance = $updatedReceiver['balance'];

                    // Send Email Receipt to Receiver
                    try {
                        if ($receiver['email']) {
                            $mailer = new \Nexus\Core\Mailer();
                            $subject = "Time Credits Received";

                            $html = \Nexus\Core\EmailTemplate::render(
                                "Payment Received",
                                "You received $amount Time Credits from {$sender['name']}.",
                                "
                                    <p><strong>Amount:</strong> $amount Credits</p>
                                    <p><strong>From:</strong> {$sender['name']}</p>
                                    <p><strong>Note:</strong> \"$description\"</p>
                                    <hr style='border: 0; border-top: 1px solid #eee; margin: 15px 0;'>
                                    <p><strong>New Balance:</strong> $newBalance Credits</p>
                                ",
                                "View Wallet",
                                \Nexus\Core\TenantContext::getFrontendUrl() . \Nexus\Core\TenantContext::getBasePath() . "/wallet",
                                "Project NEXUS"
                            );

                            $mailer->send($receiver['email'], $subject, $html);
                        }
                    } catch (\Throwable $e) {
                        error_log("Payment Email Failed: " . $e->getMessage());
                    }

                    // Notify Admins
                    try {
                        $admins = User::getAdmins();
                        $mailer = new \Nexus\Core\Mailer();
                        foreach ($admins as $admin) {
                            if (!$admin['email']) continue;

                            $adminSubject = "Admin Alert: Transaction Processed";
                            $adminHtml = \Nexus\Core\EmailTemplate::render(
                                "Transaction Alert",
                                "A transfer of $amount credits has been processed.",
                                "
                                    <p><strong>From:</strong> {$sender['name']} (#{$sender['id']})</p>
                                    <p><strong>To:</strong> {$receiver['name']} (#{$receiver['id']})</p>
                                    <p><strong>Amount:</strong> $amount Credits</p>
                                    <p><strong>Description:</strong> $description</p>
                                ",
                                "View Logs",
                                \Nexus\Core\TenantContext::getFrontendUrl() . \Nexus\Core\TenantContext::getBasePath() . "/admin-legacy/logs",
                                "Project NEXUS Admin"
                            );

                            $mailer->send($admin['email'], $adminSubject, $adminHtml);
                        }
                    } catch (\Throwable $e) {
                        error_log("Admin Notification Failed: " . $e->getMessage());
                    }
                } catch (\Exception $e) {
                    echo "Transfer failed: " . $e->getMessage();
                }

                // Check for Timebanking Badges (Sender and Receiver)
                try {
                    \Nexus\Services\GamificationService::checkTimebankingBadges($_SESSION['user_id']);
                    \Nexus\Services\GamificationService::checkTimebankingBadges($receiver['id']);

                    // Record giving streak for sender
                    \Nexus\Services\StreakService::recordGiving($_SESSION['user_id']);

                    // Award XP for transaction
                    \Nexus\Services\GamificationService::awardXP($_SESSION['user_id'], \Nexus\Services\GamificationService::XP_VALUES['send_credits'] * $amount, 'send_credits', "Sent $amount credits");
                    \Nexus\Services\GamificationService::awardXP($receiver['id'], \Nexus\Services\GamificationService::XP_VALUES['receive_credits'] * $amount, 'receive_credits', "Received $amount credits");
                } catch (\Throwable $e) {
                    error_log("Badge Check Failed: " . $e->getMessage());
                }

                // Redirect to Review Page
                header("Location: " . \Nexus\Core\TenantContext::getBasePath() . "/reviews/create/$transactionId?receiver={$receiver['id']}");
                exit;
            } else {
                echo "User not found.";
            }
        }
    }
}
