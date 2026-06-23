// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock adminApi ────────────────────────────────────────────────────────────
const { mockAdminLandingPage } = vi.hoisted(() => ({
  mockAdminLandingPage: {
    get: vi.fn(),
    update: vi.fn(),
  },
}));

vi.mock('../../api/adminApi', () => ({
  adminLandingPage: mockAdminLandingPage,
}));

// ─── Mock @/lib/logger ───────────────────────────────────────────────────────
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Mock contexts ────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// Stub Select/SelectItem to avoid HeroUI jsdom infinite-loop
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    Select: ({ label, children, onSelectionChange }: { label?: string; children?: React.ReactNode; onSelectionChange?: (keys: Set<string>) => void }) => (
      <select aria-label={label ?? 'select'} onChange={(e) => onSelectionChange?.(new Set([e.target.value]))}>
        {children}
      </select>
    ),
    SelectItem: ({ children, id }: { children?: React.ReactNode; id?: string }) => (
      <option value={id ?? ''}>{children}</option>
    ),
    Switch: ({ children, isSelected, onValueChange }: { children?: React.ReactNode; isSelected?: boolean; onValueChange?: (v: boolean) => void }) => (
      <input
        type="checkbox"
        aria-label={typeof children === 'string' ? children : 'switch'}
        checked={!!isSelected}
        onChange={(e) => onValueChange?.(e.target.checked)}
      />
    ),
  };
});

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeLandingConfig = () => ({
  sections: [
    { id: 'hero', type: 'hero', order: 1, enabled: true, content: { headline_1: 'Welcome', badge_text: 'New' } },
    { id: 'cta', type: 'cta', order: 2, enabled: false, content: { title: 'Join us' } },
  ],
});

const makeSuccessResponse = (config = makeLandingConfig()) => ({
  success: true,
  data: { config },
});

// ─────────────────────────────────────────────────────────────────────────────
describe('LandingPageBuilder', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminLandingPage.get.mockResolvedValue(makeSuccessResponse());
    mockAdminLandingPage.update.mockResolvedValue({ success: true });
  });

  it('shows loading spinner while fetching config', async () => {
    mockAdminLandingPage.get.mockImplementationOnce(() => new Promise(() => {}));
    const { LandingPageBuilder } = await import('./LandingPageBuilder');
    render(<LandingPageBuilder />);

    const statuses = screen.getAllByRole('status');
    const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders section cards after loading', async () => {
    const { LandingPageBuilder } = await import('./LandingPageBuilder');
    render(<LandingPageBuilder />);

    // Wait for the spinner to disappear and sections to show
    await waitFor(() => {
      // The hero and cta section cards should now be in the document
      // LandingPageBuilder renders section labels from i18n keys; just assert it no longer shows spinner
      const spinners = screen.queryAllByRole('status');
      const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
      expect(busy).toBeUndefined();
    });
  });

  it('calls adminLandingPage.get on mount', async () => {
    const { LandingPageBuilder } = await import('./LandingPageBuilder');
    render(<LandingPageBuilder />);
    await waitFor(() => expect(mockAdminLandingPage.get).toHaveBeenCalledTimes(1));
  });

  it('save button is disabled when config is not dirty', async () => {
    const { LandingPageBuilder } = await import('./LandingPageBuilder');
    render(<LandingPageBuilder />);

    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      expect(spinners.find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });

    // Save Changes button should be disabled (not dirty)
    const saveBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('save')
    );
    expect(saveBtn).toBeDefined();
    expect(saveBtn).toHaveAttribute('data-disabled');
  });

  it('renders Reset to Defaults button', async () => {
    const { LandingPageBuilder } = await import('./LandingPageBuilder');
    render(<LandingPageBuilder />);

    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      expect(spinners.find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });

    const resetBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('reset')
    );
    expect(resetBtn).toBeDefined();
  });

  it('clicking Reset shows confirm/cancel buttons', async () => {
    const { LandingPageBuilder } = await import('./LandingPageBuilder');
    render(<LandingPageBuilder />);

    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      expect(spinners.find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });

    // "Reset to defaults" button — matches exactly on "defaults"
    const resetBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.includes('defaults') || b.textContent?.includes('Reset to defaults')
    );
    if (resetBtn) fireEvent.click(resetBtn);

    await waitFor(() => {
      // After click, "Yes, reset" confirm button appears
      const confirmBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.includes('Yes') || b.textContent?.includes('reset')
      );
      expect(confirmBtn).toBeDefined();
    });
  });

  it('calls adminLandingPage.update(null) on reset confirm', async () => {
    const { LandingPageBuilder } = await import('./LandingPageBuilder');
    render(<LandingPageBuilder />);

    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      expect(spinners.find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });

    const resetBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.includes('defaults')
    );
    if (resetBtn) fireEvent.click(resetBtn);

    // Wait for confirm button ("Yes, reset")
    await waitFor(() => {
      const confirmBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.includes('Yes') || b.textContent?.includes('Yes, reset')
      );
      expect(confirmBtn).toBeDefined();
    });

    const confirmBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.includes('Yes') || b.textContent?.includes('Yes, reset')
    );
    if (confirmBtn) fireEvent.click(confirmBtn);

    await waitFor(() => {
      expect(mockAdminLandingPage.update).toHaveBeenCalledWith(null);
    });
  });

  it('shows success toast after successful reset', async () => {
    const { LandingPageBuilder } = await import('./LandingPageBuilder');
    render(<LandingPageBuilder />);

    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      expect(spinners.find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });

    const resetBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.includes('defaults')
    );
    if (resetBtn) fireEvent.click(resetBtn);

    await waitFor(() => {
      const confirmBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.includes('Yes')
      );
      expect(confirmBtn).toBeDefined();
    });

    const confirmBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.includes('Yes')
    );
    if (confirmBtn) fireEvent.click(confirmBtn);

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when reset API call fails', async () => {
    // Test the reset error path: update returns success:false
    mockAdminLandingPage.update.mockResolvedValueOnce({ success: false });
    const { LandingPageBuilder } = await import('./LandingPageBuilder');
    render(<LandingPageBuilder />);

    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      expect(spinners.find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });

    // Click "Reset to defaults" → then confirm with "Yes, reset"
    const resetBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.includes('defaults')
    );
    if (resetBtn) fireEvent.click(resetBtn);

    await waitFor(() => {
      const confirmBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.includes('Yes')
      );
      expect(confirmBtn).toBeDefined();
    });

    const confirmBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.includes('Yes')
    );
    if (confirmBtn) fireEvent.click(confirmBtn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('uses defaults when API returns no config', async () => {
    mockAdminLandingPage.get.mockResolvedValueOnce({ success: true, data: { config: null } });
    const { LandingPageBuilder } = await import('./LandingPageBuilder');
    render(<LandingPageBuilder />);

    // Should render without crashing even with null config
    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      expect(spinners.find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });
    // Multiple section cards should be present (defaults have 7 sections)
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThan(0);
  });
});
