<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Core\AudioUploader;
use App\Core\EmailTemplate;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

/**
 * VoiceMessageController -- Voice message recording and storage.
 *
 * Native Laravel implementation using request()->file() for audio uploads.
 */
class VoiceMessageController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /**
     * POST /api/v2/voice-messages
     *
     * Upload and send a voice message. Accepts either a file upload (field: 'audio')
     * or base64-encoded audio data (field: 'audio_data' + 'mime_type').
     * Form field: 'receiver_id' (required), 'duration' (optional).
     */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('voice_message_store', 10, 60);

        $senderId = $userId;
        $receiverId = (int) request()->input('receiver_id', 0);
        $duration = (int) request()->input('duration', 0);

        if (!$receiverId) {
            return $this->respondWithError('VALIDATION_ERROR', 'Receiver ID is required', 'receiver_id', 400);
        }

        try {
            $audioResult = null;

            $file = request()->file('audio');
            if ($file && $file->isValid()) {
                // Standard file upload — build $_FILES-compatible array for AudioUploader
                $fileArray = [
                    'name'     => $file->getClientOriginalName(),
                    'type'     => $file->getMimeType(),
                    'tmp_name' => $file->getRealPath(),
                    'error'    => UPLOAD_ERR_OK,
                    'size'     => $file->getSize(),
                ];
                $audioResult = AudioUploader::upload($fileArray, $duration);
            } elseif (request()->input('audio_data')) {
                // Base64 encoded audio (from MediaRecorder blob)
                $mimeType = request()->input('mime_type', 'audio/webm');
                $audioResult = AudioUploader::uploadFromBase64(request()->input('audio_data'), $mimeType, $duration);
            } else {
                return $this->respondWithError('VALIDATION_ERROR', 'No audio data provided', 'audio', 400);
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
            $sender = DB::table('users')->where('id', $senderId)->where('tenant_id', \App\Core\TenantContext::getId())->select('name')->first();
            $receiver = DB::table('users')->where('id', $receiverId)->where('tenant_id', \App\Core\TenantContext::getId())->select('name', 'email')->first();

            if ($receiver && $receiver->email) {
                $replyLink = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . "/messages/" . $senderId;

                $durationFormatted = gmdate("i:s", $audioResult['duration']);
                $emailHtml = EmailTemplate::render(
                    "New Voice Message",
                    "You have received a voice message from " . htmlspecialchars($sender->name ?? 'Someone'),
                    "Duration: {$durationFormatted}<br><br>Click the button below to listen to the message.",
                    "Listen to Voice Message",
                    $replyLink,
                    $tenant['name']
                );

                try {
                    $mailer = new Mailer();
                    $mailer->send($receiver->email, "Voice Message from " . ($sender->name ?? 'Someone'), $emailHtml);
                } catch (\Throwable $e) {
                    error_log("Voice Message Email Notification Failed: " . $e->getMessage());
                }
            }

            return $this->respondWithData([
                'success'    => true,
                'message_id' => $messageId,
                'audio_url'  => $audioResult['url'],
                'duration'   => $audioResult['duration'],
            ], null, 201);
        } catch (\Exception $e) {
            return $this->respondWithError('UPLOAD_FAILED', $e->getMessage(), 'audio', 400);
        }
    }
}
