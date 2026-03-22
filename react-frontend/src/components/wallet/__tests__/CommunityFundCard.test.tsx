// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for CommunityFundCard component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
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

import { CommunityFundCard } from '../CommunityFundCard';

const mockFundData = {
  balance: 150,
  total_deposited: 500,
  total_withdrawn: 200,
  total_donated: 150,
};

describe('CommunityFundCard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders loading state initially', () => {
    vi.mocked(api.get).mockReturnValueOnce(new Promise(() => {}));
    const { container } = render(<CommunityFundCard />);
    expect(container.querySelector('[class*="animate-pulse"]')).toBeInTheDocument();
  });

  it('renders nothing when fund data is null after load', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: false, data: null });

    const { container } = render(<CommunityFundCard />);
    await waitFor(() => {
      expect(container.firstChild).toBeNull();
    });
  });

  it('renders balance after successful API load', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockFundData });

    render(<CommunityFundCard />);
    await waitFor(() => {
      expect(screen.getByText('150')).toBeInTheDocument();
    });
  });

  it('renders full card layout in non-compact mode', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockFundData });

    render(<CommunityFundCard />);
    await waitFor(() => {
      expect(screen.getByText('500h')).toBeInTheDocument();
      expect(screen.getByText('200h')).toBeInTheDocument();
      expect(screen.getByText('150h')).toBeInTheDocument();
    });
  });

  it('renders compact layout when compact=true', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockFundData });

    render(<CommunityFundCard compact />);
    await waitFor(() => {
      expect(screen.getByText('150h')).toBeInTheDocument();
    });
  });

  it('shows Donate button in compact mode when onDonateClick is provided', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockFundData });

    render(<CommunityFundCard compact onDonateClick={vi.fn()} />);
    await waitFor(() => {
      expect(screen.getByRole('button')).toBeInTheDocument();
    });
  });

  it('calls onDonateClick when donate button is clicked', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockFundData });
    const onDonateClick = vi.fn();

    render(<CommunityFundCard onDonateClick={onDonateClick} />);
    await waitFor(() => {
      expect(screen.getByRole('button')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('button'));
    expect(onDonateClick).toHaveBeenCalled();
  });

  it('does not show donate button when onDonateClick is not provided', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockFundData });

    render(<CommunityFundCard />);
    await waitFor(() => {
      expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
  });

  it('calls API at correct endpoint', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockFundData });

    render(<CommunityFundCard />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/wallet/community-fund');
    });
  });

  it('handles API error gracefully without crashing', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('Network error'));

    const { container } = render(<CommunityFundCard />);
    await waitFor(() => {
      // After error the fund is null so renders nothing
      expect(container.firstChild).toBeNull();
    });
  });
});
