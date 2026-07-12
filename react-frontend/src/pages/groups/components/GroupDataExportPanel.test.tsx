// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

const { requestExport, getExport, downloadExport, toast } = vi.hoisted(() => ({
  requestExport: vi.fn(),
  getExport: vi.fn(),
  downloadExport: vi.fn(),
  toast: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));

vi.mock('../api/dataExport', () => ({
  requestGroupDataExport: requestExport,
  getGroupDataExport: getExport,
  downloadGroupDataExport: downloadExport,
}));
vi.mock('@/contexts', () => createMockContexts({ useToast: () => toast }));

import { GroupDataExportPanel } from './GroupDataExportPanel';

const queued = {
  id: '8dc00f9c-09b7-42f1-a9de-ff246c839843',
  status: 'queued',
  byte_size: null,
  created_at: '2026-07-11T10:00:00Z',
  completed_at: null,
  expires_at: '2026-07-12T10:00:00Z',
  download_url: null,
};

describe('GroupDataExportPanel', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    requestExport.mockResolvedValue(queued);
    getExport.mockResolvedValue({ ...queued, status: 'completed', download_url: '/download' });
    downloadExport.mockResolvedValue(undefined);
  });

  it('is hidden from non-admin viewers', () => {
    render(<GroupDataExportPanel groupId={9} isAdmin={false} />);
    expect(screen.queryByText(/group data export/i)).not.toBeInTheDocument();
  });

  it('requests a queued export then downloads the completed artifact', async () => {
    const user = userEvent.setup();
    render(<GroupDataExportPanel groupId={9} isAdmin />);

    await user.click(screen.getByRole('button', { name: /generate export/i }));
    await waitFor(() => expect(requestExport).toHaveBeenCalledWith(9));
    expect(screen.getByText(/queued/i)).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: /refresh status/i }));
    await waitFor(() => expect(screen.getByRole('button', { name: /download export/i })).toBeInTheDocument());
    await user.click(screen.getByRole('button', { name: /download export/i }));
    expect(downloadExport).toHaveBeenCalledWith(9, queued.id);
  });

  it('surfaces request and status failures truthfully', async () => {
    const user = userEvent.setup();
    requestExport.mockRejectedValueOnce(new Error('denied'));
    render(<GroupDataExportPanel groupId={9} isAdmin />);

    await user.click(screen.getByRole('button', { name: /generate export/i }));
    await waitFor(() => expect(toast.error).toHaveBeenCalled());
  });
});
