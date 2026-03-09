// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for GroupFilesTab
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (_key: string, fallback: string, _opts?: object) => fallback ?? _key,
  }),
}));

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [] }),
    upload: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
  },
  API_BASE: 'https://api.example.com',
  tokenManager: {
    getAccessToken: vi.fn().mockReturnValue('mock-token'),
  },
}));

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, name: 'Test User', email: 'test@example.com', tenant_id: 2 },
    isAuthenticated: true,
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  })),
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
  ),
}));

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description }: { title: string; description?: string }) => (
    <div data-testid="empty-state">
      <div>{title}</div>
      {description && <div>{description}</div>}
    </div>
  ),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/lib/helpers', () => ({
  formatRelativeTime: (date: string) => `relative-${date}`,
}));

import { GroupFilesTab } from '../GroupFilesTab';
import { api } from '@/lib/api';

const makeFile = (id = 1, mimeType = 'application/pdf') => ({
  id,
  file_name: `file-${id}.pdf`,
  original_name: `Document ${id}.pdf`,
  file_size: 102400, // 100 KB
  mime_type: mimeType,
  uploaded_by: { id: 1, name: 'Test User' },
  download_count: 3,
  created_at: '2026-03-01T10:00:00Z',
});

describe('GroupFilesTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the Files heading', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<GroupFilesTab groupId={1} isAdmin={false} isMember={true} />);
    await waitFor(() => {
      expect(screen.getByText('Files')).toBeInTheDocument();
    });
  });

  it('shows empty state for member when no files', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<GroupFilesTab groupId={1} isAdmin={false} isMember={true} />);
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
      expect(screen.getByText('No files yet')).toBeInTheDocument();
      expect(screen.getByText('Upload files to share with the group')).toBeInTheDocument();
    });
  });

  it('shows non-member empty state description when not a member', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<GroupFilesTab groupId={1} isAdmin={false} isMember={false} />);
    await waitFor(() => {
      expect(screen.getByText('No files have been shared in this group')).toBeInTheDocument();
    });
  });

  it('renders Upload button for members', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<GroupFilesTab groupId={1} isAdmin={false} isMember={true} />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Upload/i })).toBeInTheDocument();
    });
  });

  it('does not render Upload button for non-members', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<GroupFilesTab groupId={1} isAdmin={false} isMember={false} />);
    await waitFor(() => {
      expect(screen.queryByRole('button', { name: /^Upload$/i })).not.toBeInTheDocument();
    });
  });

  it('renders file list with names and metadata', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { files: [makeFile(1), makeFile(2, 'image/png')] },
    });
    render(<GroupFilesTab groupId={1} isAdmin={false} isMember={true} />);
    await waitFor(() => {
      expect(screen.getByText('Document 1.pdf')).toBeInTheDocument();
      expect(screen.getByText('Document 2.pdf')).toBeInTheDocument();
    });
    // Check for file size display
    expect(screen.getAllByText('100.0 KB')).toHaveLength(2);
  });

  it('renders Download button for each file', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { files: [makeFile(1)] },
    });
    render(<GroupFilesTab groupId={1} isAdmin={false} isMember={true} />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Download file/i })).toBeInTheDocument();
    });
  });

  it('renders Delete button for admin on any file', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { files: [makeFile(1)] },
    });
    render(<GroupFilesTab groupId={1} isAdmin={true} isMember={true} />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Delete file/i })).toBeInTheDocument();
    });
  });

  it('shows download count chip when download_count is present', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { files: [makeFile(1)] },
    });
    render(<GroupFilesTab groupId={1} isAdmin={false} isMember={true} />);
    await waitFor(() => {
      expect(screen.getByText('3 downloads')).toBeInTheDocument();
    });
  });

  it('shows drag and drop zone for members', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<GroupFilesTab groupId={1} isAdmin={false} isMember={true} />);
    await waitFor(() => {
      expect(screen.getByText(/Drag and drop files here/)).toBeInTheDocument();
    });
  });

  it('does not show drag and drop zone for non-members', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<GroupFilesTab groupId={1} isAdmin={false} isMember={false} />);
    await waitFor(() => {
      expect(screen.queryByText(/Drag and drop files here/)).not.toBeInTheDocument();
    });
  });
});
