// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { readFileSync } from 'node:fs';
import path from 'node:path';
import { describe, expect, it } from 'vitest';

const appSource = readFileSync(path.resolve(__dirname, 'routes/AppRoutes.tsx'), 'utf8');
const authRoutesSource = readFileSync(path.resolve(__dirname, 'routes/AuthRoutes.tsx'), 'utf8');
const publicRoutesSource = readFileSync(path.resolve(__dirname, 'routes/PublicAppRoutes.tsx'), 'utf8');
const tenantShellSource = readFileSync(path.resolve(__dirname, 'components/routing/TenantShell.tsx'), 'utf8');
const protectedRoutesStart = appSource.indexOf('{/* Protected Routes */}');
const protectedRoutesEnd = appSource.indexOf('{/* Admin Panel', protectedRoutesStart);
const protectedRoutesSource = appSource.slice(protectedRoutesStart, protectedRoutesEnd);

describe('App route feature gates', () => {
  it('gates matches routes behind the listings module', () => {
    expect(appSource).toMatch(/<Route path="matches" element=\{\s*<FeatureGate module="listings" redirect="\/dashboard">[\s\S]*?<MatchesPage \/>[\s\S]*?<\/FeatureGate>/);
    expect(appSource).toMatch(/<Route path="matches\/preferences" element=\{\s*<FeatureGate module="listings" redirect="\/dashboard">[\s\S]*?<MatchPreferencesPage \/>[\s\S]*?<\/FeatureGate>/);
  });

  it('gates reviews routes behind the reviews feature', () => {
    expect(appSource).toMatch(/<Route path="reviews" element=\{\s*<FeatureGate feature="reviews" redirect="\/dashboard">[\s\S]*?<ReviewsPage \/>[\s\S]*?<\/FeatureGate>/);
    expect(appSource).toMatch(/<Route path="reviews\/create" element=\{\s*<FeatureGate feature="reviews" redirect="\/dashboard">[\s\S]*?<ReviewsPage \/>[\s\S]*?<\/FeatureGate>/);
  });

  it('keeps auth-entry routes out of the full app route registry', () => {
    expect(appSource).not.toContain('@/components/layout/AuthLayout');
    expect(appSource).not.toMatch(/@\/pages\/auth\/(?:LoginPage|RegisterPage|ForgotPasswordPage|ResetPasswordPage|VerifyEmailPage|VerifyIdentityPage|OauthCallbackPage)/);

    expect(authRoutesSource).toContain('@/components/layout/AuthLayout');
    expect(authRoutesSource).toContain("@/pages/auth/LoginPage");
    expect(authRoutesSource).toContain("@/pages/auth/RegisterPage");
  });

  it('keeps app-only runtime providers out of the auth startup shell', () => {
    expect(tenantShellSource).not.toMatch(/from ['"]@\/contexts\/(?:NotificationsContext|PusherContext|MenuContext|PresenceContext|PodcastPlayerContext)['"]/);
    expect(tenantShellSource).not.toContain('@/components/security/IdleLogoutGuard');
    expect(tenantShellSource).not.toContain('appRoutesModulePromise');
    expect(tenantShellSource).toContain("import('@/routes/AuthRoutes')");
    expect(tenantShellSource).toContain("import('@/routes/PublicAppRoutes')");
    expect(tenantShellSource).toContain("import('@/routes/AppRoutes')");
    expect(tenantShellSource).toContain("lazy(() => import('./TenantAppProviders'))");
  });

  it('keeps protected/admin route declarations out of the public route registry', () => {
    expect(publicRoutesSource).not.toContain('@/admin/AdminApp');
    expect(publicRoutesSource).not.toContain('@/super-admin/SuperAdminApp');
    expect(publicRoutesSource).not.toContain('@/components/routing/ProtectedRoute');
    expect(publicRoutesSource).not.toContain('@/pages/marketplace/CreateMarketplaceListingPage');
    expect(publicRoutesSource).not.toContain('@/pages/courses/CoursePlayerPage');
  });

  it('excludes protected subroutes that sit under public-looking prefixes', () => {
    expect(tenantShellSource).toContain("'podcasts/studio'");
    expect(tenantShellSource).toContain("'marketplace/orders'");
    expect(tenantShellSource).toContain("'marketplace/sell'");
    expect(tenantShellSource).toContain("'marketplace/seller/coupons'");
    expect(tenantShellSource).toContain('^courses\\/[^/]+\\/learn$');
    expect(tenantShellSource).toContain('^jobs\\/[^/]+\\/kanban$');
    expect(tenantShellSource).toContain('^marketplace\\/[^/]+\\/edit$');
  });

  it('keeps seller coupon and listing-edit routes inside the authenticated route tree', () => {
    const protectedSellerRoutes = [
      'marketplace/seller/coupons',
      'marketplace/seller/coupons/new',
      'marketplace/seller/coupons/:id/edit',
      'marketplace/:id/edit',
    ];

    expect(protectedRoutesStart).toBeGreaterThan(-1);
    expect(protectedRoutesEnd).toBeGreaterThan(protectedRoutesStart);

    for (const routePath of protectedSellerRoutes) {
      const declaration = `path="${routePath}"`;
      expect(protectedRoutesSource).toContain(declaration);
      expect(appSource.split(declaration)).toHaveLength(2);
      expect(publicRoutesSource).not.toContain(declaration);
    }

    expect(protectedRoutesSource).toMatch(/path="marketplace\/seller\/coupons"[\s\S]*?<FeatureGate feature="merchant_coupons"/);
    expect(protectedRoutesSource).toMatch(/path="marketplace\/seller\/coupons\/new"[\s\S]*?<FeatureGate feature="merchant_coupons"/);
    expect(protectedRoutesSource).toMatch(/path="marketplace\/seller\/coupons\/:id\/edit"[\s\S]*?<FeatureGate feature="merchant_coupons"/);
    expect(protectedRoutesSource).toMatch(/path="marketplace\/:id\/edit"[\s\S]*?<FeatureGate feature="marketplace"/);
  });

  it('keeps public marketplace profile/list routes in the public route registry', () => {
    expect(publicRoutesSource).toContain('path="marketplace/seller/:id"');
    expect(publicRoutesSource).toContain('path="marketplace/my-listings"');
    expect(publicRoutesSource).toContain('path="marketplace/my-offers"');
  });
});
