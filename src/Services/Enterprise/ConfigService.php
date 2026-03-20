<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Enterprise;

/**
 * ConfigService — Thin delegate forwarding to \App\Services\Enterprise\ConfigService.
 *
 * The full implementation now lives in the App namespace.
 * This file exists for backwards compatibility only.
 *
 * @see \App\Services\Enterprise\ConfigService
 */
class ConfigService
{

    public static function getInstance(): \App\Services\Enterprise\ConfigService
    {
        return \App\Services\Enterprise\ConfigService::getInstance();
    }

    public function isUsingVault(): bool
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->isUsingVault();
    }

    public function getDatabase(): array
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->getDatabase();
    }

    public function getRedis(): array
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->getRedis();
    }

    public function getPusher(): array
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->getPusher();
    }

    public function getOpenAI(): array
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->getOpenAI();
    }

    public function getAnthropic(): array
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->getAnthropic();
    }

    public function getGoogleMaps(): array
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->getGoogleMaps();
    }

    public function getFirebase(): array
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->getFirebase();
    }

    public function getSmtp(): array
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->getSmtp();
    }

    public function getAppKey(): string
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->getAppKey();
    }

    public function get(string $path, $key = null, $default = null)
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->get($path, $key, $default);
    }

    public function getRequired(string $key): string
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->getRequired($key);
    }

    public function getEnvironment(): string
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->getEnvironment();
    }

    public function getInt(string $key, int $default = 0): int
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->getInt($key, $default);
    }

    public function getBool(string $key, bool $default = false): bool
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->getBool($key, $default);
    }

    public function getArray(string $key, array $default = []): array
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->getArray($key, $default);
    }

    public function getAll(array $keys): array
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->getAll($keys);
    }

    public function validate(array $requiredKeys): array
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->validate($requiredKeys);
    }

    public function getEnv(string $key, $default = null)
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->getEnv($key, $default);
    }

    public function isProduction(): bool
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->isProduction();
    }

    public function isDebug(): bool
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->isDebug();
    }

    public function clearCache(): void
    {
        \App\Services\Enterprise\ConfigService::getInstance()->clearCache();
    }

    public function getStatus(): array
    {
        return \App\Services\Enterprise\ConfigService::getInstance()->getStatus();
    }
}
