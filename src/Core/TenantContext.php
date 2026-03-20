<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

/**
 * Thin delegate — forwards all calls to \App\Core\TenantContext which
 * holds the real 7-strategy tenant resolution implementation.
 *
 * This class is kept for backward compatibility: 633+ files across the
 * legacy Nexus\ namespace reference it. The public API is identical.
 *
 * @see \App\Core\TenantContext  The authoritative implementation.
 */
class TenantContext
{
    /**
     * Resolve the current tenant (domain, header, slug, token, path, session, fallback).
     */
    public static function resolve()
    {
        \App\Core\TenantContext::resolve();
    }

    /**
     * Get the current tenant array (auto-resolves if not yet set).
     *
     * @return array
     */
    public static function get()
    {
        return \App\Core\TenantContext::get();
    }

    /**
     * Get the current tenant ID.
     *
     * @return int
     */
    public static function getId()
    {
        return \App\Core\TenantContext::getId();
    }

    /**
     * Set tenant context by ID (for cron jobs, admin areas, etc.)
     *
     * @param int $tenantId
     * @return bool True if tenant was found and set, false otherwise
     */
    public static function setById($tenantId): bool
    {
        return \App\Core\TenantContext::setById($tenantId);
    }

    /**
     * Get the URL base path (e.g. "/hour-timebank" or "").
     *
     * @return string
     */
    public static function getBasePath()
    {
        return \App\Core\TenantContext::getBasePath();
    }

    /**
     * Get the tenant slug prefix for building frontend URLs.
     *
     * @return string e.g. "/hour-timebank" or "" for master tenant
     */
    public static function getSlugPrefix(): string
    {
        return \App\Core\TenantContext::getSlugPrefix();
    }

    /**
     * Get a setting from tenant configuration (supports dot notation).
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getSetting(string $key, $default = null)
    {
        return \App\Core\TenantContext::getSetting($key, $default);
    }

    /**
     * Get the frontend URL for user-facing links.
     *
     * @return string
     */
    public static function getFrontendUrl(): string
    {
        return \App\Core\TenantContext::getFrontendUrl();
    }

    /**
     * Check whether a feature is enabled for the current tenant.
     *
     * @param string $feature
     * @return bool
     */
    public static function hasFeature($feature)
    {
        return \App\Core\TenantContext::hasFeature($feature);
    }

    /**
     * Get the full domain URL for the current tenant.
     *
     * @return string
     */
    public static function getDomain()
    {
        return \App\Core\TenantContext::getDomain();
    }

    /**
     * Get list of custom pages for the current tenant.
     *
     * @param string|null $layout
     * @return array
     */
    public static function getCustomPages($layout = null)
    {
        return \App\Core\TenantContext::getCustomPages($layout);
    }

    /**
     * Get the tenant ID from the X-Tenant-ID header (if provided).
     *
     * @return int|null
     */
    public static function getHeaderTenantId(): ?int
    {
        return \App\Core\TenantContext::getHeaderTenantId();
    }

    /**
     * Get the tenant ID from the Bearer token (if provided).
     *
     * @return int|null
     */
    public static function getTokenTenantId(): ?int
    {
        return \App\Core\TenantContext::getTokenTenantId();
    }
}
