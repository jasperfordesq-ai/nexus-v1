// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Hoist mock api ───────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// ─── Toast mock ───────────────────────────────────────────────────────────────
const mockShowToast = vi.fn();

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => ({ showToast: mockShowToast, success: vi.fn(), error: vi.fn() }),
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

// ─── Admin component stubs ────────────────────────────────────────────────────
vi.mock('../../components', () => ({
  PageHeader: ({
    title,
    actions,
  }: {
    title: string;
    subtitle?: string;
    icon?: React.ReactNode;
    actions?: React.ReactNode;
  }) => (
    <div>
      <h1>{title}</h1>
      {actions}
    </div>
  ),
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeSection = (overrides = {}) => ({
  key: 'commercial_boundary',
  label_code: 'commercial_boundary',
  status: 'ready' as const,
  summary_code: 'commercial_boundary.ready_default',
  summary_params: {},
  admin_path: '/admin/commercial',
  last_updated_at: '2025-01-01T00:00:00Z',
  missing: [],
  ...overrides,
});

const makeReadinessReport = (overrides = {}) => ({
  generated_at: '2025-01-15T12:00:00Z',
  overall: {
    status: 'ready' as const,
    ready_section_count: 3,
    total_section_count: 3,
    summary_code: 'ready',
    summary_params: {},
  },
  sections: [makeSection()],
  isolated_node_required: false,
  can_launch: true,
  blockers: [],
  launched: null,
  ...overrides,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('PilotLaunchReadinessAdminPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue({ success: true, data: makeReadinessReport() });
    mockApi.post.mockResolvedValue({ success: true, data: {} });
  });

  it('shows a loading spinner while the report is being fetched', async () => {
    mockApi.get.mockImplementationOnce(() => new Promise(() => {}));
    const PilotLaunchReadinessAdminPage = (await import('./PilotLaunchReadinessAdminPage')).default;
    render(<PilotLaunchReadinessAdminPage />);

    const statuses = screen.getAllByRole('status');
    const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders the overall status and sections after load', async () => {
    const PilotLaunchReadinessAdminPage = (await import('./PilotLaunchReadinessAdminPage')).default;
    render(<PilotLaunchReadinessAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('AG82 — Commercial boundary map')).toBeInTheDocument();
    });
  });

  it('renders the ready state card when can_launch is true', async () => {
    const PilotLaunchReadinessAdminPage = (await import('./PilotLaunchReadinessAdminPage')).default;
    render(<PilotLaunchReadinessAdminPage />);

    await waitFor(() => {
      // i18n resolves to "Ready to launch"
      expect(screen.getByText('Ready to launch')).toBeInTheDocument();
    });
  });

  it('shows error card when the API returns a failed response', async () => {
    mockApi.get.mockResolvedValue({ success: false, error: 'Server error' });
    const PilotLaunchReadinessAdminPage = (await import('./PilotLaunchReadinessAdminPage')).default;
    render(<PilotLaunchReadinessAdminPage />);

    await waitFor(() => {
      // i18n resolves to "Readiness report unavailable"
      expect(screen.getByText('Readiness report unavailable')).toBeInTheDocument();
    });
  });

  it('shows error card and calls showToast on network failure', async () => {
    mockApi.get.mockRejectedValue(new Error('network'));
    const PilotLaunchReadinessAdminPage = (await import('./PilotLaunchReadinessAdminPage')).default;
    render(<PilotLaunchReadinessAdminPage />);

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(
        expect.any(String),
        'error'
      );
    });
  });

  it('renders Launch Pilot button when can_launch is true and not yet launched', async () => {
    const PilotLaunchReadinessAdminPage = (await import('./PilotLaunchReadinessAdminPage')).default;
    render(<PilotLaunchReadinessAdminPage />);

    await waitFor(() => screen.getByText('AG82 — Commercial boundary map'));

    // i18n resolves to "Launch pilot"
    const launchBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.includes('Launch pilot')
    );
    expect(launchBtn).toBeDefined();
    // Not data-disabled when can_launch is true
    expect(launchBtn?.getAttribute('data-disabled')).toBeFalsy();
  });

  it('opens the launch confirmation modal when Launch Pilot is clicked', async () => {
    const PilotLaunchReadinessAdminPage = (await import('./PilotLaunchReadinessAdminPage')).default;
    render(<PilotLaunchReadinessAdminPage />);

    await waitFor(() => screen.getByText('AG82 — Commercial boundary map'));

    const launchBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.includes('Launch pilot')
    );
    if (launchBtn) fireEvent.click(launchBtn);

    await waitFor(() => {
      const dialogs = document.querySelectorAll('[role="dialog"]');
      expect(dialogs.length).toBeGreaterThan(0);
    });
  });

  it('calls POST /launch when confirm launch is clicked and shows success toast', async () => {
    const updatedReport = makeReadinessReport({
      launched: { launched_at: '2025-01-15T13:00:00Z', launched_by_id: 1 },
    });
    mockApi.post.mockResolvedValue({ success: true, data: { report: updatedReport } });

    const PilotLaunchReadinessAdminPage = (await import('./PilotLaunchReadinessAdminPage')).default;
    render(<PilotLaunchReadinessAdminPage />);

    await waitFor(() => screen.getByText('AG82 — Commercial boundary map'));

    // Open modal — "Launch pilot" button
    const launchBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.includes('Launch pilot')
    );
    if (launchBtn) fireEvent.click(launchBtn);

    // Confirm inside modal — "Yes, launch the pilot"
    await waitFor(() => {
      const confirmBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.includes('Yes, launch the pilot')
      );
      if (confirmBtn) fireEvent.click(confirmBtn);
    });

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/admin/caring-community/launch-readiness/launch',
        {}
      );
      expect(mockShowToast).toHaveBeenCalledWith(
        expect.any(String),
        'success'
      );
    });
  });

  it('does not show Launch Pilot button when already launched', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: makeReadinessReport({
        launched: { launched_at: '2025-01-10T10:00:00Z', launched_by_id: 1 },
      }),
    });
    const PilotLaunchReadinessAdminPage = (await import('./PilotLaunchReadinessAdminPage')).default;
    render(<PilotLaunchReadinessAdminPage />);

    await waitFor(() => screen.getByText('AG82 — Commercial boundary map'));

    const launchBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.includes('Launch pilot')
    );
    // Should not exist when launched
    expect(launchBtn).toBeUndefined();
  });

  it('shows blocked state card when blockers exist', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: makeReadinessReport({
        can_launch: false,
        blockers: [{ key: 'safeguarding', status: 'blocked' }],
        overall: {
          status: 'blocked',
          ready_section_count: 1,
          total_section_count: 3,
          summary_code: 'blocked',
          summary_params: {},
        },
        sections: [makeSection({ status: 'blocked', missing: ['safeguarding_escalation_user_id'] })],
      }),
    });
    const PilotLaunchReadinessAdminPage = (await import('./PilotLaunchReadinessAdminPage')).default;
    render(<PilotLaunchReadinessAdminPage />);

    await waitFor(() => {
      // i18n resolves to "Cannot launch yet - 1 blocker remains" (singular)
      expect(screen.getByText(/Cannot launch yet/)).toBeInTheDocument();
    });
  });

  it('shows acknowledge button for commercial_boundary section needing review', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: makeReadinessReport({
        can_launch: false,
        sections: [makeSection({ key: 'commercial_boundary', status: 'needs_review' })],
        overall: {
          status: 'needs_review',
          ready_section_count: 0,
          total_section_count: 1,
          summary_code: 'needs_review',
          summary_params: { ready: 0, total: 1 },
        },
      }),
    });
    const PilotLaunchReadinessAdminPage = (await import('./PilotLaunchReadinessAdminPage')).default;
    render(<PilotLaunchReadinessAdminPage />);

    await waitFor(() => {
      // i18n resolves to "Acknowledge default matrix"
      const ackBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.includes('Acknowledge default matrix')
      );
      expect(ackBtn).toBeDefined();
    });
  });
});
