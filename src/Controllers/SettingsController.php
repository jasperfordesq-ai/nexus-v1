<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\Auth;
use Nexus\Models\User;
use Nexus\Core\TenantContext;
use Nexus\Services\Enterprise\GdprService;

class SettingsController
{
    /**
     * Display the Settings Hub
     */
    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $user = User::findById($_SESSION['user_id']);

        View::render('settings/index', [
            'user' => $user
        ]);
    }

    /**
     * Sanitize bio HTML - allow only safe formatting tags
     */
    private static function sanitizeBioHtml(string $html): string
    {
        $allowedTags = '<p><br><strong><b><em><i><u><ul><ol><li><a>';
        $clean = strip_tags($html, $allowedTags);

        $clean = preg_replace_callback(
            '/<a\s+[^>]*href=["\']([^"\']*)["\'][^>]*>/i',
            function ($matches) {
                $href = $matches[1];
                if (preg_match('/^(https?:|mailto:)/i', $href) || strpos($href, '/') === 0) {
                    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">';
                }
                return '';
            },
            $clean
        );

        $clean = preg_replace('/<(p|br|strong|b|em|i|u|ul|ol|li)\s+[^>]*>/i', '<$1>', $clean);
        return $clean;
    }

    /**
     * Update Profile (Name, Bio)
     */
    public function updateProfile()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];

        // PROTECT EXISTING DATA - Fetch current user data first
        $currentUser = User::findById($userId);

        // Get form fields - support both separate first/last name and combined name
        $firstName = $_POST['first_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        $displayName = $_POST['name'] ?? '';

        // If first/last not provided but display name is, split it
        if (empty($firstName) && !empty($displayName)) {
            $parts = explode(' ', trim($displayName), 2);
            $firstName = $parts[0];
            $lastName = $parts[1] ?? '';
        }

        // Preserve existing values if empty
        $firstName = !empty($firstName) ? $firstName : ($currentUser['first_name'] ?? '');
        $lastName = !empty($lastName) ? $lastName : ($currentUser['last_name'] ?? '');

        // Build update data
        $updateData = [
            'first_name' => $firstName,
            'last_name' => $lastName,
        ];

        // Also save Display Name to the 'name' field if provided
        // This ensures the user's preferred display name is used in feeds
        if (!empty($displayName)) {
            $updateData['name'] = $displayName;
        }

        // Only update bio if it was in the POST (user intentionally changed it)
        if (isset($_POST['bio'])) {
            $updateData['bio'] = self::sanitizeBioHtml($_POST['bio']);
        }

        // Only update location if provided
        if (isset($_POST['location'])) {
            $updateData['location'] = $_POST['location'];
        }

        // Only update phone if provided
        if (isset($_POST['phone'])) {
            $updateData['phone'] = $_POST['phone'];
        }

        // Handle profile type change (individual/organisation)
        if (isset($_POST['profile_type'])) {
            $profileType = $_POST['profile_type'];
            // Validate profile type - only allow valid values
            if (in_array($profileType, ['individual', 'organisation'])) {
                $updateData['profile_type'] = $profileType;

                // If organisation, also save organization_name
                if ($profileType === 'organisation' && isset($_POST['organization_name'])) {
                    $updateData['organization_name'] = trim($_POST['organization_name']);
                }
                // If switching to individual, clear organization_name
                if ($profileType === 'individual') {
                    $updateData['organization_name'] = '';
                }
            }
        }

        User::update($userId, $updateData);

        // Update Session Name
        $_SESSION['user_name'] = trim($firstName . ' ' . $lastName);

        // Handle Avatar Upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['avatar']['tmp_name'];
            $fileName = $_FILES['avatar']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Security: Validate file extension
            $allowedExtensions = ['jpg', 'gif', 'png', 'jpeg', 'webp'];
            if (!in_array($fileExtension, $allowedExtensions)) {
                goto afterSettingsAvatarUpload;
            }

            // Security: Validate MIME type from file content
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo->file($fileTmpPath);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($detectedMime, $allowedMimes)) {
                goto afterSettingsAvatarUpload;
            }

            // Security: Verify it's actually an image
            $imageInfo = @getimagesize($fileTmpPath);
            if ($imageInfo === false) {
                goto afterSettingsAvatarUpload;
            }

            // Ensure absolute path is correct for server environment
            $tenantId = TenantContext::getId();
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $tenantId . '/avatars/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $dest_path = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $avatarUrl = '/uploads/' . $tenantId . '/avatars/' . $newFileName;
                User::updateAvatar($userId, $avatarUrl);
            }
        }
        afterSettingsAvatarUpload:

        header('Location: ' . TenantContext::getBasePath() . '/settings?section=profile&success=profile_updated');
        exit;
    }

    /**
     * Update Password
     */
    public function updatePassword()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Basic Validation
        if (empty($currentPassword) || empty($newPassword)) {
            // Ideally flash error
            header('Location: ' . TenantContext::getBasePath() . '/settings?section=security&error=missing_fields');
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            header('Location: ' . TenantContext::getBasePath() . '/settings?section=security&error=mismatch');
            exit;
        }

        $userId = $_SESSION['user_id'];

        // Verify Current
        if (!User::verifyPassword($userId, $currentPassword)) {
            header('Location: ' . TenantContext::getBasePath() . '/settings?section=security&error=invalid_current');
            exit;
        }

        // Update
        User::updatePassword($userId, $newPassword);

        header('Location: ' . TenantContext::getBasePath() . '/settings?section=security&success=password_updated');
        exit;
    }

    /**
     * Update Privacy Settings
     */
    public function updatePrivacy()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $profile = $_POST['privacy_profile'] ?? 'public';
        $search = isset($_POST['privacy_search']) ? 1 : 0;
        $contact = $_POST['privacy_contact'] ?? 'no'; // Expect 'yes' or 'no' from select

        // Validate Enum
        if (!in_array($profile, ['public', 'members', 'connections'])) {
            $profile = 'public';
        }

        $contactInt = ($contact === 'yes') ? 1 : 0;

        User::updatePrivacy($_SESSION['user_id'], $profile, $search, $contactInt);

        header('Location: ' . TenantContext::getBasePath() . '/settings?section=privacy&success=privacy_updated');
        exit;
    }

    /**
     * Display Privacy Settings (GDPR self-service)
     */
    public function privacy()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $user = User::findById($_SESSION['user_id']);

        View::render('settings/privacy', [
            'user' => $user
        ]);
    }

    /**
     * Update Notification Settings
     */
    public function updateNotifications()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];

        // Email notification preferences
        $emailMessages = isset($_POST['email_messages']) ? 1 : 0;
        $emailConnections = isset($_POST['email_connections']) ? 1 : 0;
        $emailTransactions = isset($_POST['email_transactions']) ? 1 : 0;
        $emailReviews = isset($_POST['email_reviews']) ? 1 : 0;

        // Push notification preference
        $pushEnabled = isset($_POST['push_enabled']) ? 1 : 0;

        // Organization notification preferences
        $emailOrgPayments = isset($_POST['email_org_payments']) ? 1 : 0;
        $emailOrgTransfers = isset($_POST['email_org_transfers']) ? 1 : 0;
        $emailOrgMembership = isset($_POST['email_org_membership']) ? 1 : 0;
        $emailOrgAdmin = isset($_POST['email_org_admin']) ? 1 : 0;

        // Store notification preferences in user_preferences or users table
        // Using a simple JSON column approach if available, or individual columns
        $preferences = [
            'email_messages' => $emailMessages,
            'email_connections' => $emailConnections,
            'email_transactions' => $emailTransactions,
            'email_reviews' => $emailReviews,
            'push_enabled' => $pushEnabled,
            // Organization notifications
            'email_org_payments' => $emailOrgPayments,
            'email_org_transfers' => $emailOrgTransfers,
            'email_org_membership' => $emailOrgMembership,
            'email_org_admin' => $emailOrgAdmin
        ];

        // Update user preferences
        User::updateNotificationPreferences($userId, $preferences);

        header('Location: ' . TenantContext::getBasePath() . '/settings?section=notifications&success=notifications_updated');
        exit;
    }

    /**
     * Update user consent (AJAX endpoint)
     */
    public function updateConsent()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $slug = $input['slug'] ?? '';
        $given = (bool) ($input['given'] ?? false);

        if (empty($slug)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing consent type']);
            exit;
        }

        try {
            $gdprService = new GdprService();
            $result = $gdprService->updateUserConsent($_SESSION['user_id'], $slug, $given);

            // Sync newsletter subscription when marketing_email consent changes
            if ($slug === 'marketing_email') {
                $this->syncNewsletterFromConsent($_SESSION['user_id'], $given);
            }

            echo json_encode(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Sync newsletter subscription based on marketing_email consent
     */
    private function syncNewsletterFromConsent(int $userId, bool $subscribed): void
    {
        try {
            $user = \Nexus\Models\User::findById($userId);
            if (!$user) return;

            $email = $user['email'];
            $existing = \Nexus\Models\NewsletterSubscriber::findByEmail($email);

            if ($subscribed) {
                // Subscribe
                if (!$existing) {
                    \Nexus\Models\NewsletterSubscriber::createConfirmed(
                        $email,
                        $user['first_name'],
                        $user['last_name'],
                        'gdpr_settings',
                        $userId
                    );
                } elseif ($existing['status'] === 'unsubscribed') {
                    \Nexus\Models\NewsletterSubscriber::update($existing['id'], ['status' => 'active']);
                }

                // Sync to Mailchimp
                try {
                    $mailchimp = new \Nexus\Services\MailchimpService();
                    $mailchimp->subscribe($email, $user['first_name'], $user['last_name']);
                } catch (\Throwable $e) {
                    error_log("Mailchimp Subscribe Failed: " . $e->getMessage());
                }
            } else {
                // Unsubscribe
                if ($existing && $existing['status'] === 'active') {
                    \Nexus\Models\NewsletterSubscriber::update($existing['id'], ['status' => 'unsubscribed']);

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
            error_log("Newsletter Sync From Consent Failed: " . $e->getMessage());
        }
    }

    /**
     * Submit GDPR request (AJAX endpoint)
     */
    public function submitGdprRequest()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $requestType = $input['request_type'] ?? '';

        $validTypes = ['access', 'erasure', 'portability', 'rectification', 'restriction', 'objection'];
        if (!in_array($requestType, $validTypes)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid request type']);
            exit;
        }

        try {
            $gdprService = new GdprService();
            $result = $gdprService->createRequest($_SESSION['user_id'], $requestType, [
                'notes' => $input['notes'] ?? null,
                'metadata' => [
                    'source' => 'user_settings',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ],
            ]);

            echo json_encode([
                'success' => true,
                'id' => $result['id'],
                'message' => 'Your request has been submitted successfully.',
            ]);
        } catch (\RuntimeException $e) {
            // Duplicate request
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to submit request. Please try again.']);
        }
        exit;
    }

    /**
     * Update Federation Settings (POST form submission)
     */
    public function updateFederation()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];

        // Check if federation is available
        if (!\Nexus\Services\FederationUserService::isFederationAvailableForUser($userId)) {
            header('Location: ' . TenantContext::getBasePath() . '/settings?section=federation&error=not_available');
            exit;
        }

        // Collect settings from form
        $settings = [
            'federation_optin' => isset($_POST['federation_optin']),
            'profile_visible_federated' => isset($_POST['profile_visible_federated']),
            'messaging_enabled_federated' => isset($_POST['messaging_enabled_federated']),
            'transactions_enabled_federated' => isset($_POST['transactions_enabled_federated']),
            'appear_in_federated_search' => isset($_POST['appear_in_federated_search']),
            'show_skills_federated' => isset($_POST['show_skills_federated']),
            'show_location_federated' => isset($_POST['show_location_federated']),
            'service_reach' => $_POST['service_reach'] ?? 'local_only',
            'travel_radius_km' => $_POST['travel_radius_km'] ?? null,
        ];

        $result = \Nexus\Services\FederationUserService::updateSettings($userId, $settings);

        if ($result) {
            header('Location: ' . TenantContext::getBasePath() . '/settings?section=federation&success=federation_updated');
        } else {
            header('Location: ' . TenantContext::getBasePath() . '/settings?section=federation&error=update_failed');
        }
        exit;
    }

    /**
     * Quick opt-out from federation (AJAX)
     */
    public function federationOptOut()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $result = \Nexus\Services\FederationUserService::optOut($_SESSION['user_id']);

        echo json_encode(['success' => $result]);
        exit;
    }
}
