<?php

// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

/**
 * App-namespace wrapper for Nexus\Core\TenantContext.
 * Delegates all calls to the legacy class during migration.
 */
class TenantContext
{
    public static function resolve(): void
    {
        \Nexus\Core\TenantContext::resolve();
    }

    public static function get()
    {
        return \Nexus\Core\TenantContext::get();
    }

    public static function getId()
    {
        return \Nexus\Core\TenantContext::getId();
    }

    public static function setById($tenantId): bool
    {
        return \Nexus\Core\TenantContext::setById($tenantId);
    }

    public static function getBasePath()
    {
        return \Nexus\Core\TenantContext::getBasePath();
    }

    public static function getSlugPrefix(): string
    {
        return \Nexus\Core\TenantContext::getSlugPrefix();
    }

    public static function getSetting(string $key, $default = null)
    {
        return \Nexus\Core\TenantContext::getSetting($key, $default);
    }

    public static function getFrontendUrl(): string
    {
        return \Nexus\Core\TenantContext::getFrontendUrl();
    }

    public static function hasFeature($feature)
    {
        return \Nexus\Core\TenantContext::hasFeature($feature);
    }

    public static function getDomain()
    {
        return \Nexus\Core\TenantContext::getDomain();
    }

    public static function getCustomPages($layout = null)
    {
        return \Nexus\Core\TenantContext::getCustomPages($layout);
    }

    public static function getHeaderTenantId(): ?int
    {
        return \Nexus\Core\TenantContext::getHeaderTenantId();
    }

    public static function getTokenTenantId(): ?int
    {
        return \Nexus\Core\TenantContext::getTokenTenantId();
    }
}
