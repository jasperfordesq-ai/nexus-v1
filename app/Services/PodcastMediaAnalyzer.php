<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Pure-PHP audio metadata analysis for podcast uploads, backed by getID3.
 *
 * The upload path only trusts the client-supplied MIME type; this analyzer
 * inspects the actual bytes, so a renamed non-audio file (or a corrupted
 * upload) is caught during media processing instead of being served forever.
 */
class PodcastMediaAnalyzer
{
    /**
     * getID3 file formats that count as genuine audio for podcast purposes.
     * WebM/Matroska appears as 'matroska' with an audio stream.
     */
    private const AUDIO_FORMATS = ['mp3', 'mp2', 'mp1', 'aac', 'mp4', 'quicktime', 'ogg', 'vorbis', 'opus', 'flac', 'wav', 'riff', 'matroska', 'webm'];

    /**
     * @return array{is_audio: bool, duration_seconds: ?int, format: ?string, mime: ?string}
     */
    public static function analyze(string $absolutePath): array
    {
        $result = ['is_audio' => false, 'duration_seconds' => null, 'format' => null, 'mime' => null];

        if (!is_file($absolutePath)) {
            return $result;
        }

        try {
            $engine = new \getID3();
            $info = $engine->analyze($absolutePath);
        } catch (\Throwable $e) {
            Log::warning('PodcastMediaAnalyzer: getID3 analysis failed', [
                'error' => $e->getMessage(),
            ]);

            return $result;
        }

        $format = strtolower((string) ($info['fileformat'] ?? ''));
        $result['format'] = $format !== '' ? $format : null;
        $result['mime'] = isset($info['mime_type']) ? (string) $info['mime_type'] : null;

        // Genuine audio = a recognised container with a decoded audio stream.
        $hasAudioStream = isset($info['audio']) && is_array($info['audio']) && !empty($info['audio']);
        $result['is_audio'] = $hasAudioStream && in_array($format, self::AUDIO_FORMATS, true);

        $playtime = $info['playtime_seconds'] ?? null;
        if (is_numeric($playtime) && (float) $playtime > 0) {
            $result['duration_seconds'] = (int) round((float) $playtime);
        }

        return $result;
    }
}
