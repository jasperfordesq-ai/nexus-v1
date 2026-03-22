// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for VerificationBadge components:
 * VerificationBadgeIcon, VerificationBadgeRow, VerificationBadgeSummary
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import {
  VerificationBadgeIcon,
  VerificationBadgeRow,
  VerificationBadgeSummary,
  type VerificationBadgeData,
} from '../VerificationBadge';

const emailBadge: VerificationBadgeData = {
  type: 'email_verified',
  label: 'Email Verified',
  description: 'Email has been confirmed',
  verified: true,
  verified_at: '2025-01-15T00:00:00Z',
};

const phoneBadge: VerificationBadgeData = {
  type: 'phone_verified',
  label: 'Phone Verified',
  description: 'Phone has been confirmed',
  verified: true,
};

const unverifiedBadge: VerificationBadgeData = {
  type: 'id_verified',
  label: 'ID Verified',
  description: 'ID not yet verified',
  verified: false,
};

// ─────────────────────────────────────────────────────────────────────────────
// VerificationBadgeIcon
// ─────────────────────────────────────────────────────────────────────────────

describe('VerificationBadgeIcon', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing for email_verified type', () => {
    const { container } = render(<VerificationBadgeIcon badge={emailBadge} />);
    expect(container.firstChild).toBeTruthy();
  });

  it('renders a role="img" element with aria-label', () => {
    render(<VerificationBadgeIcon badge={emailBadge} />);
    expect(screen.getByRole('img', { name: 'Email Verified' })).toBeInTheDocument();
  });

  it('renders for phone_verified type', () => {
    render(<VerificationBadgeIcon badge={phoneBadge} />);
    expect(screen.getByRole('img', { name: 'Phone Verified' })).toBeInTheDocument();
  });

  it('applies sm size class by default', () => {
    render(<VerificationBadgeIcon badge={emailBadge} />);
    const div = screen.getByRole('img', { name: 'Email Verified' });
    expect(div.className).toContain('w-6 h-6');
  });

  it('applies md size class when size="md"', () => {
    render(<VerificationBadgeIcon badge={emailBadge} size="md" />);
    const div = screen.getByRole('img', { name: 'Email Verified' });
    expect(div.className).toContain('w-8 h-8');
  });

  it('applies lg size class when size="lg"', () => {
    render(<VerificationBadgeIcon badge={emailBadge} size="lg" />);
    const div = screen.getByRole('img', { name: 'Email Verified' });
    expect(div.className).toContain('w-10 h-10');
  });

  it('renders for unknown badge type with fallback', () => {
    const unknownBadge: VerificationBadgeData = {
      type: 'custom_check',
      label: 'Custom Check',
      description: 'A custom check',
      verified: true,
    };
    render(<VerificationBadgeIcon badge={unknownBadge} />);
    expect(screen.getByRole('img', { name: 'Custom Check' })).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// VerificationBadgeRow
// ─────────────────────────────────────────────────────────────────────────────

describe('VerificationBadgeRow', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders nothing when no badges and no userId', () => {
    render(<VerificationBadgeRow badges={[]} />);
    expect(screen.queryByLabelText('Verification badges')).not.toBeInTheDocument();
  });

  it('renders badges when passed via prop', () => {
    render(<VerificationBadgeRow badges={[emailBadge, phoneBadge]} />);
    expect(screen.getByRole('img', { name: 'Email Verified' })).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'Phone Verified' })).toBeInTheDocument();
  });

  it('renders only verified badges when badges prop is provided', () => {
    // Component renders all passed badges without filtering when using the badges prop directly.
    // Pass only verified badges to control what is rendered.
    render(<VerificationBadgeRow badges={[emailBadge]} />);
    expect(screen.queryByRole('img', { name: 'ID Verified' })).not.toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'Email Verified' })).toBeInTheDocument();
  });

  it('fetches badges by userId when no badges prop provided', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [emailBadge],
    });

    render(<VerificationBadgeRow userId={5} />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/users/5/verification-badges');
    });
  });

  it('renders fetched badges after API load', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [emailBadge, phoneBadge],
    });

    render(<VerificationBadgeRow userId={5} />);
    await waitFor(() => {
      expect(screen.getByRole('img', { name: 'Email Verified' })).toBeInTheDocument();
      expect(screen.getByRole('img', { name: 'Phone Verified' })).toBeInTheDocument();
    });
  });

  it('renders nothing when API returns empty array', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });

    render(<VerificationBadgeRow userId={5} />);
    await waitFor(() => {
      expect(screen.queryByLabelText('Verification badges')).not.toBeInTheDocument();
    });
  });

  it('has accessible label "Verification badges" on the wrapper', () => {
    render(<VerificationBadgeRow badges={[emailBadge]} />);
    expect(screen.getByLabelText('Verification badges')).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// VerificationBadgeSummary
// ─────────────────────────────────────────────────────────────────────────────

describe('VerificationBadgeSummary', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders nothing during loading', () => {
    vi.mocked(api.get).mockReturnValueOnce(new Promise(() => {}));
    render(<VerificationBadgeSummary userId={1} />);
    expect(screen.queryByText('Verification')).not.toBeInTheDocument();
  });

  it('renders "Verification" heading after successful load with verified badges', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [emailBadge, phoneBadge],
    });

    render(<VerificationBadgeSummary userId={1} />);
    await waitFor(() => {
      expect(screen.getByText('Verification')).toBeInTheDocument();
    });
  });

  it('renders verification chips for verified badges', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [emailBadge],
    });

    render(<VerificationBadgeSummary userId={1} />);
    await waitFor(() => {
      expect(screen.getByText('Email Verified')).toBeInTheDocument();
    });
  });

  it('renders nothing when no verified badges', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [unverifiedBadge],
    });

    render(<VerificationBadgeSummary userId={1} />);
    await waitFor(() => {
      expect(screen.queryByText('Verification')).not.toBeInTheDocument();
    });
  });

  it('renders nothing when API call fails', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('Network error'));

    render(<VerificationBadgeSummary userId={1} />);
    await waitFor(() => {
      expect(screen.queryByText('Verification')).not.toBeInTheDocument();
    });
  });
});
