<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * SchemaService — Laravel DI wrapper for legacy \Nexus\Services\SchemaService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class SchemaService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy SchemaService::organization().
     */
    public function organization(?array $tenant = null, ?array $config = null): array
    {
        return \Nexus\Services\SchemaService::organization($tenant, $config);
    }

    /**
     * Delegates to legacy SchemaService::webSite().
     */
    public function webSite(?array $tenant = null): array
    {
        return \Nexus\Services\SchemaService::webSite($tenant);
    }

    /**
     * Delegates to legacy SchemaService::article().
     */
    public function article(array $post, ?array $author = null): array
    {
        return \Nexus\Services\SchemaService::article($post, $author);
    }

    /**
     * Delegates to legacy SchemaService::event().
     */
    public function event(array $event, ?array $organizer = null): array
    {
        return \Nexus\Services\SchemaService::event($event, $organizer);
    }

    /**
     * Delegates to legacy SchemaService::offer().
     */
    public function offer(array $listing, ?array $seller = null): array
    {
        return \Nexus\Services\SchemaService::offer($listing, $seller);
    }
}
