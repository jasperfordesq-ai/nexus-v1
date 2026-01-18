<?php
// Connections - Add New
// Path: views/connections/add.php

use Nexus\Core\TenantContext;
use Nexus\Models\Connection;
use Nexus\Models\Notification;
use Nexus\Models\User;

$layout = layout(); // Fixed: centralized detection
$basePath = TenantContext::getBasePath();

$success = false;
$error = null;
$receiverUser = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        $error = "You must be logged in to send requests.";
    } else {
        $requesterId = $_SESSION['user_id'];
        $receiverId = $_POST['receiver_id'] ?? null;
        $memberQuery = $_POST['member_query'] ?? null;

        // 1. Resolve Receiver ID
        if (!$receiverId && $memberQuery) {
            $targetUser = \Nexus\Core\DatabaseWrapper::query("SELECT * FROM users WHERE email = ? LIMIT 1", [$memberQuery])->fetch();
            if ($targetUser) {
                $receiverId = $targetUser['id'];
                $receiverUser = $targetUser;
            } else {
                $error = "User not found with that email.";
            }
        } elseif ($receiverId) {
            $receiverUser = \Nexus\Core\DatabaseWrapper::query("SELECT * FROM users WHERE id = ? LIMIT 1", [$receiverId])->fetch();
        }

        // 2. Send Request
        if ($receiverId && !$error) {
            if ($receiverId == $requesterId) {
                $error = "You cannot add yourself.";
            } else {
                try {
                    $result = Connection::sendRequest($requesterId, $receiverId);
                    if ($result) {
                        $requesterName = $_SESSION['user_name'] ?? 'A member';
                        Notification::create(
                            $receiverId,
                            "$requesterName sent you a friend request.",
                            TenantContext::getBasePath() . "/profile/$requesterId",
                            'connection_request'
                        );
                        $success = true;
                    } else {
                        $error = "Connection request already exists or you are already friends.";
                    }
                } catch (Exception $e) {
                    $error = "System error: " . $e->getMessage();
                }
            }
        } elseif (!$receiverId && !$error) {
            $error = "Please specify a user.";
        }
    }
}

require __DIR__ . '/../layouts/' . $layout . '/header.php';
?>

<style>
.add-connection-container {
    max-width: 500px;
    margin: 0 auto;
    padding: 140px 20px 40px;
}

.add-connection-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
    backdrop-filter: blur(20px);
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    text-align: center;
    padding: 40px 32px;
}

[data-theme="dark"] .add-connection-card {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.9));
    border-color: rgba(255, 255, 255, 0.1);
}

.success-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 24px;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.1));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.success-icon i {
    font-size: 2.5rem;
    color: #10b981;
}

.add-connection-card h2 {
    margin: 0 0 12px;
    font-size: 1.5rem;
    font-weight: 700;
    color: #10b981;
}

.add-connection-card p {
    margin: 0 0 24px;
    color: #6b7280;
    font-size: 1rem;
}

[data-theme="dark"] .add-connection-card p {
    color: #94a3b8;
}

.receiver-preview {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: rgba(99, 102, 241, 0.08);
    border-radius: 16px;
    margin-bottom: 24px;
}

[data-theme="dark"] .receiver-preview {
    background: rgba(99, 102, 241, 0.15);
}

.receiver-preview img {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #6366f1;
}

.receiver-preview-info {
    text-align: left;
}

.receiver-preview-name {
    font-weight: 600;
    font-size: 1.1rem;
    color: #1f2937;
    margin-bottom: 4px;
}

[data-theme="dark"] .receiver-preview-name {
    color: #f1f5f9;
}

.receiver-preview-meta {
    font-size: 0.875rem;
    color: #6b7280;
}

[data-theme="dark"] .receiver-preview-meta {
    color: #94a3b8;
}

.btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 28px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: none;
    border-radius: 14px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
    width: 100%;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
}

.btn-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    background: transparent;
    color: #6366f1;
    border: 2px solid rgba(99, 102, 241, 0.3);
    border-radius: 12px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
    margin-top: 12px;
}

.btn-secondary:hover {
    background: rgba(99, 102, 241, 0.1);
    border-color: #6366f1;
}

.error-alert {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #dc2626;
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 0.9rem;
    text-align: left;
}

[data-theme="dark"] .error-alert {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
}

.form-group {
    margin-bottom: 20px;
    text-align: left;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #374151;
}

[data-theme="dark"] .form-group label {
    color: #e2e8f0;
}

.form-input {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid rgba(0, 0, 0, 0.1);
    border-radius: 12px;
    font-size: 1rem;
    background: rgba(255, 255, 255, 0.5);
    transition: all 0.2s;
    box-sizing: border-box;
}

[data-theme="dark"] .form-input {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.1);
    color: #f1f5f9;
}

.form-input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

.form-input::placeholder {
    color: #9ca3af;
}

.add-connection-card h1 {
    margin: 0 0 8px;
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
}

[data-theme="dark"] .add-connection-card h1 {
    color: #f1f5f9;
}

.form-header-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.1));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.form-header-icon i {
    font-size: 1.75rem;
    color: #6366f1;
}

@media (max-width: 640px) {
    .add-connection-container {
        padding: 120px 16px 100px;
    }

    .add-connection-card {
        padding: 32px 20px;
    }
}
</style>

<div class="add-connection-container">
    <?php if ($success): ?>
        <div class="add-connection-card">
            <div class="success-icon">
                <i class="fa-solid fa-check"></i>
            </div>
            <h2>Request Sent!</h2>

            <?php if ($receiverUser): ?>
            <div class="receiver-preview">
                <img src="<?= htmlspecialchars($receiverUser['avatar_url'] ?: '/assets/img/defaults/default_avatar.webp') ?>" alt="">
                <div class="receiver-preview-info">
                    <div class="receiver-preview-name"><?= htmlspecialchars($receiverUser['name'] ?? $receiverUser['first_name'] . ' ' . $receiverUser['last_name']) ?></div>
                    <div class="receiver-preview-meta">Friend request pending</div>
                </div>
            </div>
            <?php endif; ?>

            <p>Your connection request has been delivered. You'll be notified when they respond.</p>

            <a href="<?= $basePath ?>/home" class="btn-primary">
                <i class="fa-solid fa-home"></i>
                Return to Feed
            </a>
            <a href="<?= $basePath ?>/connections" class="btn-secondary">
                <i class="fa-solid fa-user-group"></i>
                View My Friends
            </a>
        </div>
    <?php else: ?>
        <div class="add-connection-card">
            <div class="form-header-icon">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <h1>Add Friend</h1>
            <p style="margin-bottom: 24px;">Enter the email address of the person you'd like to connect with.</p>

            <?php if ($error): ?>
                <div class="error-alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="member_query">Email Address</label>
                    <input type="email" id="member_query" name="member_query" class="form-input" placeholder="friend@example.com" required>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-paper-plane"></i>
                    Send Friend Request
                </button>
            </form>

            <a href="<?= $basePath ?>/members" class="btn-secondary">
                <i class="fa-solid fa-search"></i>
                Browse Members Instead
            </a>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/' . $layout . '/footer.php'; ?>