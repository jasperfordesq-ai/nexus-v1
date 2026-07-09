// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/contexts', () => createMockContexts());

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return {
    ...actual,
    useParams: vi.fn(() => ({ token: 'checkin-token-xyz' })),
  };
});

import { api } from '@/lib/api';
import { useParams } from 'react-router-dom';
import CheckInVerifyPage from './CheckInVerifyPage';

describe('CheckInVerifyPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(useParams).mockReturnValue({ token: 'checkin-token-xyz' });
  });

  it('renders the confirm state with a confirm button when a token is present', () => {
    render(<CheckInVerifyPage />);
    expect(screen.getByRole('button')).toBeInTheDocument();
  });

  it('renders error state immediately when no token is provided', () => {
    vi.mocked(useParams).mockReturnValue({ token: undefined });
    render(<CheckInVerifyPage />);
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('posts the verify endpoint on confirm', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ success: true, data: { user: { id: 1, name: 'Alex' } } });

    render(<CheckInVerifyPage />);
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        expect.stringContaining('checkin/verify/checkin-token-xyz'),
        expect.any(Object),
      );
    });
  });

  it('announces a check-in failure via an assertive alert region (WCAG 4.1.3)', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ success: false, errors: [{ code: 'FORBIDDEN' }] });

    render(<CheckInVerifyPage />);
    fireEvent.click(screen.getByRole('button'));

    const alert = await screen.findByRole('alert');
    expect(alert).toBeInTheDocument();
  });

  it('moves focus to the live result region when check-in resolves', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ success: true, data: { user: { id: 1, name: 'Alex' } } });

    render(<CheckInVerifyPage />);
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(document.activeElement?.getAttribute('aria-live')).toBeTruthy();
    });
  });

  it('renders a done link regardless of state', () => {
    render(<CheckInVerifyPage />);
    expect(screen.getByRole('link')).toBeInTheDocument();
  });
});
