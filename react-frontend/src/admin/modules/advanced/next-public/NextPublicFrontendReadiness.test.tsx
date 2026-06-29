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

const readiness = {
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
    },
  },
  manifest: {
    exists: true,
    mode: 'shadow',
    route_counts: {
      public_routes: 3,
      api_backed_public_routes: 2,
      vite_private_prefixes: 3,
      vite_private_patterns: 2,
    },
    validation: {
      status: 'pass',
      issues: [],
    },
    public_routes: [
      { pattern: '/', routeKey: 'home', labelKey: 'pages.home.title' },
      { pattern: '/about', routeKey: 'about', labelKey: 'pages.about.title' },
      { pattern: '/blog/:slug', routeKey: 'blog-detail', labelKey: 'pages.blogDetail.title' },
    ],
    vite_private_prefixes: ['admin', 'dashboard', 'messages'],
    vite_private_patterns: ['/events/new', '/events/:id/edit'],
  },
  content_sources: {
    manifest_exists: true,
    manifest_path: 'next-public-frontend/content-sources.json',
    source_of_truth: 'laravel_public_api',
    database_queries_from_next: false,
    api_backed_routes: [
      { routeKey: 'blog-index', endpoint: '/v2/blog', method: 'GET' },
      { routeKey: 'listingDetail', endpoint: '/v2/listings/{id}', method: 'GET' },
    ],
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
  },
  safety_checks: [
    { key: 'route_cutover_disabled', status: 'pass' },
    { key: 'parity_tests_required_before_cutover', status: 'blocker' },
  ],
  cutover_step_keys: ['verify_next_shadow_build', 'enable_canary_for_public_routes_only'],
};

describe('NextPublicFrontendReadiness', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders shadow mode and confirms production routing is off', async () => {
    vi.mocked(adminSettings.getNextPublicFrontendReadiness).mockResolvedValue({
      success: true,
      data: readiness,
    });

    render(<NextPublicFrontendReadiness />);

    await waitFor(() => {
      expect(screen.getByText('Shadow mode')).toBeInTheDocument();
    });

    expect(screen.getByText('Public traffic is not served by Next.js')).toBeInTheDocument();
    expect(screen.getByText('Prerender fallback retained')).toBeInTheDocument();
    expect(screen.getByText('/about')).toBeInTheDocument();
    expect(screen.getByText('/blog/:slug')).toBeInTheDocument();
    expect(screen.getByText('dashboard')).toBeInTheDocument();
    expect(screen.getByText('laravel_public_api')).toBeInTheDocument();
    expect(screen.getByText('GET /v2/listings/{id}')).toBeInTheDocument();
    expect(screen.getByText('npm run build:next-public')).toBeInTheDocument();
    expect(screen.getByText('Manifest validation passed')).toBeInTheDocument();
    expect(screen.getByText('3 public routes')).toBeInTheDocument();
    expect(screen.getByText('3 private prefixes')).toBeInTheDocument();
  });

  it('shows an error state when readiness cannot load', async () => {
    vi.mocked(adminSettings.getNextPublicFrontendReadiness).mockRejectedValue(new Error('Nope'));

    render(<NextPublicFrontendReadiness />);

    await waitFor(() => {
      expect(screen.getByText('Next.js readiness could not be loaded.')).toBeInTheDocument();
    });
  });
});
