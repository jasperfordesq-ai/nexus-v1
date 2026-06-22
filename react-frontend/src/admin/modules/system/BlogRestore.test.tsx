// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── hoisted mock data ────────────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
}));

const mockBackup = vi.hoisted(() => ({
  id: 1,
  filename: 'blog-backup-2026-06-01.sql',
  created_at: '2026-06-01T00:00:00Z',
  size: '2.4 MB',
}));

// ── mocks ────────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  })
);

vi.mock('../../api/adminApi', () => ({
  adminTools: {
    getBlogBackups: vi.fn(),
    restoreBlogBackup: vi.fn(),
  },
}));

import { adminTools } from '../../api/adminApi';
import { BlogRestore } from './BlogRestore';

// ── helpers ──────────────────────────────────────────────────────────────────
function mockSuccessfulLoad(backups = [mockBackup]) {
  vi.mocked(adminTools.getBlogBackups).mockResolvedValue({
    success: true,
    data: backups,
  } as never);
}

function mockEmptyLoad() {
  vi.mocked(adminTools.getBlogBackups).mockResolvedValue({
    success: true,
    data: [],
  } as never);
}

function mockFailedLoad() {
  vi.mocked(adminTools.getBlogBackups).mockRejectedValue(new Error('Network error'));
}

// ── tests ────────────────────────────────────────────────────────────────────
describe('BlogRestore', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading spinner while fetching backups', () => {
    vi.mocked(adminTools.getBlogBackups).mockReturnValue(new Promise(() => {}));
    render(<BlogRestore />);

    const loadingEl = screen.queryAllByRole('status').find(
      el => el.getAttribute('aria-busy') === 'true'
    );
    expect(loadingEl).toBeInTheDocument();
  });

  it('removes loading spinner once backups load', async () => {
    mockSuccessfulLoad();
    render(<BlogRestore />);

    await waitFor(() => {
      expect(screen.getByText('blog-backup-2026-06-01.sql')).toBeInTheDocument();
    });

    const busyStatus = screen.queryAllByRole('status').find(
      el => el.getAttribute('aria-busy') === 'true'
    );
    expect(busyStatus).toBeUndefined();
  });

  it('renders backup filename and size after load', async () => {
    mockSuccessfulLoad();
    render(<BlogRestore />);

    await waitFor(() => {
      expect(screen.getByText('blog-backup-2026-06-01.sql')).toBeInTheDocument();
    });
    expect(screen.getByText(/2\.4 MB/)).toBeInTheDocument();
  });

  it('shows empty state when no backups available', async () => {
    mockEmptyLoad();
    render(<BlogRestore />);

    await waitFor(() => {
      expect(screen.queryByText('blog-backup-2026-06-01.sql')).not.toBeInTheDocument();
    });
  });

  it('shows error toast when fetch fails', async () => {
    mockFailedLoad();
    render(<BlogRestore />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows caution warning banner', async () => {
    mockSuccessfulLoad();
    render(<BlogRestore />);

    await waitFor(() => {
      expect(screen.getByText('blog-backup-2026-06-01.sql')).toBeInTheDocument();
    });

    // Warning banner text from i18n key system.use_with_caution
    // It will render the key or actual text; either way it's present
    expect(screen.queryByText(/caution/i) ?? screen.queryByText(/system\.use_with_caution/)).not.toBeNull();
  });

  it('clicking Restore opens confirm modal', async () => {
    mockSuccessfulLoad();
    const user = userEvent.setup();
    render(<BlogRestore />);

    await waitFor(() => {
      expect(screen.getByText('blog-backup-2026-06-01.sql')).toBeInTheDocument();
    });

    const restoreBtn = screen.getByRole('button', { name: /restore/i });
    await user.click(restoreBtn);

    // ConfirmModal becomes open — a second button (confirm) appears
    await waitFor(() => {
      expect(screen.getAllByRole('button').length).toBeGreaterThan(1);
    });
  });

  it('calls restoreBlogBackup with correct id when confirmed', async () => {
    // First load: returns backup. Second load (after restore): returns empty.
    vi.mocked(adminTools.getBlogBackups)
      .mockResolvedValueOnce({ success: true, data: [mockBackup] } as never)
      .mockResolvedValueOnce({ success: true, data: [] } as never);
    vi.mocked(adminTools.restoreBlogBackup).mockResolvedValue({
      success: true,
      data: { restored_count: 12 },
    } as never);

    const user = userEvent.setup();
    render(<BlogRestore />);

    await waitFor(() => {
      expect(screen.getByText('blog-backup-2026-06-01.sql')).toBeInTheDocument();
    });

    const restoreBtn = screen.getByRole('button', { name: /restore/i });
    await user.click(restoreBtn);

    // Wait for modal to open then click the confirm button (last "restore" button)
    await waitFor(() => {
      const btns = screen.getAllByRole('button');
      expect(btns.length).toBeGreaterThan(1);
    });

    // Find the modal confirm button — it is the one inside the ConfirmModal
    const allRestoreBtns = screen.getAllByRole('button', { name: /restore/i });
    await user.click(allRestoreBtns[allRestoreBtns.length - 1]);

    await waitFor(() => {
      expect(adminTools.restoreBlogBackup).toHaveBeenCalledWith(mockBackup.id);
    });
    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast if restore API fails', async () => {
    mockSuccessfulLoad();
    vi.mocked(adminTools.restoreBlogBackup).mockResolvedValue({
      success: false,
    } as never);

    const user = userEvent.setup();
    render(<BlogRestore />);

    await waitFor(() => {
      expect(screen.getByText('blog-backup-2026-06-01.sql')).toBeInTheDocument();
    });

    const restoreBtn = screen.getByRole('button', { name: /restore/i });
    await user.click(restoreBtn);

    await waitFor(() => {
      expect(screen.getAllByRole('button').length).toBeGreaterThan(1);
    });

    const allRestoreBtns = screen.getAllByRole('button', { name: /restore/i });
    await user.click(allRestoreBtns[allRestoreBtns.length - 1]);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});
