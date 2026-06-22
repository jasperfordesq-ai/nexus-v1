// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─────────────────────────────────────────────────────────────────────────────
// Stable mock data (vi.hoisted so factories run before vi.mock calls)
// ─────────────────────────────────────────────────────────────────────────────
const { mockToast, mockGetGuardianConsents } = vi.hoisted(() => {
  const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
  const mockGetGuardianConsents = vi.fn();
  return { mockToast, mockGetGuardianConsents };
});

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast }),
);

vi.mock('../../api/adminApi', () => ({
  adminVolunteering: {
    getGuardianConsents: mockGetGuardianConsents,
  },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import VolunteerConsents from './VolunteerConsents';

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────
const CONSENT_ACTIVE = {
  id: 1,
  minor_name: 'Alice Minor',
  guardian_name: 'Bob Guardian',
  guardian_email: 'bob@example.com',
  relationship: 'Parent',
  opportunity_title: 'Park Clean-up',
  status: 'active' as const,
  consent_date: '2025-01-01T00:00:00Z',
  expires_date: null,
};

const CONSENT_EXPIRED = {
  id: 2,
  minor_name: 'Charlie Minor',
  guardian_name: 'Dana Guardian',
  guardian_email: 'dana@example.com',
  relationship: 'Carer',
  opportunity_title: 'Library Help',
  status: 'expired' as const,
  consent_date: '2024-01-01T00:00:00Z',
  expires_date: '2024-06-01T00:00:00Z',
};

// Resolve a single page; has_more=false so the loop exits immediately
function resolveOnePage(items: unknown[]) {
  mockGetGuardianConsents.mockResolvedValue({
    success: true,
    data: { items, has_more: false, cursor: null },
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// Tests
// ─────────────────────────────────────────────────────────────────────────────
describe('VolunteerConsents', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders populated table with consent rows', async () => {
    resolveOnePage([CONSENT_ACTIVE]);

    render(<VolunteerConsents />);

    await waitFor(() => {
      expect(screen.getByText('Alice Minor')).toBeInTheDocument();
    });
    expect(screen.getByText('Bob Guardian')).toBeInTheDocument();
    expect(screen.getByText('bob@example.com')).toBeInTheDocument();
    expect(screen.getByText('Park Clean-up')).toBeInTheDocument();
  });

  it('shows empty state when no consents are returned', async () => {
    resolveOnePage([]);

    render(<VolunteerConsents />);

    await waitFor(() => {
      // EmptyState renders the title translation key. In tests i18n falls back to the key.
      // The component passes t('volunteering.no_consents') as the title.
      expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });
  });

  it('shows error toast on API failure', async () => {
    mockGetGuardianConsents.mockRejectedValue(new Error('Network error'));

    render(<VolunteerConsents />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows expiry warning banner when consents expire within 30 days', async () => {
    // Expires 10 days from now
    const soon = new Date(Date.now() + 10 * 24 * 60 * 60 * 1000).toISOString();
    const expiringSoon = {
      ...CONSENT_ACTIVE,
      id: 3,
      minor_name: 'Eve Minor',
      expires_date: soon,
      status: 'active' as const,
    };

    resolveOnePage([expiringSoon]);

    render(<VolunteerConsents />);

    // Both the table row AND the banner list should show Eve Minor
    await waitFor(() => {
      const bannerItems = screen.getAllByText('Eve Minor');
      // One in the table cell, one in the banner <li> span
      expect(bannerItems.length).toBeGreaterThanOrEqual(2);
    });
  });

  it('does NOT show expiry banner when no consents expire soon', async () => {
    resolveOnePage([CONSENT_ACTIVE]);

    render(<VolunteerConsents />);

    await waitFor(() => {
      expect(screen.getByText('Alice Minor')).toBeInTheDocument();
    });

    // No AlertTriangle-driven banner content should mention "contact_guardian"
    // The banner only appears when expiringConsents.length > 0
    expect(screen.queryByText('Alice Minor', { selector: 'li span' })).not.toBeInTheDocument();
  });

  it('shows Re-request button for expired consents', async () => {
    resolveOnePage([CONSENT_EXPIRED]);

    render(<VolunteerConsents />);

    await waitFor(() => {
      expect(screen.getByText('Charlie Minor')).toBeInTheDocument();
    });

    // The actions column renders a mailto button for expired rows
    const mailtoBtn = screen.getByRole('link', { name: /re.request/i });
    expect(mailtoBtn).toBeInTheDocument();
    expect(mailtoBtn).toHaveAttribute('href', expect.stringContaining('mailto:dana@example.com'));
  });

  it('does NOT show Re-request button for active consents', async () => {
    resolveOnePage([CONSENT_ACTIVE]);

    render(<VolunteerConsents />);

    await waitFor(() => {
      expect(screen.getByText('Alice Minor')).toBeInTheDocument();
    });

    expect(screen.queryByRole('link', { name: /re.request/i })).not.toBeInTheDocument();
  });

  it('handles plain-array response shape defensively', async () => {
    mockGetGuardianConsents.mockResolvedValue({
      success: true,
      data: [CONSENT_ACTIVE],
    });

    render(<VolunteerConsents />);

    await waitFor(() => {
      expect(screen.getByText('Alice Minor')).toBeInTheDocument();
    });
  });

  it('shows no error toast when API returns success:false but some items already fetched', async () => {
    // First call success, second fails — but items > 0 so no toast
    mockGetGuardianConsents
      .mockResolvedValueOnce({ success: true, data: { items: [CONSENT_ACTIVE], has_more: true, cursor: 2 } })
      .mockResolvedValueOnce({ success: false });

    render(<VolunteerConsents />);

    await waitFor(() => {
      expect(screen.getByText('Alice Minor')).toBeInTheDocument();
    });

    // No error because all.length > 0 when the break happens
    expect(mockToast.error).not.toHaveBeenCalled();
  });
});
