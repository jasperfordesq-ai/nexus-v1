// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─── Mock adminApi ────────────────────────────────────────────────────────────
const { mockAdminMatching } = vi.hoisted(() => ({
  mockAdminMatching: {
    getConfig: vi.fn(),
    updateConfig: vi.fn(),
    clearCache: vi.fn(),
  },
}));

vi.mock('../../api/adminApi', () => ({
  adminMatching: mockAdminMatching,
}));

// ─── Toast / Tenant / Router ──────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
const mockNavigate = vi.fn();

vi.mock('react-router-dom', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-router-dom')>();
  return { ...orig, useNavigate: () => mockNavigate };
});

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Stub heavy admin components ──────────────────────────────────────────────
vi.mock('../../components', () => ({
  PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div data-testid="page-header">
      <span>{title}</span>
      {actions}
    </div>
  ),
  ConfirmModal: ({
    isOpen,
    onConfirm,
    onClose,
    title,
  }: {
    isOpen: boolean;
    onConfirm: () => void;
    onClose: () => void;
    title: string;
  }) =>
    isOpen ? (
      <div role="dialog" data-testid="confirm-modal">
        <span>{title}</span>
        <button onClick={onConfirm}>Confirm</button>
        <button onClick={onClose}>Cancel</button>
      </div>
    ) : null,
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeConfig = (overrides = {}) => ({
  category_weight: 0.25,
  skill_weight: 0.20,
  proximity_weight: 0.25,
  freshness_weight: 0.10,
  reciprocity_weight: 0.15,
  quality_weight: 0.05,
  proximity_bands: [
    { distance_km: 5, score: 1.0 },
    { distance_km: 15, score: 0.9 },
    { distance_km: 30, score: 0.7 },
    { distance_km: 50, score: 0.5 },
    { distance_km: 100, score: 0.2 },
  ],
  enabled: true,
  broker_approval_enabled: true,
  max_distance_km: 50,
  min_match_score: 40,
  hot_match_threshold: 80,
  ...overrides,
});

const successConfig = { success: true, data: makeConfig() };

// ─────────────────────────────────────────────────────────────────────────────
describe('MatchingConfig', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminMatching.getConfig.mockResolvedValue(successConfig);
    mockAdminMatching.updateConfig.mockResolvedValue({ success: true });
    mockAdminMatching.clearCache.mockResolvedValue({ success: true, data: { entries_cleared: 12 } });
  });

  it('shows loading spinner while config is fetching', async () => {
    mockAdminMatching.getConfig.mockImplementationOnce(() => new Promise(() => {}));
    const { MatchingConfig } = await import('./MatchingConfig');
    render(<MatchingConfig />);

    const statuses = screen.getAllByRole('status');
    const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders algorithm settings after load', async () => {
    const { MatchingConfig } = await import('./MatchingConfig');
    render(<MatchingConfig />);

    await waitFor(() => {
      // Save button appears once config is loaded (disabled by default when not dirty)
      const btns = screen.getAllByRole('button');
      expect(btns.length).toBeGreaterThan(0);
    });
  });

  it('shows error toast when config load fails', async () => {
    mockAdminMatching.getConfig.mockRejectedValueOnce(new Error('network'));
    const { MatchingConfig } = await import('./MatchingConfig');
    render(<MatchingConfig />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('save button is disabled initially (not dirty)', async () => {
    const { MatchingConfig } = await import('./MatchingConfig');
    render(<MatchingConfig />);

    await waitFor(() => {
      // Find save button by text containing "save" (case-insensitive via textContent)
      const saveBtns = screen.getAllByRole('button').filter((b) =>
        b.textContent?.toLowerCase().includes('save')
      );
      // The save-changes button should be disabled when not dirty
      const saveBtn = saveBtns.find((b) =>
        b.textContent?.toLowerCase().includes('save')
      );
      expect(saveBtn).toBeDefined();
      // data-disabled for HeroUI
      const isDisabled =
        saveBtn?.hasAttribute('disabled') ||
        saveBtn?.getAttribute('data-disabled') === 'true';
      expect(isDisabled).toBe(true);
    });
  });

  it('opens clear cache confirm modal on button click', async () => {
    const { MatchingConfig } = await import('./MatchingConfig');
    render(<MatchingConfig />);

    await waitFor(() =>
      screen.getAllByRole('button').some((b) =>
        b.textContent?.toLowerCase().includes('cache')
      )
    );

    const cacheBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('cache')
    );
    expect(cacheBtn).toBeDefined();
    fireEvent.click(cacheBtn!);

    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });

  it('calls clearCache when cache modal is confirmed', async () => {
    const { MatchingConfig } = await import('./MatchingConfig');
    render(<MatchingConfig />);

    await waitFor(() =>
      screen.getAllByRole('button').some((b) =>
        b.textContent?.toLowerCase().includes('cache')
      )
    );

    const cacheBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('cache')
    );
    fireEvent.click(cacheBtn!);
    await waitFor(() => screen.getByRole('dialog'));

    const confirmBtn = screen.getByRole('button', { name: /confirm/i });
    fireEvent.click(confirmBtn);

    await waitFor(() => {
      expect(mockAdminMatching.clearCache).toHaveBeenCalled();
    });
  });

  it('shows success toast after clearing cache', async () => {
    const { MatchingConfig } = await import('./MatchingConfig');
    render(<MatchingConfig />);

    await waitFor(() =>
      screen.getAllByRole('button').some((b) =>
        b.textContent?.toLowerCase().includes('cache')
      )
    );

    const cacheBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('cache')
    );
    fireEvent.click(cacheBtn!);
    await waitFor(() => screen.getByRole('dialog'));

    const confirmBtn = screen.getByRole('button', { name: /confirm/i });
    fireEvent.click(confirmBtn);

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('opens reset-to-defaults confirm modal', async () => {
    const { MatchingConfig } = await import('./MatchingConfig');
    render(<MatchingConfig />);

    await waitFor(() =>
      screen.getAllByRole('button').some((b) =>
        b.textContent?.toLowerCase().includes('reset')
      )
    );

    const resetBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('reset')
    );
    expect(resetBtn).toBeDefined();
    fireEvent.click(resetBtn!);

    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });

  it('calls toast.info and closes modal after confirming reset', async () => {
    const { MatchingConfig } = await import('./MatchingConfig');
    render(<MatchingConfig />);

    await waitFor(() =>
      screen.getAllByRole('button').some((b) =>
        b.textContent?.toLowerCase().includes('reset')
      )
    );

    const resetBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('reset')
    );
    fireEvent.click(resetBtn!);
    await waitFor(() => screen.getByRole('dialog'));

    const confirmBtn = screen.getByRole('button', { name: /confirm/i });
    fireEvent.click(confirmBtn);

    await waitFor(() => {
      expect(mockToast.info).toHaveBeenCalled();
      // Modal should close
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });

  it('navigates back when Back button is clicked', async () => {
    const { MatchingConfig } = await import('./MatchingConfig');
    render(<MatchingConfig />);

    await waitFor(() =>
      screen.getAllByRole('button').some((b) =>
        b.textContent?.toLowerCase().includes('back')
      )
    );

    const backBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('back')
    );
    expect(backBtn).toBeDefined();
    fireEvent.click(backBtn!);

    expect(mockNavigate).toHaveBeenCalledWith('/test/admin/smart-matching');
  });

  it('calls updateConfig with current config on save when weights valid', async () => {
    // Load config and mark dirty by simulating reset (which sets dirty=true)
    const { MatchingConfig } = await import('./MatchingConfig');
    render(<MatchingConfig />);

    // Wait for config to load
    await waitFor(() =>
      screen.getAllByRole('button').some((b) =>
        b.textContent?.toLowerCase().includes('reset')
      )
    );

    // Reset to defaults → makes config dirty
    const resetBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('reset')
    );
    fireEvent.click(resetBtn!);
    await waitFor(() => screen.getByRole('dialog'));
    fireEvent.click(screen.getByRole('button', { name: /confirm/i }));
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());

    // Now save button should be enabled
    const saveBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('save')
    );
    expect(saveBtn).toBeDefined();
    fireEvent.click(saveBtn!);

    await waitFor(() => {
      expect(mockAdminMatching.updateConfig).toHaveBeenCalled();
    });
  });

  it('shows error toast when save fails', async () => {
    mockAdminMatching.updateConfig.mockRejectedValueOnce(new Error('fail'));
    const { MatchingConfig } = await import('./MatchingConfig');
    render(<MatchingConfig />);

    await waitFor(() =>
      screen.getAllByRole('button').some((b) =>
        b.textContent?.toLowerCase().includes('reset')
      )
    );

    // Make dirty via reset
    const resetBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('reset')
    );
    fireEvent.click(resetBtn!);
    await waitFor(() => screen.getByRole('dialog'));
    fireEvent.click(screen.getByRole('button', { name: /confirm/i }));
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());

    const saveBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('save')
    );
    fireEvent.click(saveBtn!);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});
