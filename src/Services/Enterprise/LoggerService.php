<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Enterprise;

/**
 * LoggerService — Thin delegate forwarding to \App\Services\Enterprise\LoggerService.
 *
 * The full implementation now lives in the App namespace.
 * This file exists for backwards compatibility only.
 *
 * @see \App\Services\Enterprise\LoggerService
 */
class LoggerService
{
    public const EMERGENCY = \App\Services\Enterprise\LoggerService::EMERGENCY;
    public const ALERT = \App\Services\Enterprise\LoggerService::ALERT;
    public const CRITICAL = \App\Services\Enterprise\LoggerService::CRITICAL;
    public const ERROR = \App\Services\Enterprise\LoggerService::ERROR;
    public const WARNING = \App\Services\Enterprise\LoggerService::WARNING;
    public const NOTICE = \App\Services\Enterprise\LoggerService::NOTICE;
    public const INFO = \App\Services\Enterprise\LoggerService::INFO;
    public const DEBUG = \App\Services\Enterprise\LoggerService::DEBUG;

    public static function getInstance(string $channel = 'nexus'): self
    {
        return \App\Services\Enterprise\LoggerService::getInstance($channel);
    }

    public static function channel(string $channel): self
    {
        return \App\Services\Enterprise\LoggerService::channel($channel);
    }

    public function emergency(string $message, array $context = []): void
    {
        \App\Services\Enterprise\LoggerService::getInstance()->emergency($message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        \App\Services\Enterprise\LoggerService::getInstance()->alert($message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        \App\Services\Enterprise\LoggerService::getInstance()->critical($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        \App\Services\Enterprise\LoggerService::getInstance()->error($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        \App\Services\Enterprise\LoggerService::getInstance()->warning($message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        \App\Services\Enterprise\LoggerService::getInstance()->notice($message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        \App\Services\Enterprise\LoggerService::getInstance()->info($message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        \App\Services\Enterprise\LoggerService::getInstance()->debug($message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        \App\Services\Enterprise\LoggerService::getInstance()->log($level, $message, $context);
    }

    public function exception(\Throwable $exception, array $context = []): void
    {
        \App\Services\Enterprise\LoggerService::getInstance()->exception($exception, $context);
    }

    public function withContext(array $context): self
    {
        return \App\Services\Enterprise\LoggerService::getInstance()->withContext($context);
    }

    public function clearContext(): self
    {
        return \App\Services\Enterprise\LoggerService::getInstance()->clearContext();
    }
}
