<?php

namespace Nexus\Core;

/**
 * AudioUploader - Handles voice message file uploads
 *
 * Validates and stores audio files (WebM, MP3, OGG, WAV) for voice messaging.
 */
class AudioUploader
{
    private static $allowedTypes = [
        'audio/webm',
        'audio/ogg',
        'audio/mpeg',
        'audio/mp3',
        'audio/wav',
        'audio/x-wav',
        'audio/mp4',
        'audio/aac',
    ];

    private static $maxSize = 10 * 1024 * 1024; // 10MB max for voice messages
    private static $maxDuration = 300; // 5 minutes max

    /**
     * Upload an audio file for voice messaging
     *
     * @param array $file $_FILES['audio'] or raw audio data
     * @param int $duration Duration in seconds (from frontend)
     * @return array ['url' => '/uploads/...', 'duration' => 30]
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
            throw new \Exception("Invalid audio format. Supported: WebM, OGG, MP3, WAV, AAC");
        }

        // Determine extension from MIME
        $extension = self::getExtensionFromMime($detectedMime);

        // Generate secure filename
        $filename = uniqid('voice_', true) . '.' . $extension;

        // Tenant-scoped directory
        $tenant = TenantContext::get();
        $slug = $tenant['slug'] ?? 'default';
        if ($tenant['id'] == 1 && empty($tenant['slug'])) {
            $slug = 'master';
        }

        $directory = 'tenants/' . $slug . '/voice_messages';
        $targetDir = __DIR__ . '/../../httpdocs/uploads/' . $directory;

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetPath = $targetDir . '/' . $filename;
        $publicPath = '/uploads/' . $directory . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new \Exception("Failed to save audio file");
        }

        return [
            'url' => $publicPath,
            'duration' => max(1, $duration),
        ];
    }

    /**
     * Upload from base64 encoded audio data (for blob recordings)
     *
     * @param string $base64Data Base64 encoded audio
     * @param string $mimeType MIME type of the audio
     * @param int $duration Duration in seconds
     * @return array ['url' => '/uploads/...', 'duration' => 30]
     */
    public static function uploadFromBase64(string $base64Data, string $mimeType, int $duration = 0): array
    {
        // Normalize MIME type (strip codec info like "audio/webm;codecs=opus")
        $baseMime = explode(';', $mimeType)[0];

        // Validate MIME type
        if (!in_array($baseMime, self::$allowedTypes)) {
            throw new \Exception("Invalid audio format: {$baseMime}");
        }

        // Use normalized mime for extension lookup
        $mimeType = $baseMime;

        // Decode base64
        $audioData = base64_decode($base64Data);
        if ($audioData === false) {
            throw new \Exception("Invalid base64 audio data");
        }

        // Size validation
        if (strlen($audioData) > self::$maxSize) {
            throw new \Exception("Audio file too large. Maximum 10MB allowed.");
        }

        // Duration validation
        if ($duration > self::$maxDuration) {
            throw new \Exception("Voice message too long. Maximum 5 minutes allowed.");
        }

        // Generate filename
        $extension = self::getExtensionFromMime($mimeType);
        $filename = uniqid('voice_', true) . '.' . $extension;

        // Tenant-scoped directory
        $tenant = TenantContext::get();
        $slug = $tenant['slug'] ?? 'default';
        if ($tenant['id'] == 1 && empty($tenant['slug'])) {
            $slug = 'master';
        }

        $directory = 'tenants/' . $slug . '/voice_messages';
        $targetDir = __DIR__ . '/../../httpdocs/uploads/' . $directory;

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetPath = $targetDir . '/' . $filename;
        $publicPath = '/uploads/' . $directory . '/' . $filename;

        // Save file
        if (file_put_contents($targetPath, $audioData) === false) {
            throw new \Exception("Failed to save audio file");
        }

        return [
            'url' => $publicPath,
            'duration' => max(1, $duration),
        ];
    }

    /**
     * Get file extension from MIME type
     */
    private static function getExtensionFromMime(string $mime): string
    {
        $map = [
            'audio/webm' => 'webm',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp3' => 'mp3',
            'audio/wav' => 'wav',
            'audio/x-wav' => 'wav',
            'audio/mp4' => 'm4a',
            'audio/aac' => 'aac',
        ];

        return $map[$mime] ?? 'webm';
    }

    /**
     * Delete a voice message file
     */
    public static function delete(string $url): bool
    {
        if (empty($url) || strpos($url, '/uploads/') !== 0) {
            return false;
        }

        // Prevent path traversal attacks (e.g., /uploads/../../../etc/passwd)
        $uploadsDir = realpath(__DIR__ . '/../../httpdocs/uploads');
        if (!$uploadsDir) {
            return false;
        }

        $path = realpath(__DIR__ . '/../../httpdocs' . $url);

        // Ensure the resolved path is within the uploads directory
        if (!$path || strpos($path, $uploadsDir) !== 0) {
            return false;
        }

        if (is_file($path)) {
            return unlink($path);
        }

        return false;
    }
}
