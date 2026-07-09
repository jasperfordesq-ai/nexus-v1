// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

function readSource(relativePath: string): string {
  return readFileSync(resolve(root, relativePath), 'utf8');
}

describe('HeroUI visual contract smoke guard', () => {
  it.each([
    'components/auth/OAuthButtons.tsx',
    'components/auth/SsoButtons.tsx',
    'components/feedback/ErrorBoundary.tsx',
    'components/feedback/FeatureErrorBoundary.tsx',
    'components/feedback/LoadingScreen.tsx',
  ])('keeps %s on HeroUI-backed primitives instead of native fallbacks', (relativePath) => {
    const source = readSource(relativePath);

    expect(source).not.toMatch(/<button\b/);
    expect(source).toMatch(/@\/components\/ui\/(?:Button|Card|GlassCard|Skeleton|Spinner)/);
  });

  it('keeps the auth shell on shared polished controls', () => {
    const source = readSource('components/layout/AuthLayout.tsx');

    expect(source).toContain('@/components/LanguageSwitcher');
    expect(source).toContain('./SourceRepositoryLink');
    expect(source).not.toMatch(/<select\b/);
    expect(source).not.toMatch(/<option\b/);
  });

  it('keeps the admin shell visibly opaque and HeroUI-card backed', () => {
    const adminHeader = readSource('admin/components/AdminHeader.tsx');
    const pageHeader = readSource('admin/components/PageHeader.tsx');

    expect(adminHeader).toContain('bg-[var(--surface-solid)]');
    expect(adminHeader).toContain('@/components/ui/Button');
    expect(pageHeader).toContain('@/components/ui/Card');
    expect(pageHeader).toContain('<Card');
  });

  it('keeps panel Tailwind utilities in the main stylesheet only', () => {
    const indexCss = readSource('index.css');
    const routeEntrypoints = [
      'admin/AdminApp.tsx',
      'broker/BrokerApp.tsx',
      'caring/CaringApp.tsx',
      'partners/PartnersApp.tsx',
      'super-admin/SuperAdminApp.tsx',
    ];
    const retiredRouteSheets = [
      'admin/admin.css',
      'broker/broker.css',
      'caring/caring.css',
      'partners/partners.css',
      'super-admin/super-admin.css',
    ];

    expect(indexCss).toContain('@source "./**/*.{js,ts,jsx,tsx}";');
    expect(indexCss).not.toMatch(/@source\s+not\s+["']\.\/(?:admin|broker|caring|partners|super-admin)\//);

    for (const relativePath of routeEntrypoints) {
      expect(readSource(relativePath)).not.toMatch(/import\s+['"]\.\/[^'"]+\.css['"]/);
    }

    for (const relativePath of retiredRouteSheets) {
      expect(existsSync(resolve(root, relativePath))).toBe(false);
    }
  });
});
