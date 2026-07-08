// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { readFileSync } from 'node:fs';
import path from 'node:path';
import { describe, expect, it } from 'vitest';

const appSource = readFileSync(path.resolve(__dirname, 'routes/AppRoutes.tsx'), 'utf8');
const authRoutesSource = readFileSync(path.resolve(__dirname, 'routes/AuthRoutes.tsx'), 'utf8');
const tenantShellSource = readFileSync(path.resolve(__dirname, 'components/routing/TenantShell.tsx'), 'utf8');

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
    expect(tenantShellSource).toContain("import('@/routes/AppRoutes')");
    expect(tenantShellSource).toContain("lazy(() => import('./TenantAppProviders'))");
  });
});
