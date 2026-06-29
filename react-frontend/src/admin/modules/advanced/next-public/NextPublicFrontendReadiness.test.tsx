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
      check_manifests: true,
      check_no_js_html: true,
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
    route_readiness: [
      {
        pattern: '/about',
        routeKey: 'about',
        content_source: 'static_or_tenant_bootstrap',
        status: 'blocker',
        blockers: ['parity_test_required'],
      },
      {
        pattern: '/listings/:id',
        routeKey: 'listingDetail',
        content_source: 'laravel_public_api',
        status: 'blocker',
        blockers: ['parity_test_required'],
      },
    ],
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
  tenant_resolution: {
    status: 'pass',
    bootstrap_endpoint: '/v2/tenant/bootstrap',
    bootstrap_route_status: 'public',
    source_of_truth: 'laravel_tenant_bootstrap',
    shared_host_slug_parameter: 'slug',
    custom_domain_origin_forwarding: true,
    next_queries_database: false,
    examples: [
      {
        key: 'shared_host_slug',
        request_host: 'app.project-nexus.ie',
        request_path: '/{tenantSlug}',
        bootstrap_request: 'GET /v2/tenant/bootstrap?slug={tenantSlug}',
        headers: ['Origin: https://app.project-nexus.ie'],
      },
      {
        key: 'custom_domain',
        request_host: '<custom-domain>',
        request_path: '/',
        bootstrap_request: 'GET /v2/tenant/bootstrap',
        headers: ['Origin: https://<custom-domain>'],
      },
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
    verification_commands: [
      'npm --prefix next-public-frontend run check',
      'npm --prefix react-frontend run build',
      'vendor/bin/phpunit --no-coverage tests/Laravel/Unit/Services/NextPublicFrontendReadinessServiceTest.php tests/Laravel/Feature/Controllers/AdminNextPublicFrontendControllerTest.php',
    ],
  },
  safety_checks: [
    { key: 'route_cutover_disabled', status: 'pass' },
    { key: 'parity_tests_required_before_cutover', status: 'blocker' },
  ],
  cutover_step_keys: ['verify_next_shadow_build', 'enable_canary_for_public_routes_only'],
  cutover_gates: [
    {
      key: 'verify_next_shadow_build',
      status: 'blocker',
      blockers: ['manual_verification_required'],
      verification_commands: ['npm --prefix next-public-frontend run check'],
    },
    {
      key: 'prepare_apache_canary_routes',
      status: 'blocker',
      blockers: ['explicit_cutover_instruction_required', 'edge_routes_not_configured'],
      verification_commands: [],
    },
  ],
  operator_playbook: {
    activation_available: false,
    requires_explicit_cutover_instruction: true,
    no_production_effect: true,
    stages: [
      {
        key: 'verify_shadow_module',
        status: 'blocked',
        commands: ['npm --prefix next-public-frontend run check'],
        notes: ['shadow_only'],
      },
      {
        key: 'prepare_reviewed_edge_config',
        status: 'blocked',
        commands: [],
        notes: ['no_activation_control'],
      },
      {
        key: 'monitor_with_prerender_fallback',
        status: 'blocked',
        commands: [],
        notes: ['do_not_remove_prerender'],
      },
    ],
  },
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
    expect(screen.getAllByText('/about')).not.toHaveLength(0);
    expect(screen.getByText('/blog/:slug')).toBeInTheDocument();
    expect(screen.getByText('dashboard')).toBeInTheDocument();
    expect(screen.getAllByText('laravel_public_api')).not.toHaveLength(0);
    expect(screen.getByText('GET /v2/listings/{id}')).toBeInTheDocument();
    expect(screen.getByText('Tenant resolution contract')).toBeInTheDocument();
    expect(screen.getByText('/v2/tenant/bootstrap')).toBeInTheDocument();
    expect(screen.getByText('Public Laravel GET route')).toBeInTheDocument();
    expect(screen.getByText('GET /v2/tenant/bootstrap?slug={tenantSlug}')).toBeInTheDocument();
    expect(screen.getByText('Origin: https://<custom-domain>')).toBeInTheDocument();
    expect(screen.getByText('npm run build:next-public')).toBeInTheDocument();
    expect(screen.getAllByText('npm --prefix next-public-frontend run check')).not.toHaveLength(0);
    expect(screen.getByText('npm --prefix react-frontend run build')).toBeInTheDocument();
    expect(
      screen.getByText(
        'vendor/bin/phpunit --no-coverage tests/Laravel/Unit/Services/NextPublicFrontendReadinessServiceTest.php tests/Laravel/Feature/Controllers/AdminNextPublicFrontendControllerTest.php',
      ),
    ).toBeInTheDocument();
    expect(screen.getByText('Manifest validation passed')).toBeInTheDocument();
    expect(screen.getByText('Route cutover gates')).toBeInTheDocument();
    expect(screen.getByText('about')).toBeInTheDocument();
    expect(screen.getByText('static_or_tenant_bootstrap')).toBeInTheDocument();
    expect(screen.getByText('listingDetail')).toBeInTheDocument();
    expect(screen.getAllByText('laravel_public_api')).not.toHaveLength(0);
    expect(screen.getAllByText('Parity test required')).not.toHaveLength(0);
    expect(screen.getByText('3 public routes')).toBeInTheDocument();
    expect(screen.getByText('3 private prefixes')).toBeInTheDocument();
    expect(screen.getByText('Manual verification required')).toBeInTheDocument();
    expect(screen.getByText('Explicit cutover instruction required')).toBeInTheDocument();
    expect(screen.getByText('Production edge routes are not configured')).toBeInTheDocument();
    expect(screen.getByText('Operator playbook')).toBeInTheDocument();
    expect(screen.getByText('No activation controls are available on this page.')).toBeInTheDocument();
    expect(screen.getByText(/Prepare Apache canary routing as a reviewed config-only change\./)).toBeInTheDocument();
    expect(screen.getByText('Do not remove prerender fallback.')).toBeInTheDocument();
  });

  it('shows an error state when readiness cannot load', async () => {
    vi.mocked(adminSettings.getNextPublicFrontendReadiness).mockRejectedValue(new Error('Nope'));

    render(<NextPublicFrontendReadiness />);

    await waitFor(() => {
      expect(screen.getByText('Next.js readiness could not be loaded.')).toBeInTheDocument();
    });
  });
});
