<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

/**
 * App Controller - Mobile app utilities
 * Handles version checking, update prompts, and app-specific endpoints
 */
class AppController extends BaseApiController
{
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
            'Push notifications'
        ],
        '1.1' => [
            'New React frontend with HeroUI components',
            'Content moderation system (feed, comments, reviews, reports)',
            'Super admin panel with tenant management',
            'Updated to app.project-nexus.ie domain',
            'Bug fixes and performance improvements'
        ]
    ];


    /**
     * Check app version and return update status
     * POST /api/app/check-version
     * Body: { "version": "1.0.0", "platform": "android" }
     */
    public function checkVersion()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $clientVersion = $input['version'] ?? '0.0.0';
        $platform = $input['platform'] ?? 'android';

        // Compare versions
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
            'release_notes' => $releaseNotes
        ];

        // Add platform-specific info
        if ($platform === 'android') {
            $response['update_url'] = self::UPDATE_URL;
            $response['update_message'] = $forceUpdate
                ? 'A critical update is required. Please update to continue using the app.'
                : 'A new version is available with improvements and bug fixes.';
        }

        $this->jsonResponse($response);
    }

    /**
     * Get current app version info (public endpoint)
     * GET /api/app/version
     */
    public function version()
    {
        $this->jsonResponse([
            'version' => self::CURRENT_VERSION,
            'min_version' => self::MIN_REQUIRED_VERSION,
            'update_url' => self::UPDATE_URL,
            'release_notes' => self::RELEASE_NOTES[self::CURRENT_VERSION] ?? []
        ]);
    }

    /**
     * Log app events (crashes, errors, analytics)
     * POST /api/app/log
     */
    public function log()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        $event = $input['event'] ?? 'unknown';
        $version = $input['version'] ?? 'unknown';
        $platform = $input['platform'] ?? 'unknown';
        $data = $input['data'] ?? [];

        // Log to error log for now (could be extended to database)
        error_log(sprintf(
            "[APP LOG] Event: %s | Version: %s | Platform: %s | Data: %s",
            $event,
            $version,
            $platform,
            json_encode($data)
        ));

        $this->jsonResponse(['success' => true]);
    }
}
