<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use Nexus\Core\AudioUploader as LegacyAudioUploader;

/**
 * App-namespace wrapper for Nexus\Core\AudioUploader.
 *
 * Delegates to the legacy implementation. Once the Laravel migration is
 * complete this can be replaced with Laravel's Storage facade.
 */
class AudioUploader
{
    /**
     * Upload an audio file for voice messaging.
     *
     * @param array $file $_FILES['audio'] array
     * @param int $duration Duration in seconds (from frontend)
     * @return array ['url' => '/uploads/...', 'duration' => 30]
     * @throws \Exception On upload error or validation failure
     */
    public static function upload($file, int $duration = 0): array
    {
        return LegacyAudioUploader::upload($file, $duration);
    }

    /**
     * Upload from base64 encoded audio data (for blob recordings).
     *
     * @param string $base64Data Base64 encoded audio
     * @param string $mimeType MIME type of the audio
     * @param int $duration Duration in seconds
     * @return array ['url' => '/uploads/...', 'duration' => 30]
     * @throws \Exception On upload error or validation failure
     */
    public static function uploadFromBase64(string $base64Data, string $mimeType, int $duration = 0): array
    {
        return LegacyAudioUploader::uploadFromBase64($base64Data, $mimeType, $duration);
    }

    /**
     * Delete a voice message file.
     */
    public static function delete(string $url): bool
    {
        return LegacyAudioUploader::delete($url);
    }
}
