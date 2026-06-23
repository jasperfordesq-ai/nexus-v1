// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── API mock ────────────────────────────────────────────────────────────────
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

// ─── Toast + contexts ────────────────────────────────────────────────────────
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

// EndorseButton imports useToast from '@/contexts' (not ToastContext directly)
vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  }),
);

// ─── Stub @/components/ui ────────────────────────────────────────────────────
vi.mock('@/components/ui', async () => (await import('@/test/uiMock')).uiMock);

// ─── Default props ────────────────────────────────────────────────────────────
const defaultProps = {
  memberId: 5,
  skillName: 'Carpentry',
  endorsementCount: 3,
  isEndorsed: false,
  onEndorsementChange: vi.fn(),
};

// ─────────────────────────────────────────────────────────────────────────────
describe('EndorseButton', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.post.mockResolvedValue({ success: true });
    mockApi.delete.mockResolvedValue({ success: true });
  });

  it('renders the Endorse button when not endorsed', async () => {
    const { EndorseButton } = await import('./EndorseButton');
    render(<EndorseButton {...defaultProps} isEndorsed={false} />);
    // Button text includes 'endorse' key; uiMock renders button children
    const btn = screen.getByRole('button');
    expect(btn).toBeInTheDocument();
  });

  it('shows endorsement count when > 0', async () => {
    const { EndorseButton } = await import('./EndorseButton');
    render(<EndorseButton {...defaultProps} endorsementCount={7} />);
    expect(screen.getByText(/7/)).toBeInTheDocument();
  });

  it('does not show count when endorsement count is 0', async () => {
    const { EndorseButton } = await import('./EndorseButton');
    render(<EndorseButton {...defaultProps} endorsementCount={0} />);
    // Count should not appear when 0
    expect(screen.queryByText('(0)')).not.toBeInTheDocument();
  });

  it('calls POST endpoint when endorsing', async () => {
    const { EndorseButton } = await import('./EndorseButton');
    const { userEvent } = await import('@/test/test-utils');
    const user = userEvent.setup();

    render(<EndorseButton {...defaultProps} isEndorsed={false} />);
    await user.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/members/5/endorse',
        { skill_name: 'Carpentry' },
      );
    });
  });

  it('calls DELETE endpoint when removing endorsement', async () => {
    const { EndorseButton } = await import('./EndorseButton');
    const { userEvent } = await import('@/test/test-utils');
    const user = userEvent.setup();

    render(<EndorseButton {...defaultProps} isEndorsed={true} />);
    await user.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(mockApi.delete).toHaveBeenCalledWith(
        '/v2/members/5/endorse',
        expect.objectContaining({ body: JSON.stringify({ skill_name: 'Carpentry' }) }),
      );
    });
  });

  it('increments count optimistically when endorsing', async () => {
    const { EndorseButton } = await import('./EndorseButton');
    const { userEvent } = await import('@/test/test-utils');
    const user = userEvent.setup();

    // Delay API response so we can observe optimistic update
    mockApi.post.mockImplementation(() => new Promise((resolve) => setTimeout(() => resolve({ success: true }), 100)));

    render(<EndorseButton {...defaultProps} endorsementCount={3} isEndorsed={false} />);
    await user.click(screen.getByRole('button'));

    // Optimistic: count becomes 4
    expect(screen.getByText(/4/)).toBeInTheDocument();
  });

  it('decrements count optimistically when removing endorsement', async () => {
    const { EndorseButton } = await import('./EndorseButton');
    const { userEvent } = await import('@/test/test-utils');
    const user = userEvent.setup();

    mockApi.delete.mockImplementation(() => new Promise((resolve) => setTimeout(() => resolve({ success: true }), 100)));

    render(<EndorseButton {...defaultProps} endorsementCount={3} isEndorsed={true} />);
    await user.click(screen.getByRole('button'));

    // Optimistic: count becomes 2
    expect(screen.getByText(/2/)).toBeInTheDocument();
  });

  it('shows error toast and reverts when POST fails', async () => {
    mockApi.post.mockResolvedValue({ success: false, error: 'Failed to endorse' });
    const { EndorseButton } = await import('./EndorseButton');
    const { userEvent } = await import('@/test/test-utils');
    const user = userEvent.setup();

    render(<EndorseButton {...defaultProps} endorsementCount={3} isEndorsed={false} />);
    await user.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
    // Count reverts to 3
    expect(screen.getByText(/3/)).toBeInTheDocument();
  });

  it('shows error toast when API throws', async () => {
    mockApi.post.mockRejectedValue(new Error('Network error'));
    const { EndorseButton } = await import('./EndorseButton');
    const { userEvent } = await import('@/test/test-utils');
    const user = userEvent.setup();

    render(<EndorseButton {...defaultProps} isEndorsed={false} />);
    await user.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('calls onEndorsementChange callback after successful endorse', async () => {
    const onEndorsementChange = vi.fn();
    const { EndorseButton } = await import('./EndorseButton');
    const { userEvent } = await import('@/test/test-utils');
    const user = userEvent.setup();

    render(
      <EndorseButton
        {...defaultProps}
        isEndorsed={false}
        onEndorsementChange={onEndorsementChange}
      />,
    );
    await user.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(onEndorsementChange).toHaveBeenCalled();
    });
  });

  it('renders compact variant inside a Tooltip wrapper', async () => {
    const { EndorseButton } = await import('./EndorseButton');
    render(<EndorseButton {...defaultProps} compact={true} />);
    // Compact renders a Tooltip (uiMock div) wrapping the button
    const btn = screen.getByRole('button');
    expect(btn).toBeInTheDocument();
  });

  it('compact variant shows count when > 0', async () => {
    const { EndorseButton } = await import('./EndorseButton');
    render(<EndorseButton {...defaultProps} compact={true} endorsementCount={5} />);
    expect(screen.getByText('5')).toBeInTheDocument();
  });

  it('compact variant calls endorse on click', async () => {
    const { EndorseButton } = await import('./EndorseButton');
    const { userEvent } = await import('@/test/test-utils');
    const user = userEvent.setup();

    render(<EndorseButton {...defaultProps} compact={true} isEndorsed={false} />);
    await user.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/members/5/endorse',
        { skill_name: 'Carpentry' },
      );
    });
  });
});
