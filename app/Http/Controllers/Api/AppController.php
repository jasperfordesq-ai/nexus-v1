<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * AppController -- Mobile app version check and logging.
 *
 * Native implementation (no delegation).
 */
class AppController extends BaseApiController
{
    protected bool $isV2Api = true;

    // Current app version - UPDATE THIS when releasing a new APK
    // Must match versionName in capacitor/android/app/build.gradle
    private const CURRENT_VERSION = '1.1';

    // Minimum required version - users below this MUST update
    private const MIN_REQUIRED_VERSION = '1.0';

    // Update URL for APK download
    private const UPDATE_URL = 'https://api.project-nexus.ie/downloads/nexus-latest.apk';

    // What's new in the latest version
    private const RELEASE_NOTES = [
        '1.0' => [
            'Initial release',
            'Persistent login (stay logged in for 1 year)',
            'Offline support',
            'Push notifications',
        ],
        '1.1' => [
            'New React frontend with HeroUI components',
            'Content moderation system (feed, comments, reviews, reports)',
            'Super admin panel with tenant management',
            'Updated to app.project-nexus.ie domain',
            'Bug fixes and performance improvements',
        ],
    ];

    /**
     * POST /api/v2/app/check-version
     *
     * Check app version and return update status.
     * Body: { "version": "1.0.0", "platform": "android" }
     */
    public function checkVersion(): JsonResponse
    {
        $this->rateLimit('app_check_version', 30, 60);

        $clientVersion = $this->input('version', '0.0.0');
        $platform = $this->input('platform', 'android');

        $needsUpdate = version_compare($clientVersion, self::CURRENT_VERSION, '<');
        $forceUpdate = version_compare($clientVersion, self::MIN_REQUIRED_VERSION, '<');

        // Get release notes for versions newer than client
        $releaseNotes = [];
        foreach (self::RELEASE_NOTES as $version => $notes) {
            if (version_compare($version, $clientVersion, '>')) {
                $releaseNotes[$version] = $notes;
            }
        }

        $response = [
            'success' => true,
            'current_version' => self::CURRENT_VERSION,
            'min_required_version' => self::MIN_REQUIRED_VERSION,
            'client_version' => $clientVersion,
            'update_available' => $needsUpdate,
            'force_update' => $forceUpdate,
            'update_url' => self::UPDATE_URL,
            'release_notes' => $releaseNotes,
        ];

        // Add platform-specific info
        if ($platform === 'android') {
            $response['update_url'] = self::UPDATE_URL;
            $response['update_message'] = $forceUpdate
                ? 'A critical update is required. Please update to continue using the app.'
                : 'A new version is available with improvements and bug fixes.';
        }

        return response()->json($response);
    }

    /**
     * GET /api/v2/app/version
     *
     * Get current app version info (public endpoint).
     */
    public function version(): JsonResponse
    {
        return response()->json([
            'version' => self::CURRENT_VERSION,
            'min_version' => self::MIN_REQUIRED_VERSION,
            'update_url' => self::UPDATE_URL,
            'release_notes' => self::RELEASE_NOTES[self::CURRENT_VERSION] ?? [],
        ]);
    }

    /**
     * POST /api/v2/app/log
     *
     * Log app events (crashes, errors, analytics).
     * Body: { "event": "...", "version": "...", "platform": "...", "data": {...} }
     */
    public function log(): JsonResponse
    {
        $this->rateLimit('app_log', 30, 60);

        $event = $this->input('event', 'unknown');
        $version = $this->input('version', 'unknown');
        $platform = $this->input('platform', 'unknown');
        $data = $this->input('data', []);

        // Sanitize event name to prevent log injection
        $event = preg_replace('/[^a-zA-Z0-9_.-]/', '', substr($event, 0, 64));
        $version = preg_replace('/[^a-zA-Z0-9_.-]/', '', substr($version, 0, 20));
        $platform = preg_replace('/[^a-zA-Z0-9_.-]/', '', substr($platform, 0, 20));

        error_log(sprintf(
            "[APP LOG] Event: %s | Version: %s | Platform: %s | Data: %s",
            $event,
            $version,
            $platform,
            json_encode($data)
        ));

        return response()->json(['success' => true]);
    }
}
