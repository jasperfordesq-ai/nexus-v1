<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\MemberPremiumService;
use Tests\Laravel\TestCase;

class MemberPremiumServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::setById($this->testTenantId);
    }

    public function test_safe_return_url_rejects_external_and_script_urls(): void
    {
        $fallback = $this->tenantUrl('/premium/return');

        $this->assertSame($fallback, MemberPremiumService::safeReturnUrl('https://evil.example/phish', '/premium/return'));
        $this->assertSame($fallback, MemberPremiumService::safeReturnUrl('//evil.example/phish', '/premium/return'));
        $this->assertSame($fallback, MemberPremiumService::safeReturnUrl('javascript:alert(1)', '/premium/return'));
    }

    public function test_safe_return_url_allows_current_tenant_absolute_and_relative_urls(): void
    {
        $sameTenant = $this->tenantUrl('/premium/manage?tab=billing');

        $this->assertSame($sameTenant, MemberPremiumService::safeReturnUrl($sameTenant, '/premium/return'));
        $this->assertSame($sameTenant, MemberPremiumService::safeReturnUrl('/premium/manage?tab=billing', '/premium/return'));
    }

    public function test_safe_return_url_rejects_other_tenant_paths_on_shared_frontend_host(): void
    {
        $slugPrefix = TenantContext::getSlugPrefix();
        if ($slugPrefix === '') {
            $this->markTestSkipped('Current test tenant uses a custom domain or master route without a slug prefix.');
        }

        $base = rtrim(TenantContext::getFrontendUrl(), '/');

        $this->assertSame(
            $this->tenantUrl('/premium/return'),
            MemberPremiumService::safeReturnUrl($base . '/other-tenant/premium/return', '/premium/return')
        );
    }

    private function tenantUrl(string $path): string
    {
        $base = rtrim(TenantContext::getFrontendUrl(), '/');
        $slugPrefix = TenantContext::getSlugPrefix();
        $path = '/' . ltrim($path, '/');

        if ($slugPrefix !== '' && !str_starts_with($path, $slugPrefix . '/') && $path !== $slugPrefix) {
            $path = $slugPrefix . $path;
        }

        return $base . $path;
    }
}
