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
class GroupPolicyRepository
{
    const CATEGORY_CREATION = 'creation';
    const CATEGORY_MEMBERSHIP = 'membership';
    const CATEGORY_CONTENT = 'content';
    const CATEGORY_MODERATION = 'moderation';
    const CATEGORY_NOTIFICATIONS = 'notifications';
    const CATEGORY_FEATURES = 'features';

    const TYPE_BOOLEAN = 'boolean';
    const TYPE_NUMBER = 'number';
    const TYPE_STRING = 'string';
    const TYPE_JSON = 'json';
    const TYPE_LIST = 'list';

    public function __construct()
    {
    }

    /**
     * Delegates to legacy GroupPolicyRepository::setPolicy().
     */
    public function setPolicy($key, $value, $category = self::CATEGORY_FEATURES, $type = self::TYPE_STRING, $description = null, $tenantId = null)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupPolicyRepository::getPolicy().
     */
    public function getPolicy($key, $default = null, $tenantId = null)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupPolicyRepository::getPoliciesByCategory().
     */
    public function getPoliciesByCategory($category, $tenantId = null)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupPolicyRepository::getAllPolicies().
     */
    public function getAllPolicies($tenantId = null)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupPolicyRepository::deletePolicy().
     */
    public function deletePolicy($key, $tenantId = null)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }
}
