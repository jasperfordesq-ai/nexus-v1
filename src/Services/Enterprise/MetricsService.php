<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Enterprise;

/**
 * MetricsService — Thin delegate forwarding to \App\Services\Enterprise\MetricsService.
 *
 * The full implementation now lives in the App namespace.
 * This file exists for backwards compatibility only.
 *
 * @see \App\Services\Enterprise\MetricsService
 */
class MetricsService
{
    public const STATUS_OK = \App\Services\Enterprise\MetricsService::STATUS_OK;
    public const STATUS_WARNING = \App\Services\Enterprise\MetricsService::STATUS_WARNING;
    public const STATUS_CRITICAL = \App\Services\Enterprise\MetricsService::STATUS_CRITICAL;
    public const STATUS_UNKNOWN = \App\Services\Enterprise\MetricsService::STATUS_UNKNOWN;

    public static function getInstance(): \App\Services\Enterprise\MetricsService
    {
        return \App\Services\Enterprise\MetricsService::getInstance();
    }

    public function send(string $message): void
    {
        \App\Services\Enterprise\MetricsService::getInstance()->send($message);
    }

    public function isEnabled(): bool
    {
        return \App\Services\Enterprise\MetricsService::getInstance()->isEnabled();
    }

    public function increment(string $metric, int|array $value = 1, array $tags = [], float $sampleRate = 1.0): void
    {
        \App\Services\Enterprise\MetricsService::getInstance()->increment($metric, $value, $tags, $sampleRate);
    }

    public function decrement(string $metric, int|array $value = 1, array $tags = []): void
    {
        \App\Services\Enterprise\MetricsService::getInstance()->decrement($metric, $value, $tags);
    }

    public function gauge(string $metric, float $value, array $tags = []): void
    {
        \App\Services\Enterprise\MetricsService::getInstance()->gauge($metric, $value, $tags);
    }

    public function histogram(string $metric, float $value, array $tags = []): void
    {
        \App\Services\Enterprise\MetricsService::getInstance()->histogram($metric, $value, $tags);
    }

    public function distribution(string $metric, float $value, array $tags = []): void
    {
        \App\Services\Enterprise\MetricsService::getInstance()->distribution($metric, $value, $tags);
    }

    public function timing(string $metric, float $milliseconds, array $tags = []): void
    {
        \App\Services\Enterprise\MetricsService::getInstance()->timing($metric, $milliseconds, $tags);
    }

    public function time(string $metric, callable $callback, array $tags = [])
    {
        return \App\Services\Enterprise\MetricsService::getInstance()->time($metric, $callback, $tags);
    }

    public function set(string $metric, $value, array $tags = []): void
    {
        \App\Services\Enterprise\MetricsService::getInstance()->set($metric, $value, $tags);
    }

    public function serviceCheck(string $name, int $status, string|array $options = []): void
    {
        \App\Services\Enterprise\MetricsService::getInstance()->serviceCheck($name, $status, $options);
    }

    public function event(string $title, string $text, array $options = []): void
    {
        \App\Services\Enterprise\MetricsService::getInstance()->event($title, $text, $options);
    }

    public function business(string $metric, float $value, array $tags = []): void
    {
        \App\Services\Enterprise\MetricsService::getInstance()->business($metric, $value, $tags);
    }

    public function flush(): void
    {
        \App\Services\Enterprise\MetricsService::getInstance()->flush();
    }

    public function addGlobalTag(string $key, string $value): void
    {
        \App\Services\Enterprise\MetricsService::getInstance()->addGlobalTag($key, $value);
    }
}
