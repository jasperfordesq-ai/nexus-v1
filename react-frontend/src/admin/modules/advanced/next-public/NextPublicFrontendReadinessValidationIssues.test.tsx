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
        {
          code: 'vite_private_pattern_missing_required',
          context: '/events/new',
          severity: 'blocker',
        },
        {
          code: 'public_route_collides_with_private_pattern',
          context: '/events/new',
          severity: 'blocker',
        },
        {
          code: 'api_backed_route_private_endpoint',
          context: 'events',
          severity: 'blocker',
        },
        {
          code: 'api_backed_route_endpoint_not_plain_path',
          context: 'events',
          severity: 'blocker',
        },
        {
          code: 'api_backed_route_endpoint_has_path_traversal',
          context: 'events',
          severity: 'blocker',
        },
        {
          code: 'api_backed_route_not_registered',
          context: 'events',
          severity: 'blocker',
        },
        {
          code: 'api_backed_route_requires_auth',
          context: 'jobs',
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
  tenant_resolution: {
    status: 'pass',
    bootstrap_endpoint: '/v2/tenant/bootstrap',
    source_of_truth: 'laravel_public_api',
    shared_host_slug_parameter: 'slug',
    custom_domain_origin_forwarding: true,
    next_queries_database: false,
    bootstrap_route_status: 'public',
    examples: [],
  },
  edge_canary: {
    status: 'blocker',
    edge: 'apache_plesk',
    routing_flag: 'NEXT_PUBLIC_FRONTEND_ROUTING_ENABLED',
    routing_flag_enabled: false,
    activation_available: false,
    preview_only: true,
    requires_explicit_cutover_instruction: true,
    reviewed_config_required: true,
    route_file_status: 'not_configured',
    config_template: {
      path: 'scripts/deploy/apache/next-public-foundation-canary.conf.example',
      exists: true,
      example_only: true,
      included_by_deploy: false,
      required_review_steps: [],
    },
    route_audit: {
      status: 'pass',
      template_path: 'scripts/deploy/apache/next-public-foundation-canary.conf.example',
      template_exists: true,
      exact_path_count: 0,
      public_only: true,
      template_paths: [],
      private_collisions: [],
      unmatched_template_paths: [],
      unsupported_rules: [],
    },
    guardrails: [],
  },
  route_batches: [
    {
      key: 'foundation_public_pages',
      status: 'blocked',
      route_count: 1,
      route_keys: ['home'],
      blockers: ['manual_shadow_review_required'],
      verification_commands: [],
    },
  ],
  remaining_public_route_work: {
    production_effect: 'none',
    activation_available: false,
    counts: {
      public_routes: 1,
      api_backed_public_routes: 0,
      remaining_public_routes: 1,
      unclassified_manifest_only_routes: 0,
    },
    guardrails: ['route_status_has_no_production_effect'],
    groups: [
      {
        key: 'static_manual_review',
        status: 'blocked',
        route_count: 1,
        route_keys: ['home'],
        reason: 'static_or_tenant_bootstrap',
        required_actions: ['manual_no_js_shadow_review'],
        verification_commands: [],
      },
    ],
  },
  cutover_artifacts: {
    production_effect: 'none',
    activation_available: false,
    items: [],
    required_commands: [],
  },
  cutover_eligibility: {
    status: 'blocked',
    eligible: false,
    production_effect: 'none',
    activation_available: false,
    requires_explicit_cutover_instruction: true,
    counts: {
      public_routes: 1,
      api_backed_public_routes: 0,
      remaining_public_routes: 1,
      unclassified_manifest_only_routes: 0,
    },
    blockers: [
      'manifest_validation_blocked',
      'remaining_public_route_work',
      'edge_routes_not_configured',
      'explicit_cutover_instruction_required',
    ],
    required_actions: [
      'complete_remaining_public_route_work',
      'run_shadow_verification',
      'prepare_reviewed_edge_config_after_instruction',
      'keep_prerender_fallback',
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
    verification_commands: [],
  },
  safety_checks: [
    { key: 'route_cutover_disabled', status: 'pass' },
  ],
  cutover_step_keys: ['verify_next_shadow_build'],
  cutover_gates: [
    {
      key: 'verify_next_shadow_build',
      status: 'blocker',
      blockers: ['manual_verification_required'],
      verification_commands: [],
    },
  ],
  operator_playbook: {
    activation_available: false,
    requires_explicit_cutover_instruction: true,
    no_production_effect: true,
    stages: [],
  },
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
    expect(screen.getByText('Required Vite private route pattern is missing.')).toBeInTheDocument();
    expect(screen.getByText('A public route collides with a private Vite route pattern.')).toBeInTheDocument();
    expect(screen.getByText('API-backed public routes must not use private Laravel v2 namespaces.')).toBeInTheDocument();
    expect(screen.getByText('API-backed route endpoints must be plain paths without query strings or fragments.')).toBeInTheDocument();
    expect(screen.getByText('API-backed route endpoint is not registered in Laravel.')).toBeInTheDocument();
    expect(screen.getByText('API-backed route endpoint requires authentication.')).toBeInTheDocument();
    expect(screen.getAllByText('next_database')).not.toHaveLength(0);
    expect(screen.getAllByText('login')).not.toHaveLength(0);
    expect(screen.getAllByText('/events/new')).not.toHaveLength(0);
  });
});
