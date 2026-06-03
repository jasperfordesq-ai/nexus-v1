<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Typed tenant configuration for the Podcasts module.
 */
class PodcastConfigurationService
{
    public const CONFIG_ALLOW_MEMBER_SHOW_CREATION = 'podcasts.allow_member_show_creation';
    public const CONFIG_MODERATION_ENABLED = 'podcasts.moderation_enabled';
    public const CONFIG_ENABLE_RSS_FEED = 'podcasts.enable_rss_feed';
    public const CONFIG_ENABLE_PRIVATE_SHOWS = 'podcasts.enable_private_shows';
    public const CONFIG_ENABLE_TRANSCRIPTS = 'podcasts.enable_transcripts';
    public const CONFIG_ENABLE_CHAPTERS = 'podcasts.enable_chapters';
    public const CONFIG_ENABLE_EPISODE_REACTIONS = 'podcasts.enable_episode_reactions';
    public const CONFIG_ENABLE_LISTEN_ANALYTICS = 'podcasts.enable_listen_analytics';
    public const CONFIG_MAX_SHOWS_PER_USER = 'podcasts.max_shows_per_user';
    public const CONFIG_MAX_AUDIO_SIZE_MB = 'podcasts.max_audio_size_mb';
    public const CONFIG_MEDIA_STORAGE_DRIVER = 'podcasts.media_storage_driver';
    public const CONFIG_CLOUD_STORAGE_DISK = 'podcasts.cloud_storage_disk';
    public const CONFIG_CLOUD_CDN_BASE_URL = 'podcasts.cloud_cdn_base_url';
    public const CONFIG_ENABLE_MEDIA_SCANNING = 'podcasts.enable_media_scanning';
    public const CONFIG_ENABLE_MEDIA_PROCESSING = 'podcasts.enable_media_processing';

    public const DEFAULTS = [
        self::CONFIG_ALLOW_MEMBER_SHOW_CREATION => true,
        self::CONFIG_MODERATION_ENABLED => false,
        self::CONFIG_ENABLE_RSS_FEED => true,
        self::CONFIG_ENABLE_PRIVATE_SHOWS => true,
        self::CONFIG_ENABLE_TRANSCRIPTS => true,
        self::CONFIG_ENABLE_CHAPTERS => true,
        self::CONFIG_ENABLE_EPISODE_REACTIONS => true,
        self::CONFIG_ENABLE_LISTEN_ANALYTICS => true,
        self::CONFIG_MAX_SHOWS_PER_USER => 5,
        self::CONFIG_MAX_AUDIO_SIZE_MB => 250,
        self::CONFIG_MEDIA_STORAGE_DRIVER => 'local',
        self::CONFIG_CLOUD_STORAGE_DISK => 's3',
        self::CONFIG_CLOUD_CDN_BASE_URL => '',
        self::CONFIG_ENABLE_MEDIA_SCANNING => true,
        self::CONFIG_ENABLE_MEDIA_PROCESSING => true,
    ];

    private const CACHE_TTL = 300;

    public static function get(string $key, mixed $default = null): mixed
    {
        $tenantId = TenantContext::getId();
        $stored = self::getStoredValues($tenantId);

        if (array_key_exists($key, $stored)) {
            return $stored[$key];
        }

        return $default ?? self::DEFAULTS[$key] ?? null;
    }

    public static function set(string $key, mixed $value): void
    {
        $tenantId = TenantContext::getId();
        $storedValue = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        $settingType = self::detectType($value);

        try {
            DB::table('tenant_settings')->updateOrInsert(
                ['tenant_id' => $tenantId, 'setting_key' => $key],
                [
                    'setting_value' => $storedValue,
                    'setting_type' => $settingType,
                    'category' => 'podcasts',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            Cache::forget("podcast_config:{$tenantId}");
        } catch (\Throwable $e) {
            Log::error('PodcastConfigurationService::set failed', [
                'key' => $key,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public static function getAll(): array
    {
        return array_merge(self::DEFAULTS, self::getStoredValues(TenantContext::getId()));
    }

    private static function getStoredValues(int $tenantId): array
    {
        return Cache::remember("podcast_config:{$tenantId}", self::CACHE_TTL, function () use ($tenantId) {
            try {
                $rows = DB::table('tenant_settings')
                    ->where('tenant_id', $tenantId)
                    ->where('setting_key', 'LIKE', 'podcasts.%')
                    ->select('setting_key', 'setting_value', 'setting_type')
                    ->get();

                $result = [];
                foreach ($rows as $row) {
                    $result[$row->setting_key] = self::decodeValue((string) $row->setting_value, $row->setting_type ?? 'string');
                }

                return $result;
            } catch (\Throwable $e) {
                Log::error('PodcastConfigurationService::getStoredValues failed', [
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    private static function decodeValue(string $storedValue, string $type): mixed
    {
        return match ($type) {
            'boolean' => in_array(strtolower($storedValue), ['true', '1', 'yes'], true),
            'integer' => (int) $storedValue,
            'float' => (float) $storedValue,
            default => $storedValue,
        };
    }

    private static function detectType(mixed $value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'float';
        }

        return 'string';
    }
}
