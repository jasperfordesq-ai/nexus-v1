<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * VolunteerCertificateService — Laravel DI wrapper for legacy \Nexus\Services\VolunteerCertificateService.
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
    public function getErrors(): array
    {
        return \Nexus\Services\VolunteerCertificateService::getErrors();
    }

    /**
     * Delegates to legacy VolunteerCertificateService::generate().
     */
    public function generate(int $userId, array $options = []): ?array
    {
        return \Nexus\Services\VolunteerCertificateService::generate($userId, $options);
    }

    /**
     * Delegates to legacy VolunteerCertificateService::verify().
     */
    public function verify(string $code): ?array
    {
        return \Nexus\Services\VolunteerCertificateService::verify($code);
    }

    /**
     * Delegates to legacy VolunteerCertificateService::getUserCertificates().
     */
    public function getUserCertificates(int $userId): array
    {
        return \Nexus\Services\VolunteerCertificateService::getUserCertificates($userId);
    }

    /**
     * Delegates to legacy VolunteerCertificateService::generateHtml().
     */
    public function generateHtml(string $code): ?string
    {
        return \Nexus\Services\VolunteerCertificateService::generateHtml($code);
    }

    /**
     * Delegates to legacy VolunteerCertificateService::markDownloaded().
     */
    public function markDownloaded(string $code): void
    {
        \Nexus\Services\VolunteerCertificateService::markDownloaded($code);
    }
}
