// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for GroupFilesTab.
 */

import { beforeEach, describe, it, expect, vi } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { api } from '@/lib/api';

vi.mock('react-i18next', () => ({
  initReactI18next: { type: '3rdParty', init: vi.fn() },
  useTranslation: () => ({
    t: (key: string, fallbackOrOptions?: string | { name?: string }) => {
      if (key === 'files.download_aria' && typeof fallbackOrOptions === 'object') {
        return `Download ${fallbackOrOptions.name}`;
      }
      return typeof fallbackOrOptions === 'string' ? fallbackOrOptions : key;
    },
  }),
}));

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    upload: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
  },
}));

vi.mock('@/contexts', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn() }),
}));

vi.mock('@/lib/helpers', () => ({
  formatRelativeTime: () => 'just now',
}));

import { GroupFilesTab } from '../GroupFilesTab';

describe('GroupFilesTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(api.get).mockImplementation(async (url: string) => {
      if (url.includes('/folders')) {
        return { success: true, data: [] };
      }

      return {
        success: true,
        data: {
          items: [
            {
              id: 10,
              group_id: 1,
              file_name: 'meeting-notes.pdf',
              file_path: 'groups/1/meeting-notes.pdf',
              file_type: 'application/pdf',
              file_size: 1024,
              uploaded_by: 42,
              uploader_name: 'Avery Member',
              uploader_avatar: null,
              folder: null,
              description: null,
              created_at: '2026-05-01T12:00:00Z',
            },
          ],
          cursor: null,
          has_more: false,
        },
      };
    });
  });

  it('loads and renders group files', async () => {
    render(<GroupFilesTab groupId={1} isAdmin={false} />);

    expect(await screen.findByText('meeting-notes.pdf')).toBeInTheDocument();
    expect(screen.getByText('Avery Member')).toBeInTheDocument();
  });

  it('downloads through the API client with auth headers', async () => {
    const user = userEvent.setup();
    render(<GroupFilesTab groupId={1} isAdmin={false} />);

    await user.click(await screen.findByLabelText('Download meeting-notes.pdf'));

    expect(api.download).toHaveBeenCalledWith('/v2/groups/1/files/10/download', {
      filename: 'meeting-notes.pdf',
    });
  });

  it('shows delete actions only for admins or the uploader', async () => {
    const { rerender } = render(
      <GroupFilesTab groupId={1} isAdmin={false} currentUserId={7} />,
    );

    await screen.findByText('meeting-notes.pdf');
    expect(screen.queryByLabelText('File actions')).not.toBeInTheDocument();

    rerender(<GroupFilesTab groupId={1} isAdmin={false} currentUserId={42} />);

    await waitFor(() => {
      expect(screen.getByLabelText('File actions')).toBeInTheDocument();
    });
  });
});
