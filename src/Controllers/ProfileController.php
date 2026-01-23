<?php

namespace Nexus\Controllers;

use Nexus\Models\User;
use Nexus\Models\Transaction;
use Nexus\Models\OrgMember;
use Nexus\Core\View;
use Nexus\Core\Csrf;
use Nexus\Core\TenantContext;

class ProfileController
{

    public function me()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }
        $this->show($_SESSION['user_id']);
    }

    public function show($id)
    {
        // Handle both numeric IDs and usernames
        if (!is_numeric($id)) {
            // Try to find user by username
            $user = User::findByUsername($id);
            if ($user) {
                // Use the numeric ID for the rest of the method
                $id = $user['id'];
            }
        }

        // If we didn't find by username, or if ID is numeric, use findById
        if (!isset($user) || !$user) {
            $user = User::findById($id);
        }

        if (!$user) {
            if ($id == 1) {
                error_log("ProfileController: User 1 not found via User::findById($id)");
            }
            \Nexus\Core\View::render('404');
            return;
        }

        // 1. Profile Visibility Enforcement
        $privacy = $user['privacy_profile'] ?? 'public';
        $isMe = (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id);
        $isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

        if (!$isMe && !$isAdmin) {
            if ($privacy === 'members' && !isset($_SESSION['user_id'])) {
                // Redirect to login or show limited view
                header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login?redirect=/profile/' . $id);
                exit;
            }
            if ($privacy === 'connections') {
                if (!isset($_SESSION['user_id'])) {
                    header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
                    exit;
                }
                // Check connection
                $status = \Nexus\Models\Connection::getStatus($_SESSION['user_id'], $id);
                if ($status !== 'accepted') {
                    // Private Profile View
                    // For now, simple error, or we can render a "Private Profile" view
                    \Nexus\Core\View::render('errors/private_profile');
                    return;
                }
            }
        }

        // 2. Search Engine Indexing
        // Default to allowed (1) if not set. If 0, noindex.
        $allowSearch = $user['privacy_search'] ?? 1;
        if (!$allowSearch) {
            \Nexus\Core\SEO::addMeta('robots', 'noindex, nofollow');
        } else {
            // If public, we might explicitly allow, or just do nothing (defaults to index)
            // But previously it was forcing noindex. Removed that force.
            \Nexus\Core\SEO::addMeta('robots', 'index, follow');
        }

        $reviews = \Nexus\Models\Review::getForUser($id);

        // SEO Data
        \Nexus\Core\SEO::setTitle($user['name'] ?? 'Member Profile');
        \Nexus\Core\SEO::setDescription(($user['bio'] ?? '') ?: "Check out " . ($user['name'] ?? 'this member') . "'s profile on the TimeBank.");
        if (!empty($user['avatar_url'])) {
            \Nexus\Core\SEO::setImage($user['avatar_url']);
        }

        // Connection Status
        $connection = null;
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $id) {
            $connection = \Nexus\Models\Connection::getStatus($_SESSION['user_id'], $id);
        }

        // Badges
        $badges = \Nexus\Models\UserBadge::getForUser($id);
        $showcasedBadges = \Nexus\Models\UserBadge::getShowcased($id);

        // Organization memberships (leadership roles)
        $userOrganizations = [];
        try {
            $userOrganizations = OrgMember::getUserOrganizations($id);
        } catch (\Exception $e) {
            // Silently fail if org tables don't exist
            $userOrganizations = [];
        }

        View::render('profile/show', [
            'user' => $user,
            'reviews' => $reviews,
            'connection' => $connection,
            'exchangesCount' => Transaction::countForUser($id),
            'badges' => $badges ?? [],
            'showcasedBadges' => $showcasedBadges ?? [],
            'userOrganizations' => $userOrganizations,
        ]);
    }

    public function edit()
    {
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
        // This prevents random logouts on transient DB issues
        if (!$user) {
            error_log("ProfileController::edit - User ID {$userId} not found after retries. Possible DB issue or deleted user.");
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login?error=session_check_failed');
            exit;
        }

        // Load newsletter subscription status
        $newsletterSubscription = \Nexus\Models\NewsletterSubscriber::findByEmail($user['email']);
        $isSubscribed = $newsletterSubscription && $newsletterSubscription['status'] === 'active';

        // Use View Engine for Theme Support
        View::render('profile/edit', [
            'user' => $user,
            'isNewsletterSubscribed' => $isSubscribed,
        ]);
    }

    /**
     * Sanitize bio HTML - allow only safe formatting tags
     * Strips dangerous tags like script, iframe, etc.
     */
    private static function sanitizeBioHtml(string $html): string
    {
        // Allow only safe tags for bio formatting
        $allowedTags = '<p><br><strong><b><em><i><u><ul><ol><li><a>';

        // Strip all tags except allowed ones
        $clean = strip_tags($html, $allowedTags);

        // Clean up anchor tags - only allow href attribute with http/https/mailto
        $clean = preg_replace_callback(
            '/<a\s+[^>]*href=["\']([^"\']*)["\'][^>]*>/i',
            function ($matches) {
                $href = $matches[1];
                // Only allow safe protocols
                if (preg_match('/^(https?:|mailto:)/i', $href) || strpos($href, '/') === 0) {
                    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">';
                }
                return ''; // Remove unsafe links
            },
            $clean
        );

        // Remove any remaining attributes from other tags (except href on anchors)
        $clean = preg_replace('/<(p|br|strong|b|em|i|u|ul|ol|li)\s+[^>]*>/i', '<$1>', $clean);

        return $clean;
    }

    public function update()
    {
        Csrf::verifyOrDie();

        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];

        // PROTECT EXISTING DATA - Only update fields that were actually submitted
        // Fetch current user data first
        $currentUser = User::findById($userId);

        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');

        // Preserve existing values if new values are empty
        $firstName = $firstName !== '' ? $firstName : ($currentUser['first_name'] ?? '');
        $lastName = $lastName !== '' ? $lastName : ($currentUser['last_name'] ?? '');

        // For optional fields, only update if field was present in form AND has value
        // If field is in POST but empty, user intentionally cleared it
        // If field is NOT in POST, preserve existing value
        $bio = isset($_POST['bio']) ? $_POST['bio'] : ($currentUser['bio'] ?? '');

        // Sanitize bio HTML - allow only safe formatting tags
        $bio = self::sanitizeBioHtml($bio);
        $location = isset($_POST['location']) ? $_POST['location'] : ($currentUser['location'] ?? '');
        $phone = isset($_POST['phone']) ? $_POST['phone'] : ($currentUser['phone'] ?? '');
        $profileType = isset($_POST['profile_type']) ? $_POST['profile_type'] : ($currentUser['profile_type'] ?? 'individual');
        $orgName = isset($_POST['organization_name']) ? $_POST['organization_name'] : ($currentUser['organization_name'] ?? '');

        if ($firstName && $lastName) {
            User::updateProfile($userId, $firstName, $lastName, $bio, $location, $phone, $profileType, $orgName);

            // Handle Avatar Upload
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['avatar']['tmp_name'];
                $fileName = $_FILES['avatar']['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                // Security: Validate file extension
                $allowedExtensions = ['jpg', 'gif', 'png', 'jpeg', 'webp'];
                if (!in_array($fileExtension, $allowedExtensions)) {
                    // Invalid extension - skip upload silently
                    goto afterAvatarUpload;
                }

                // Security: Validate MIME type from file content
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $detectedMime = $finfo->file($fileTmpPath);
                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($detectedMime, $allowedMimes)) {
                    // Invalid MIME type - skip upload silently
                    goto afterAvatarUpload;
                }

                // Security: Verify it's actually an image
                $imageInfo = @getimagesize($fileTmpPath);
                if ($imageInfo === false) {
                    // Not a valid image - skip upload silently
                    goto afterAvatarUpload;
                }

                // Ensure absolute path is correct for server environment
                $tenantId = TenantContext::getId();
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $tenantId . '/avatars/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // SECURITY: Use cryptographically secure random filename
                $newFileName = bin2hex(random_bytes(16)) . '.' . $fileExtension;
                $dest_path = $uploadDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $avatarUrl = '/uploads/' . $tenantId . '/avatars/' . $newFileName;
                    User::updateAvatar($userId, $avatarUrl);
                }
            }
            afterAvatarUpload:

            // Gamification: Check profile completion badge
            try {
                \Nexus\Services\GamificationService::checkProfileBadge($userId);
            } catch (\Throwable $e) {
                error_log("Gamification profile error: " . $e->getMessage());
            }

            // Handle Newsletter Subscription Toggle
            $this->updateNewsletterPreference($currentUser, isset($_POST['newsletter_subscribed']));

            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/profile/' . $userId);
            exit;
        }
    }

    /**
     * Update newsletter subscription based on user preference
     */
    private function updateNewsletterPreference(array $user, bool $wantsSubscribed): void
    {
        try {
            $email = $user['email'];
            $existing = \Nexus\Models\NewsletterSubscriber::findByEmail($email);

            if ($wantsSubscribed) {
                // User wants to be subscribed
                if (!$existing) {
                    // Create new subscription
                    \Nexus\Models\NewsletterSubscriber::createConfirmed(
                        $email,
                        $user['first_name'],
                        $user['last_name'],
                        'profile_settings',
                        $user['id']
                    );
                } elseif ($existing['status'] === 'unsubscribed') {
                    // Resubscribe - update status to active
                    \Nexus\Models\NewsletterSubscriber::update($existing['id'], ['status' => 'active']);
                }

                // Record GDPR consent for marketing email
                try {
                    $gdprService = new \Nexus\Services\Enterprise\GdprService();
                    $gdprService->recordConsent(
                        $user['id'],
                        'marketing_email',
                        true,
                        'User opted in to newsletter via profile settings.',
                        '1.0'
                    );
                } catch (\Throwable $e) {
                    error_log("GDPR Consent Recording Failed: " . $e->getMessage());
                }

                // Sync to Mailchimp
                try {
                    $mailchimp = new \Nexus\Services\MailchimpService();
                    $mailchimp->subscribe($email, $user['first_name'], $user['last_name']);
                } catch (\Throwable $e) {
                    error_log("Mailchimp Subscribe Failed: " . $e->getMessage());
                }

            } else {
                // User wants to unsubscribe
                if ($existing && $existing['status'] === 'active') {
                    \Nexus\Models\NewsletterSubscriber::update($existing['id'], ['status' => 'unsubscribed']);

                    // Record GDPR consent withdrawal
                    try {
                        $gdprService = new \Nexus\Services\Enterprise\GdprService();
                        $gdprService->recordConsent(
                            $user['id'],
                            'marketing_email',
                            false,
                            'User opted out of newsletter via profile settings.',
                            '1.0'
                        );
                    } catch (\Throwable $e) {
                        error_log("GDPR Consent Withdrawal Recording Failed: " . $e->getMessage());
                    }

                    // Unsubscribe from Mailchimp
                    try {
                        $mailchimp = new \Nexus\Services\MailchimpService();
                        $mailchimp->unsubscribe($email);
                    } catch (\Throwable $e) {
                        error_log("Mailchimp Unsubscribe Failed: " . $e->getMessage());
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log("Newsletter Preference Update Failed: " . $e->getMessage());
        }
    }
}
