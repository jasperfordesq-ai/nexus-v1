<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Share Target Controller
 * Handles content shared from other apps via the Web Share Target API
 *
 * When users share content TO this app from another app (like a link from
 * their browser, or an image from their gallery), this controller processes it.
 */
class ShareTargetController
{
    /**
     * POST /share-target
     * Receives shared content from other apps
     */
    public function receive()
    {
        // Must be logged in to share content
        if (!isset($_SESSION['user_id'])) {
            // Store shared data temporarily and redirect to login
            $_SESSION['pending_share'] = [
                'title' => $_POST['title'] ?? '',
                'text' => $_POST['text'] ?? '',
                'url' => $_POST['url'] ?? '',
                'has_media' => !empty($_FILES['media'])
            ];
            header('Location: /login?redirect=/share-target/pending');
            exit;
        }

        $title = trim($_POST['title'] ?? '');
        $text = trim($_POST['text'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $media = $_FILES['media'] ?? null;

        // Determine what type of content was shared and route appropriately
        $shareData = [
            'title' => $title,
            'text' => $text,
            'url' => $url,
            'combined' => $this->combineSharedText($title, $text, $url),
            'media' => null
        ];

        // Handle media upload if present
        if ($media && $media['error'] === UPLOAD_ERR_OK) {
            $shareData['media'] = $this->handleMediaUpload($media);
        }

        // Store in session for the compose page
        $_SESSION['share_data'] = $shareData;

        // Redirect to compose page with share context
        header('Location: /share-target/compose');
        exit;
    }

    /**
     * GET /share-target/compose
     * Shows options for what to do with shared content
     */
    public function compose()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $shareData = $_SESSION['share_data'] ?? null;

        if (!$shareData) {
            // No pending share, redirect to home
            header('Location: /');
            exit;
        }

        \Nexus\Core\SEO::setTitle('Share Content');

        \Nexus\Core\View::render('share/compose', [
            'shareData' => $shareData
        ]);
    }

    /**
     * GET /share-target/pending
     * Handle pending share after login
     */
    public function pending()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $pendingShare = $_SESSION['pending_share'] ?? null;

        if ($pendingShare) {
            // Convert pending share to active share data
            $_SESSION['share_data'] = [
                'title' => $pendingShare['title'],
                'text' => $pendingShare['text'],
                'url' => $pendingShare['url'],
                'combined' => $this->combineSharedText(
                    $pendingShare['title'],
                    $pendingShare['text'],
                    $pendingShare['url']
                ),
                'media' => null
            ];
            unset($_SESSION['pending_share']);
        }

        header('Location: /share-target/compose');
        exit;
    }

    /**
     * POST /share-target/create
     * Create content from shared data
     */
    public function create()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $shareData = $_SESSION['share_data'] ?? null;
        $action = $_POST['action'] ?? '';

        if (!$shareData) {
            header('Location: /');
            exit;
        }

        switch ($action) {
            case 'post':
                // Create a feed post
                $this->createPost($shareData);
                break;

            case 'message':
                // Start a new message with shared content
                $this->createMessage($shareData);
                break;

            case 'listing':
                // Create a new listing
                $this->createListing($shareData);
                break;

            default:
                $_SESSION['flash_error'] = 'Invalid action';
                header('Location: /share-target/compose');
                exit;
        }
    }

    /**
     * Create a feed post from shared content
     */
    private function createPost($shareData)
    {
        $db = Database::getConnection();
        $userId = $_SESSION['user_id'];
        $tenantId = TenantContext::getId();

        $content = $shareData['combined'];

        // Add media if present
        $mediaPath = $shareData['media'];

        $stmt = $db->prepare("
            INSERT INTO posts (user_id, tenant_id, content, image, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $tenantId, $content, $mediaPath]);

        // Clear share data
        unset($_SESSION['share_data']);

        $_SESSION['flash_success'] = 'Post created successfully!';
        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/');
        exit;
    }

    /**
     * Redirect to messages with pre-filled content
     */
    private function createMessage($shareData)
    {
        // Store message content for the messages page
        $_SESSION['prefill_message'] = $shareData['combined'];

        // Clear share data
        unset($_SESSION['share_data']);

        header('Location: /messages/new');
        exit;
    }

    /**
     * Redirect to listing creation with pre-filled content
     */
    private function createListing($shareData)
    {
        // Store listing content
        $_SESSION['prefill_listing'] = [
            'title' => $shareData['title'] ?: 'Shared Item',
            'description' => $shareData['combined']
        ];

        // Clear share data
        unset($_SESSION['share_data']);

        header('Location: /listings/create');
        exit;
    }

    /**
     * Combine shared text elements into a single string
     */
    private function combineSharedText($title, $text, $url)
    {
        $parts = [];

        if ($title) {
            $parts[] = $title;
        }

        if ($text) {
            $parts[] = $text;
        }

        if ($url) {
            $parts[] = $url;
        }

        return implode("\n\n", $parts);
    }

    /**
     * Handle media file upload
     */
    private function handleMediaUpload($file)
    {
        // SECURITY: Validate actual MIME type using finfo, not user-supplied type
        $allowedTypes = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            'video/mp4' => ['mp4'],
            'video/webm' => ['webm']
        ];

        // Check actual file MIME type (not user-supplied header)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $actualMime = $finfo->file($file['tmp_name']);

        if (!isset($allowedTypes[$actualMime])) {
            return null;
        }

        // Validate extension matches MIME type
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedTypes[$actualMime])) {
            // Use first valid extension for detected MIME type
            $ext = $allowedTypes[$actualMime][0];
        }

        // Max 10MB
        if ($file['size'] > 10 * 1024 * 1024) {
            return null;
        }

        $uploadDir = __DIR__ . '/../../httpdocs/uploads/shares/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // SECURITY: Use cryptographically secure random filename
        $filename = 'share_' . bin2hex(random_bytes(16)) . '.' . $ext;
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return '/uploads/shares/' . $filename;
        }

        return null;
    }
}
