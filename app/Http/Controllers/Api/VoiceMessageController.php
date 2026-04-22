<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Core\AudioUploader;
use App\Core\EmailTemplate;
use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\I18n\LocaleContext;
use App\Models\Message;
use App\Services\TranscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
            return $this->respondWithError('VALIDATION_ERROR', __('api.missing_required_field', ['field' => 'receiver_id']), 'receiver_id', 400);
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
                return $this->respondWithError('VALIDATION_ERROR', __('api.no_audio_data_provided'), 'audio', 400);
            }

            // Create voice message via MessageService::send() (consistent with MessagesController)
            $tenant = TenantContext::get();
            $tenantId = $tenant['id'];
            $messageData = \App\Services\MessageService::send($senderId, [
                'recipient_id'   => $receiverId,
                'body'           => '',
                'is_voice'       => true,
                'audio_url'      => $audioResult['url'],
                'audio_duration' => $audioResult['duration'],
            ]);

            if (empty($messageData)) {
                $errors = \App\Services\MessageService::getErrors();
                return $this->respondWithErrors($errors, 422);
            }

            $messageId = $messageData['id'] ?? null;

            // Transcribe the audio file (non-blocking — failures are logged, not thrown)
            $transcript = null;
            $transcriptLanguage = null;
            try {
                // Resolve the audio file path for transcription
                $audioPath = $audioResult['local_path'] ?? null;
                if (!$audioPath && isset($audioResult['url'])) {
                    // If only URL available, download to temp file for transcription.
                    // SSRF HARDENING: the URL comes from AudioUploader (our own storage),
                    // but we still defensively validate before fetching.
                    $audioPath = $this->safeDownloadAudio((string) $audioResult['url']);
                }

                if ($audioPath && file_exists($audioPath)) {
                    $transcription = TranscriptionService::transcribe($audioPath);
                    if ($transcription && !empty($transcription['text'])) {
                        $transcript = $transcription['text'];
                        $transcriptLanguage = $transcription['language'] ?? 'en';

                        DB::table('messages')
                            ->where('id', $messageId)
                            ->where('tenant_id', $tenantId)
                            ->update([
                                'transcript'          => $transcript,
                                'transcript_language' => $transcriptLanguage,
                            ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Voice message transcription failed', [
                    'message_id' => $messageId,
                    'error'      => $e->getMessage(),
                ]);
            }

            // Send email notification for voice message
            $sender = DB::table('users')->where('id', $senderId)->where('tenant_id', \App\Core\TenantContext::getId())->select('name')->first();
            $receiver = DB::table('users')->where('id', $receiverId)->where('tenant_id', \App\Core\TenantContext::getId())->select('name', 'email', 'preferred_language')->first();

            if ($receiver && $receiver->email) {
                LocaleContext::withLocale($receiver, function () use ($sender, $receiver, $receiverId, $senderId, $audioResult) {
                    $replyLink = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . "/messages/" . $senderId;

                    $durationFormatted = gmdate("i:s", $audioResult['duration']);
                    $senderName = htmlspecialchars($sender->name ?? __('emails.common.fallback_someone'), ENT_QUOTES, 'UTF-8');

                    $emailHtml = EmailTemplateBuilder::make()
                        ->title(__('emails_misc.voice_message.email_title'))
                        ->greeting(__('emails_misc.voice_message.email_greeting', ['sender' => $senderName]))
                        ->paragraph(__('emails_misc.voice_message.email_body', ['duration' => $durationFormatted]))
                        ->button(__('emails_misc.voice_message.email_cta'), $replyLink)
                        ->render();

                    try {
                        $mailer = Mailer::forCurrentTenant();
                        if (!$mailer->send($receiver->email, __('emails_misc.voice_message.email_subject', ['sender' => $senderName]), $emailHtml)) {
                            Log::warning('[VoiceMessage] Email notification failed to send', ['receiver_id' => $receiverId]);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('[VoiceMessage] Email notification failed: ' . $e->getMessage(), ['receiver_id' => $receiverId]);
                    }
                });
            }

            return $this->respondWithData([
                'success'              => true,
                'message_id'           => $messageId,
                'audio_url'            => $audioResult['url'],
                'duration'             => $audioResult['duration'],
                'transcript'           => $transcript,
                'transcript_language'  => $transcriptLanguage,
            ], null, 201);
        } catch (\Exception $e) {
            Log::error('Voice message store failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->respondWithError('UPLOAD_FAILED', __('api.audio_upload_failed'), 'audio', 400);
        }
    }

    /**
     * Safely download an audio file from a URL with SSRF + size + type guards.
     *
     * Returns the local temp path on success, or null on any failure.
     *
     * Guards:
     *  - https:// only (reject http/file/ftp/data/gopher/etc.)
     *  - reject private/loopback/link-local IPv4 ranges (basic SSRF protection)
     *  - reject if Content-Length exceeds 25 MB (or if streamed body exceeds it)
     *  - reject if Content-Type is not audio/*
     *  - 10s connect+read timeout
     */
    private function safeDownloadAudio(string $url): ?string
    {
        $maxBytes = 25 * 1024 * 1024; // 25 MB

        // 1. Scheme check — https only
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https') {
            Log::warning('safeDownloadAudio: rejecting non-https scheme', ['scheme' => $parts['scheme'] ?? null]);
            return null;
        }
        $host = $parts['host'] ?? '';
        if ($host === '') {
            return null;
        }

        // 2. SSRF — resolve host and reject internal/reserved IP ranges
        $ip = null;
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = $host;
        } else {
            $records = @gethostbynamel($host);
            if (!$records) {
                Log::warning('safeDownloadAudio: DNS lookup failed', ['host' => $host]);
                return null;
            }
            $ip = $records[0];
        }
        if ($this->isPrivateOrReservedIp($ip)) {
            Log::warning('safeDownloadAudio: rejecting private/reserved IP', ['host' => $host, 'ip' => $ip]);
            return null;
        }

        // 3. Fetch with Laravel HTTP client (10s timeout)
        try {
            $response = Http::timeout(10)
                ->withOptions(['max_redirects' => 0]) // avoid redirect-based SSRF
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('safeDownloadAudio: HTTP request failed', ['error' => $e->getMessage()]);
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        // 4. Content-Type check
        $contentType = strtolower((string) $response->header('Content-Type'));
        if (!str_starts_with($contentType, 'audio/')) {
            Log::warning('safeDownloadAudio: rejecting non-audio content type', ['content_type' => $contentType]);
            return null;
        }

        // 5. Size check (header + actual)
        $contentLengthHeader = $response->header('Content-Length');
        if ($contentLengthHeader !== null && $contentLengthHeader !== '' && (int) $contentLengthHeader > $maxBytes) {
            Log::warning('safeDownloadAudio: Content-Length exceeds limit', ['length' => $contentLengthHeader]);
            return null;
        }
        $body = $response->body();
        if (strlen($body) > $maxBytes) {
            Log::warning('safeDownloadAudio: body exceeds limit', ['bytes' => strlen($body)]);
            return null;
        }

        // 6. Write temp file
        $ext = str_contains($contentType, 'webm') ? 'webm'
             : (str_contains($contentType, 'mpeg') ? 'mp3'
             : (str_contains($contentType, 'ogg') ? 'ogg' : 'audio'));
        $path = sys_get_temp_dir() . '/' . uniqid('voice_') . '.' . $ext;
        if (@file_put_contents($path, $body) === false) {
            return null;
        }
        return $path;
    }

    /**
     * Reject private/loopback/link-local/reserved ranges (basic SSRF protection).
     */
    private function isPrivateOrReservedIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }
        // PHP's FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE covers:
        // 10/8, 172.16/12, 192.168/16, 127/8, 169.254/16, and IPv6 equivalents
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }
        return false;
    }
}
