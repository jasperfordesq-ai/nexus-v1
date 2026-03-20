<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

/**
 * Thin delegate — forwards every call to App\Core\AudioUploader.
 *
 * @deprecated Use App\Core\AudioUploader directly. Kept for backward compatibility.
 */
class AudioUploader
{
    /**
     * Upload an audio file for voice messaging.
     *
     * @param array $file $_FILES['audio'] or raw audio data
     * @param int $duration Duration in seconds (from frontend)
     * @return array ['url' => '/uploads/...', 'duration' => 30]
     */
    public static function upload($file, int $duration = 0): array
    {
        return \App\Core\AudioUploader::upload($file, $duration);
    }

    /**
     * Upload from base64 encoded audio data (for blob recordings).
     *
     * @param string $base64Data Base64 encoded audio
     * @param string $mimeType MIME type of the audio
     * @param int $duration Duration in seconds
     * @return array ['url' => '/uploads/...', 'duration' => 30]
     */
    public static function uploadFromBase64(string $base64Data, string $mimeType, int $duration = 0): array
    {
        return \App\Core\AudioUploader::uploadFromBase64($base64Data, $mimeType, $duration);
    }

    /**
     * Delete a voice message file.
     */
    public static function delete(string $url): bool
    {
        return \App\Core\AudioUploader::delete($url);
    }
}
