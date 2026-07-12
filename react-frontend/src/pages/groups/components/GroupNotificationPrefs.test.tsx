// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
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

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  }),
);

import { GroupNotificationPrefs } from './GroupNotificationPrefs';

const DEFAULT_PREFS = {
  frequency: 'instant' as const,
  email_enabled: true,
  push_enabled: true,
  updated_at: null,
};

describe('GroupNotificationPrefs', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('does not render modal content when isOpen=false', () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: DEFAULT_PREFS });
    render(
      <GroupNotificationPrefs groupId={1} isOpen={false} onClose={vi.fn()} />,
    );
    // When closed the modal is not visible; no API call should be made
    expect(api.get).not.toHaveBeenCalled();
  });

  it('loads prefs from API when opened', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: DEFAULT_PREFS });
    render(
      <GroupNotificationPrefs groupId={42} isOpen={true} onClose={vi.fn()} />,
    );
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(
        '/v2/groups/42/notification-prefs',
        expect.objectContaining({ signal: expect.any(AbortSignal) }),
      );
    });
  });

  it('shows a truthful retry state instead of editable defaults when loading fails', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({
        success: false,
        code: 'HTTP_500',
        error: 'Raw server copy',
      })
      .mockResolvedValueOnce({ success: true, data: DEFAULT_PREFS });

    render(
      <GroupNotificationPrefs groupId={1} isOpen={true} onClose={vi.fn()} />,
    );

    expect(await screen.findByRole('alert')).toBeInTheDocument();
    expect(screen.queryAllByRole('radio')).toHaveLength(0);
    expect(screen.getByRole('button', { name: /try again/i })).toBeInTheDocument();
    expect(screen.queryByText('Raw server copy')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /try again/i }));
    await waitFor(() => expect(screen.getByRole('radio', { name: /Instant/i })).toBeChecked());
  });

  it('shows spinner while loading', () => {
    // Never resolve so loading stays true
    vi.mocked(api.get).mockImplementation(() => new Promise(() => {}));
    render(
      <GroupNotificationPrefs groupId={1} isOpen={true} onClose={vi.fn()} />,
    );
    // HeroUI renders portal content; the modal body contains a status div with aria-busy.
    // The toast container also carries role="status", so use getAllByRole and find the
    // one with aria-busy="true".
    const statusEls = screen.getAllByRole('status');
    const loadingEl = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(loadingEl).toBeInTheDocument();
  });

  it('renders radio buttons after load completes', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: DEFAULT_PREFS });
    render(
      <GroupNotificationPrefs groupId={1} isOpen={true} onClose={vi.fn()} />,
    );
    await waitFor(() => {
      expect(screen.getAllByRole('radio').length).toBeGreaterThan(0);
    });
  });

  it('disables channel toggles while the group is muted', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: DEFAULT_PREFS });
    render(
      <GroupNotificationPrefs groupId={1} isOpen={true} onClose={vi.fn()} />,
    );
    const muted = await screen.findByRole('radio', { name: /Muted/i });
    fireEvent.click(muted);

    await waitFor(() => {
      for (const channel of screen.getAllByRole('switch')) {
        expect(channel).toBeDisabled();
      }
    });
  });

  it('renders Save and Cancel buttons after load', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: DEFAULT_PREFS });
    render(
      <GroupNotificationPrefs groupId={1} isOpen={true} onClose={vi.fn()} />,
    );
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /cancel/i })).toBeInTheDocument();
    });
  });

  it('calls api.put with current prefs on Save', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: DEFAULT_PREFS });
    vi.mocked(api.put).mockResolvedValue({
      success: true,
      data: {
        message: 'Preferences updated',
        preferences: { ...DEFAULT_PREFS, updated_at: '2026-07-11T16:21:00.000000Z' },
      },
    });
    const onClose = vi.fn();

    render(
      <GroupNotificationPrefs groupId={7} isOpen={true} onClose={onClose} />,
    );

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() => {
      expect(api.put).toHaveBeenCalledWith(
        '/v2/groups/7/notification-prefs',
        expect.objectContaining({ frequency: 'instant', email_enabled: true, push_enabled: true }),
      );
    });
  });

  it('shows success toast and calls onClose after successful save', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: DEFAULT_PREFS });
    vi.mocked(api.put).mockResolvedValue({
      success: true,
      data: {
        message: 'Preferences updated',
        preferences: { ...DEFAULT_PREFS, updated_at: '2026-07-11T16:21:00.000000Z' },
      },
    });
    const onClose = vi.fn();

    render(
      <GroupNotificationPrefs groupId={7} isOpen={true} onClose={onClose} />,
    );

    await waitFor(() => screen.getByRole('button', { name: /save/i }));
    fireEvent.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
      expect(onClose).toHaveBeenCalled();
    });
  });

  it('shows error toast when save fails', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: DEFAULT_PREFS });
    vi.mocked(api.put).mockResolvedValue({ success: false });
    const onClose = vi.fn();

    render(
      <GroupNotificationPrefs groupId={7} isOpen={true} onClose={onClose} />,
    );

    await waitFor(() => screen.getByRole('button', { name: /save/i }));
    fireEvent.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
    expect(mockToast.success).not.toHaveBeenCalled();
    expect(onClose).not.toHaveBeenCalled();
  });

  it('calls onClose when Cancel is clicked', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: DEFAULT_PREFS });
    const onClose = vi.fn();

    render(
      <GroupNotificationPrefs groupId={1} isOpen={true} onClose={onClose} />,
    );

    await waitFor(() => screen.getByRole('button', { name: /cancel/i }));
    fireEvent.click(screen.getByRole('button', { name: /cancel/i }));

    expect(onClose).toHaveBeenCalledTimes(1);
  });
});
