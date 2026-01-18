<?php

namespace Nexus\Controllers;

use Nexus\Models\User;
use Nexus\Core\View;
use Nexus\Core\Csrf;
use Nexus\Core\TenantContext;

class OnboardingController
{
    private function checkAccess()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }
    }

    public function index()
    {
        $this->checkAccess();
        $user = User::findById($_SESSION['user_id']);
        View::render('onboarding/index', ['user' => $user]);
    }

    public function store()
    {
        Csrf::verifyOrDie();
        $this->checkAccess();

        $userId = $_SESSION['user_id'];
        $user = User::findById($userId);

        // Get bio from form
        $bio = $_POST['bio'] ?? '';

        // Preserve existing location and phone
        $location = $user['location'] ?? '';
        $phone = $user['phone'] ?? '';

        // Update profile
        User::updateProfile(
            $userId,
            $user['first_name'],
            $user['last_name'],
            $bio,
            $location,
            $phone
        );

        // Handle Avatar Upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['avatar']['tmp_name'];
            $fileName = $_FILES['avatar']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Security: Validate file extension
            $allowedExtensions = ['jpg', 'gif', 'png', 'jpeg', 'webp'];
            if (in_array($fileExtension, $allowedExtensions)) {
                // Security: Validate MIME type from file content
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $detectedMime = $finfo->file($fileTmpPath);
                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

                if (in_array($detectedMime, $allowedMimes)) {
                    // Security: Verify it's actually an image
                    $imageInfo = @getimagesize($fileTmpPath);

                    if ($imageInfo !== false) {
                        // Upload is valid - process it
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
                }
            }
            // If validation fails, silently skip (like ProfileController does)
        }

        // Set default avatar if user still doesn't have one
        $updatedUser = User::findById($userId);
        if (empty($updatedUser['avatar_url'])) {
            User::updateAvatar($userId, '/assets/img/defaults/default_avatar.png');
        }

        // Redirect to Dashboard
        header('Location: ' . TenantContext::getBasePath() . '/dashboard?msg=welcome_aboard');
        exit;
    }
}
