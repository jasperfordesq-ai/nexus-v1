// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/contexts', () => createMockContexts());

import { api } from '@/lib/api';
import { FederationCommunityPicker } from './FederationCommunityPicker';

const mockGet = vi.mocked(api.get);

const PEERS = [
  {
    id: 1,
    slug: 'berlin-care',
    display_name: 'Berlin Caring Network',
    base_url: 'https://berlin.example.com',
    region: 'Berlin',
    member_count_bucket: '100–500',
    accepts_inbound_transfers: true,
  },
  {
    id: 2,
    slug: 'munich-helpers',
    display_name: 'Munich Helpers',
    base_url: 'https://munich.example.com',
    region: 'Bavaria',
    member_count_bucket: '50–100',
    accepts_inbound_transfers: false,
  },
];

describe('FederationCommunityPicker', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('does not load peers when closed', () => {
    mockGet.mockResolvedValue({ success: true, data: { peers: PEERS } });

    render(
      <FederationCommunityPicker
        isOpen={false}
        onClose={vi.fn()}
        onSelect={vi.fn()}
      />,
    );

    expect(mockGet).not.toHaveBeenCalled();
  });

  it('shows a spinner while loading peers', () => {
    // Hang the promise so loading stays visible
    mockGet.mockReturnValueOnce(new Promise(() => {}));

    render(
      <FederationCommunityPicker
        isOpen={true}
        onClose={vi.fn()}
        onSelect={vi.fn()}
      />,
    );

    // The loading container div has aria-busy="true" — look for it directly
    // (avoids ambiguity with the ToastProvider and Spinner's nested role="status")
    const busyContainer = document.querySelector('[aria-busy="true"]');
    expect(busyContainer).not.toBeNull();
  });

  it('renders peers after successful load', async () => {
    mockGet.mockResolvedValueOnce({ success: true, data: { peers: PEERS } });

    render(
      <FederationCommunityPicker
        isOpen={true}
        onClose={vi.fn()}
        onSelect={vi.fn()}
      />,
    );

    await waitFor(() => {
      expect(screen.getByText('Berlin Caring Network')).toBeInTheDocument();
    });
    expect(screen.getByText('Munich Helpers')).toBeInTheDocument();
  });

  it('shows empty state when API returns no peers', async () => {
    mockGet.mockResolvedValueOnce({ success: true, data: { peers: [] } });

    render(
      <FederationCommunityPicker
        isOpen={true}
        onClose={vi.fn()}
        onSelect={vi.fn()}
      />,
    );

    // Wait for loading to finish (spinner disappears)
    await waitFor(() => {
      expect(screen.queryByRole('status', { name: /loading/i, hidden: true })).not.toBeInTheDocument();
    });
    // No peer cards
    expect(screen.queryByText('Berlin Caring Network')).not.toBeInTheDocument();
    expect(screen.queryByText('Munich Helpers')).not.toBeInTheDocument();
  });

  it('shows empty state when API call fails', async () => {
    mockGet.mockRejectedValueOnce(new Error('Network error'));

    render(
      <FederationCommunityPicker
        isOpen={true}
        onClose={vi.fn()}
        onSelect={vi.fn()}
      />,
    );

    // Wait for loading to finish
    await waitFor(() => {
      expect(screen.queryByRole('status', { name: /loading/i, hidden: true })).not.toBeInTheDocument();
    });
    expect(screen.queryByText('Berlin Caring Network')).not.toBeInTheDocument();
  });

  it('calls onClose when cancel button is pressed', async () => {
    mockGet.mockResolvedValueOnce({ success: true, data: { peers: PEERS } });
    const onClose = vi.fn();

    render(
      <FederationCommunityPicker
        isOpen={true}
        onClose={onClose}
        onSelect={vi.fn()}
      />,
    );

    await waitFor(() => {
      expect(screen.getByText('Berlin Caring Network')).toBeInTheDocument();
    });

    // Cancel button
    const cancelBtn = screen.getByRole('button', { name: /cancel/i });
    fireEvent.click(cancelBtn);

    expect(onClose).toHaveBeenCalled();
  });

  it('selects a peer on card click and enables the Select button', async () => {
    mockGet.mockResolvedValueOnce({ success: true, data: { peers: PEERS } });

    render(
      <FederationCommunityPicker
        isOpen={true}
        onClose={vi.fn()}
        onSelect={vi.fn()}
      />,
    );

    await waitFor(() => {
      expect(screen.getByText('Berlin Caring Network')).toBeInTheDocument();
    });

    // Select button should be disabled before picking a peer
    const selectBtn = screen.getByRole('button', { name: /select/i });
    expect(selectBtn).toBeDisabled();

    // Click the peer card (the Card is pressable)
    fireEvent.click(screen.getByText('Berlin Caring Network'));

    await waitFor(() => {
      expect(selectBtn).not.toBeDisabled();
    });
  });

  it('calls onSelect with the chosen peer and onClose when Select is confirmed', async () => {
    mockGet.mockResolvedValueOnce({ success: true, data: { peers: PEERS } });
    const onSelect = vi.fn();
    const onClose = vi.fn();

    render(
      <FederationCommunityPicker
        isOpen={true}
        onClose={onClose}
        onSelect={onSelect}
      />,
    );

    await waitFor(() => {
      expect(screen.getByText('Berlin Caring Network')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByText('Berlin Caring Network'));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /select/i })).not.toBeDisabled();
    });

    fireEvent.click(screen.getByRole('button', { name: /select/i }));

    expect(onSelect).toHaveBeenCalledWith(PEERS[0]);
    expect(onClose).toHaveBeenCalled();
  });

  it('filters peers by search query', async () => {
    mockGet.mockResolvedValueOnce({ success: true, data: { peers: PEERS } });

    render(
      <FederationCommunityPicker
        isOpen={true}
        onClose={vi.fn()}
        onSelect={vi.fn()}
      />,
    );

    await waitFor(() => {
      expect(screen.getByText('Berlin Caring Network')).toBeInTheDocument();
    });

    const searchInput = screen.getByRole('searchbox');
    fireEvent.change(searchInput, { target: { value: 'munich' } });

    await waitFor(() => {
      expect(screen.queryByText('Berlin Caring Network')).not.toBeInTheDocument();
    });
    expect(screen.getByText('Munich Helpers')).toBeInTheDocument();
  });
});
