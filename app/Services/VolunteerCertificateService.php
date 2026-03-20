<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class VolunteerCertificateService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy VolunteerCertificateService::getErrors().
     */
    public static function getErrors(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy VolunteerCertificateService::generate().
     */
    public static function generate(int $userId, array $options = []): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy VolunteerCertificateService::verify().
     */
    public static function verify(string $code): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy VolunteerCertificateService::getUserCertificates().
     */
    public static function getUserCertificates(int $userId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy VolunteerCertificateService::generateHtml().
     */
    public static function generateHtml(string $code): ?string
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy VolunteerCertificateService::markDownloaded().
     */
    public static function markDownloaded(string $code): void
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }
}
