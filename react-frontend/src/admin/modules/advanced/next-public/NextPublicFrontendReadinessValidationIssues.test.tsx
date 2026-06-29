// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

vi.mock('@/hooks/usePageTitle', () => ({ usePageTitle: vi.fn() }));

vi.mock('@/admin/api/adminApi', () => ({
  adminSettings: {
    getNextPublicFrontendReadiness: vi.fn(),
  },
}));

import { adminSettings } from '@/admin/api/adminApi';
import NextPublicFrontendReadiness from './NextPublicFrontendReadiness';

const readinessWithValidationIssue = {
  mode: 'shadow',
  app: {
    exists: true,
    package_name: 'nexus-next-public-frontend',
    version: '1.5.4',
    next_version: '16.2.9',
    react_version: '19.2.7',
    lockfile_exists: true,
    package_scripts: {
      dev: true,
      build: true,
      start: true,
      test: true,
      check_manifests: true,
      check_no_js_html: true,
    },
  },
  manifest: {
    exists: true,
    mode: 'shadow',
    route_counts: {
      public_routes: 1,
      api_backed_public_routes: 0,
      vite_private_prefixes: 1,
      vite_private_patterns: 0,
    },
    validation: {
      status: 'blocker',
      issues: [
        {
          code: 'content_sources_not_laravel_api',
          context: 'next_database',
          severity: 'blocker',
        },
        {
          code: 'vite_private_prefix_missing_required',
          context: 'login',
          severity: 'blocker',
        },
      ],
    },
    public_routes: [
      { pattern: '/', routeKey: 'home', labelKey: 'pages.home.title' },
    ],
    vite_private_prefixes: ['dashboard'],
    vite_private_patterns: [],
    route_readiness: [],
  },
  content_sources: {
    manifest_exists: true,
    manifest_path: 'next-public-frontend/content-sources.json',
    source_of_truth: 'next_database',
    database_queries_from_next: true,
    api_backed_routes: [],
  },
  production_routing: {
    active: false,
    route_cutover_enabled: false,
    edge_routes_configured: false,
  },
  prerender: {
    status: 'unchanged',
    fallback_retained: true,
  },
  shadow_runtime: {
    compose_profile: 'next-public-shadow',
    dev_command: 'npm run dev:next-public',
    build_command: 'npm run build:next-public',
    container_port: 3000,
    host_port_env: 'NEXUS_NEXT_PUBLIC_PORT',
    port_env: 'NEXUS_NEXT_PUBLIC_PORT',
    default_shadow_port: 3200,
    compose_profile_configured: true,
    verification_commands: [],
  },
  safety_checks: [
    { key: 'route_cutover_disabled', status: 'pass' },
  ],
  cutover_step_keys: ['verify_next_shadow_build'],
};

describe('NextPublicFrontendReadiness validation issues', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders translated labels for content-source validation blockers', async () => {
    vi.mocked(adminSettings.getNextPublicFrontendReadiness).mockResolvedValue({
      success: true,
      data: readinessWithValidationIssue,
    });

    render(<NextPublicFrontendReadiness />);

    await waitFor(() => {
      expect(screen.getByText('Manifest validation blocked')).toBeInTheDocument();
    });

    expect(screen.getByText('Content sources must use Laravel public APIs.')).toBeInTheDocument();
    expect(screen.getByText('Required Vite private route prefix is missing.')).toBeInTheDocument();
    expect(screen.getAllByText('next_database')).not.toHaveLength(0);
    expect(screen.getAllByText('login')).not.toHaveLength(0);
  });
});
