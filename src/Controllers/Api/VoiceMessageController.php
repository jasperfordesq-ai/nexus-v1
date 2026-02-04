<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\AudioUploader;
use Nexus\Core\Csrf;
use Nexus\Core\Database;
use Nexus\Core\EmailTemplate;
use Nexus\Core\Mailer;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiAuth;
use Nexus\Models\Message;

/**
 * VoiceMessageController - Handles voice message uploads and sending
 */
class VoiceMessageController
{
    use ApiAuth;

    /**
     * Upload and send a voice message
     * POST /api/messages/voice
     */
    public function store()
    {
        header('Content-Type: application/json');

        // Auth check - supports both session and Bearer token
        $userId = $this->getAuthenticatedUserId();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        // CSRF check - automatically skipped for Bearer token auth
        Csrf::verifyOrDieJson();

        try {
            $senderId = $userId;
            $receiverId = (int)($_POST['receiver_id'] ?? 0);
            $duration = (int)($_POST['duration'] ?? 0);

            if (!$receiverId) {
                throw new \Exception('Receiver ID is required');
            }

            // Handle file upload or base64 data
            $audioResult = null;

            if (!empty($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
                // Standard file upload
                $audioResult = AudioUploader::upload($_FILES['audio'], $duration);
            } elseif (!empty($_POST['audio_data'])) {
                // Base64 encoded audio (from MediaRecorder blob)
                $mimeType = $_POST['mime_type'] ?? 'audio/webm';
                $audioResult = AudioUploader::uploadFromBase64($_POST['audio_data'], $mimeType, $duration);
            } else {
                throw new \Exception('No audio data provided');
            }

            // Create voice message in database
            $tenant = TenantContext::get();
            $tenantId = $tenant['id'];
            $messageId = Message::createVoice(
                $tenantId,
                $senderId,
                $receiverId,
                $audioResult['url'],
                $audioResult['duration']
            );

            // Send email notification for voice message
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$senderId]);
            $sender = $stmt->fetch();

            $stmt = $db->prepare("SELECT name, email FROM users WHERE id = ?");
            $stmt->execute([$receiverId]);
            $receiver = $stmt->fetch();

            if ($receiver && $receiver['email']) {
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                $replyLink = $protocol . "://" . $_SERVER['HTTP_HOST'] . TenantContext::getBasePath() . "/messages/" . $senderId;

                $durationFormatted = gmdate("i:s", $audioResult['duration']);
                $emailHtml = EmailTemplate::render(
                    "New Voice Message",
                    "You have received a voice message from " . htmlspecialchars($sender['name']),
                    "Duration: {$durationFormatted}<br><br>Click the button below to listen to the message.",
                    "Listen to Voice Message",
                    $replyLink,
                    $tenant['name']
                );

                try {
                    $mailer = new Mailer();
                    $mailer->send($receiver['email'], "Voice Message from " . $sender['name'], $emailHtml);
                } catch (\Throwable $e) {
                    error_log("Voice Message Email Notification Failed: " . $e->getMessage());
                }
            }

            echo json_encode([
                'success' => true,
                'message_id' => $messageId,
                'audio_url' => $audioResult['url'],
                'duration' => $audioResult['duration'],
            ]);

        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }

        exit;
    }
}
