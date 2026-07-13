<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

/**
 * Audio file upload handler for voice messaging.
 */
class AudioUploader
{
    private static $allowedTypes = [
        'audio/webm',
        'video/webm', // Chrome records audio-only WebM as video/webm (finfo detection)
        'audio/ogg',
        'audio/mpeg',
        'audio/mp3',
        'audio/wav',
        'audio/x-wav',
        'audio/mp4',
        'audio/x-m4a',          // finfo detection for M4A-branded MPEG-4 audio (mobile recordings)
        'video/mp4',            // Android MediaRecorder writes isom/mp42-branded MPEG-4 — finfo reports video/mp4 even for audio-only
        'audio/aac',
        'audio/x-hx-aac-adts',  // finfo detection for raw AAC (ADTS) streams
    ];

    private static $maxSize = 10 * 1024 * 1024; // 10MB max for voice messages
    private static $maxDuration = 300; // 5 minutes max

    /**
     * Upload an audio file for voice messaging.
     *
     * @param array $file $_FILES['audio'] array
     * @param int $duration Duration in seconds (from frontend)
     * @return array{url: string, duration: int, local_path: string}
     * @throws \Exception On upload error or validation failure
     */
    public static function upload($file, int $duration = 0): array
    {
        if (empty($file['tmp_name'])) {
            throw new \Exception("No audio file provided");
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception("Upload error code: " . $file['error']);
        }

        // Size validation
        if ($file['size'] > self::$maxSize) {
            throw new \Exception("Audio file too large. Maximum 10MB allowed.");
        }

        // Duration validation
        if ($duration > self::$maxDuration) {
            throw new \Exception("Voice message too long. Maximum 5 minutes allowed.");
        }

        // MIME type validation using file content
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($file['tmp_name']);

        if (!in_array($detectedMime, self::$allowedTypes)) {
            throw new \Exception("Invalid audio format (detected: {$detectedMime}). Supported: WebM, OGG, MP3, WAV, M4A, AAC");
        }

        // Determine extension from MIME
        $extension = self::getExtensionFromMime($detectedMime);

        // Generate secure filename
        $filename = 'voice_' . bin2hex(random_bytes(16)) . '.' . $extension;

        // Tenant-scoped directory
        $tenantId = TenantContext::getId();
        // nosemgrep: tainted-filename — tenant is an int and filename is generated from 128 random bits
        $targetDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . (int)$tenantId . '/voice_messages';

        if (!is_dir($targetDir)) { // nosemgrep: tainted-filename
            mkdir($targetDir, 0755, true);
        }

        $targetPath = $targetDir . '/' . $filename;
        $publicPath = '/uploads/' . (int)$tenantId . '/voice_messages/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new \Exception("Failed to save audio file");
        }

        return [
            'url' => $publicPath,
            'duration' => max(1, $duration),
            // Internal filesystem identity for immediate server-side work such
            // as transcription. Controllers must never expose this value.
            'local_path' => $targetPath,
        ];
    }

    /**
     * Upload from base64 encoded audio data (for blob recordings).
     *
     * @param string $base64Data Base64 encoded audio
     * @param string $mimeType MIME type of the audio
     * @param int $duration Duration in seconds
     * @return array{url: string, duration: int, local_path: string}
     * @throws \Exception On upload error or validation failure
     */
    public static function uploadFromBase64(string $base64Data, string $mimeType, int $duration = 0): array
    {
        // Normalize MIME type (strip codec info like "audio/webm;codecs=opus")
        $baseMime = explode(';', $mimeType)[0];

        // Validate MIME type
        if (!in_array($baseMime, self::$allowedTypes)) {
            throw new \Exception("Invalid audio format: {$baseMime}");
        }

        $mimeType = $baseMime;

        // Decode base64 (strict mode to reject malformed input)
        $audioData = base64_decode($base64Data, true);
        if ($audioData === false) {
            throw new \Exception("Invalid base64 audio data");
        }

        // Size validation
        if (strlen($audioData) > self::$maxSize) {
            throw new \Exception("Audio file too large. Maximum 10MB allowed.");
        }

        // Verify actual MIME type of decoded content matches claimed type
        $tmpFile = tempnam(sys_get_temp_dir(), 'audio_verify_');
        try {
            file_put_contents($tmpFile, $audioData);
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $actualMime = $finfo->file($tmpFile);
            if (!in_array($actualMime, self::$allowedTypes)) {
                throw new \Exception("Audio content does not match allowed types. Detected: {$actualMime}");
            }
        } finally {
            @unlink($tmpFile);
        }

        // Duration validation
        if ($duration > self::$maxDuration) {
            throw new \Exception("Voice message too long. Maximum 5 minutes allowed.");
        }

        // Generate filename
        $extension = self::getExtensionFromMime($mimeType);
        $filename = 'voice_' . bin2hex(random_bytes(16)) . '.' . $extension;

        // Tenant-scoped directory
        $tenantId = TenantContext::getId();
        // nosemgrep: tainted-filename — tenant is an int and filename is generated from 128 random bits
        $targetDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . (int)$tenantId . '/voice_messages';

        if (!is_dir($targetDir)) { // nosemgrep: tainted-filename
            mkdir($targetDir, 0755, true);
        }

        $targetPath = $targetDir . '/' . $filename;
        $publicPath = '/uploads/' . (int)$tenantId . '/voice_messages/' . $filename;

        // Save file
        if (file_put_contents($targetPath, $audioData) === false) { // nosemgrep: tainted-filename
            throw new \Exception("Failed to save audio file");
        }

        return [
            'url' => $publicPath,
            'duration' => max(1, $duration),
            'local_path' => $targetPath,
        ];
    }

    /**
     * Delete a voice message file.
     */
    public static function delete(string $url): bool
    {
        if (trim($url) === '') {
            return false;
        }

        // File deletion must never resolve a fallback/default tenant. Upload
        // cleanup only runs inside an already-resolved request context.
        $tenantId = TenantContext::currentId();
        if ($tenantId === null) {
            return false;
        }

        return self::deleteForTenant($url, $tenantId);
    }

    /**
     * Delete a local voice recording only when it belongs to the specified
     * tenant's canonical voice-message directory.
     *
     * This explicit-tenant variant is used by background/GDPR work where the
     * ambient request tenant may not be available. Invalid, remote, missing,
     * traversal, symlink-escape, and cross-tenant pointers all fail closed.
     */
    public static function deleteForTenant(string $url, int $tenantId): bool
    {
        $path = self::resolveTenantVoiceFilePath($url, $tenantId);

        return $path !== null && @unlink($path);
    }

    /**
     * Confirm that a server-issued voice URL resolves to an existing recording
     * inside the specified tenant's canonical voice-message directory.
     */
    public static function isTenantVoiceFile(string $url, int $tenantId): bool
    {
        return self::resolveTenantVoiceFilePath($url, $tenantId) !== null;
    }

    /**
     * Resolve a voice URL to its canonical local path for safe identity
     * comparisons. No path is returned unless the existing file is contained
     * by the specified tenant's voice-message directory.
     */
    public static function resolveTenantVoiceFilePath(string $url, int $tenantId): ?string
    {
        $url = trim($url);
        if ($url === '' || $tenantId <= 0 || str_contains($url, "\0") || str_contains($url, '\\')) {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            return null;
        }

        $path = rawurldecode((string) ($parts['path'] ?? ''));
        if (str_contains($path, "\0") || str_contains($path, '\\')) {
            return null;
        }
        $prefix = "/uploads/{$tenantId}/voice_messages/";
        if (!str_starts_with($path, $prefix)) {
            return null;
        }

        $filename = substr($path, strlen($prefix));
        // Voice URLs are server-issued with a `voice_` prefix and MIME-derived
        // extension. The bounded portable basename alphabet keeps historical
        // server-issued variants valid while preventing wrappers and traversal
        // from reaching any filesystem operation.
        if (preg_match('/\Avoice_[A-Za-z0-9][A-Za-z0-9._-]{0,127}\.(?:webm|ogg|mp3|wav|m4a|aac)\z/D', $filename) !== 1) {
            return null;
        }

        $configuredRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        if ($configuredRoot === '') {
            $configuredRoot = function_exists('public_path') ? rtrim(public_path(), '/\\') : '';
        }
        $documentRoot = $configuredRoot !== '' ? realpath($configuredRoot) : false;
        if ($documentRoot === false) {
            return null;
        }

        $voiceRoot = $documentRoot
            . DIRECTORY_SEPARATOR . 'uploads'
            . DIRECTORY_SEPARATOR . $tenantId
            . DIRECTORY_SEPARATOR . 'voice_messages';
        $resolvedRoot = realpath($voiceRoot);
        if ($resolvedRoot === false) {
            return null;
        }

        $resolved = realpath($voiceRoot . DIRECTORY_SEPARATOR . $filename);
        $rootPrefix = rtrim($resolvedRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($resolved === false || !str_starts_with($resolved, $rootPrefix)) {
            return null;
        }

        // nosemgrep: tainted-filename -- $resolved is canonical, its source
        // basename passed the exact server-issued allowlist above, and the
        // preceding prefix check proves containment inside $resolvedRoot.
        return is_file($resolved) ? $resolved : null;
    }

    /**
     * Get file extension from MIME type.
     */
    private static function getExtensionFromMime(string $mime): string
    {
        $map = [
            'audio/webm' => 'webm',
            'video/webm' => 'webm',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp3' => 'mp3',
            'audio/wav' => 'wav',
            'audio/x-wav' => 'wav',
            'audio/mp4' => 'm4a',
            'audio/x-m4a' => 'm4a',
            'video/mp4' => 'm4a',
            'audio/aac' => 'aac',
            'audio/x-hx-aac-adts' => 'aac',
        ];

        return $map[$mime] ?? 'webm';
    }
}
