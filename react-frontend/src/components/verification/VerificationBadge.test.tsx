// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock api ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
    upload: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Contexts ────────────────────────────────────────────────────────────────
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  })
);

// ─── Stub UI components ──────────────────────────────────────────────────────
vi.mock('@/components/ui', async () => (await import('@/test/uiMock')).uiMock);

// ─── Fixtures ────────────────────────────────────────────────────────────────
import type { VerificationBadgeData } from './VerificationBadge';

const emailBadge: VerificationBadgeData = {
  type: 'email_verified',
  label: 'Email Verified',
  verified: true,
  verified_at: '2025-01-01T00:00:00Z',
};

const phoneBadge: VerificationBadgeData = {
  type: 'phone_verified',
  label: 'Phone Verified',
  verified: true,
};

const idBadge: VerificationBadgeData = {
  type: 'id_verified',
  label: 'ID Verified',
  verified: true,
  verified_at: '2025-03-15T00:00:00Z',
};

const dbsBadge: VerificationBadgeData = {
  type: 'dbs_checked',
  label: 'DBS Checked',
  verified: true,
};

const adminBadge: VerificationBadgeData = {
  type: 'admin_verified',
  label: 'Admin Verified',
  verified: true,
};

// ─────────────────────────────────────────────────────────────────────────────
describe('VerificationBadgeIcon', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders with a role=img and accessible label', async () => {
    const { VerificationBadgeIcon } = await import('./VerificationBadge');
    render(<VerificationBadgeIcon badge={idBadge} />);

    const icon = screen.getByRole('img');
    expect(icon).toBeInTheDocument();
    expect(icon.getAttribute('aria-label')).toBeTruthy();
  });

  it('renders for email_verified badge type', async () => {
    const { VerificationBadgeIcon } = await import('./VerificationBadge');
    render(<VerificationBadgeIcon badge={emailBadge} />);

    const icon = screen.getByRole('img');
    expect(icon).toBeInTheDocument();
  });

  it('renders for unknown badge type using fallback', async () => {
    const { VerificationBadgeIcon } = await import('./VerificationBadge');
    const unknown: VerificationBadgeData = { type: 'unknown_type', label: 'Custom Badge' };
    render(<VerificationBadgeIcon badge={unknown} />);

    const icon = screen.getByRole('img');
    expect(icon).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
describe('VerificationBadgeRow — with prop badges', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders without crashing when passed email_verified badge', async () => {
    const { VerificationBadgeRow } = await import('./VerificationBadge');
    render(<VerificationBadgeRow badges={[emailBadge]} />);
    // The badge label text appears in a span
    await waitFor(() => {
      expect(screen.getAllByText(/email verified/i).length).toBeGreaterThan(0);
    });
  });

  it('renders id_verified badge and does NOT show "Not ID Verified" sentinel', async () => {
    const { VerificationBadgeRow } = await import('./VerificationBadge');
    render(<VerificationBadgeRow badges={[idBadge]} />);

    await waitFor(() => {
      // With id_verified present, the __unverified__ sentinel is suppressed
      expect(screen.queryByText(/not id verified/i)).not.toBeInTheDocument();
    });
  });

  it('shows "Not ID Verified" when id_verified is absent', async () => {
    const { VerificationBadgeRow } = await import('./VerificationBadge');
    render(<VerificationBadgeRow badges={[emailBadge]} />);

    await waitFor(() => {
      // The __unverified__ sentinel should appear
      expect(screen.getByText(/not id verified/i)).toBeInTheDocument();
    });
  });

  it('renders multiple badges together', async () => {
    const { VerificationBadgeRow } = await import('./VerificationBadge');
    render(<VerificationBadgeRow badges={[emailBadge, idBadge, dbsBadge]} />);

    await waitFor(() => {
      // Email and DBS visible; id_verified suppresses the "not verified" sentinel
      expect(screen.getAllByText(/email verified/i).length).toBeGreaterThan(0);
      expect(screen.queryByText(/not id verified/i)).not.toBeInTheDocument();
    });
  });

  it('renders empty without crashing', async () => {
    const { VerificationBadgeRow } = await import('./VerificationBadge');
    render(<VerificationBadgeRow badges={[]} />);

    await waitFor(() => {
      // No id_verified → shows "not verified" sentinel
      expect(screen.getByText(/not id verified/i)).toBeInTheDocument();
    });
  });

  it('renders medium size chips instead of spans', async () => {
    const { VerificationBadgeRow } = await import('./VerificationBadge');
    render(<VerificationBadgeRow badges={[adminBadge]} size="md" />);

    await waitFor(() => {
      expect(screen.getAllByText(/admin verified/i).length).toBeGreaterThan(0);
    });
  });
});

// ─────────────────────────────────────────────────────────────────────────────
describe('VerificationBadgeRow — fetching by userId', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('fetches badges by userId and displays them', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [phoneBadge] });
    const { VerificationBadgeRow } = await import('./VerificationBadge');
    render(<VerificationBadgeRow userId={42} />);

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith('/v2/users/42/verification-badges');
      expect(screen.getAllByText(/phone verified/i).length).toBeGreaterThan(0);
    });
  });

  it('renders "not ID verified" when API returns empty array', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [] });
    const { VerificationBadgeRow } = await import('./VerificationBadge');
    render(<VerificationBadgeRow userId={99} />);

    await waitFor(() => {
      expect(screen.getByText(/not id verified/i)).toBeInTheDocument();
    });
  });

  it('silently fails and renders unverified when API errors', async () => {
    mockApi.get.mockRejectedValue(new Error('network'));
    const { VerificationBadgeRow } = await import('./VerificationBadge');
    render(<VerificationBadgeRow userId={7} />);

    await waitFor(() => {
      expect(screen.getByText(/not id verified/i)).toBeInTheDocument();
    });
  });
});

// ─────────────────────────────────────────────────────────────────────────────
describe('VerificationBadgeSummary', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('fetches and renders badges for a user', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [idBadge] });
    const { VerificationBadgeSummary } = await import('./VerificationBadge');
    render(<VerificationBadgeSummary userId={5} />);

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith('/v2/users/5/verification-badges');
      expect(screen.getAllByText(/id verified/i).length).toBeGreaterThan(0);
    });
  });

  it('shows "identity not verified" message when no badges', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [] });
    const { VerificationBadgeSummary } = await import('./VerificationBadge');
    render(<VerificationBadgeSummary userId={5} />);

    await waitFor(() => {
      expect(screen.getByText(/not verified/i)).toBeInTheDocument();
    });
  });
});
