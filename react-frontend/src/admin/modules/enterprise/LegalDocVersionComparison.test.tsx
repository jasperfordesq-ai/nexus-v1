// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── Stable hoisted mock data ──────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
}));
const mockCompareVersions = vi.hoisted(() => vi.fn());

// ── Module mocks ──────────────────────────────────────────────────────────────
vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  }),
);

// LegalDocVersionComparison imports useToast from the direct context path
vi.mock('@/contexts/ToastContext', () => ({
  useToast: () => mockToast,
  ToastProvider: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('../../api/adminApi', () => ({
  adminLegalDocs: {
    compareVersions: mockCompareVersions,
  },
}));

vi.mock('@/lib/sanitize', () => ({
  sanitizeRichText: (html: string) => html,
}));

import LegalDocVersionComparison from './LegalDocVersionComparison';

// ── Fixtures ──────────────────────────────────────────────────────────────────
const makeVersion = (overrides: Partial<{
  id: number;
  is_current: boolean;
  created_at: string;
  effective_date: string | null;
  summary_of_changes: string | null;
}> = {}) => ({
  id: overrides.id ?? 1,
  document_id: 10,
  version_number: '1.0',
  version_label: null,
  content: 'content',
  content_plain: 'content',
  summary_of_changes: overrides.summary_of_changes ?? null,
  effective_date: overrides.effective_date ?? null,
  published_at: null,
  is_draft: false,
  is_current: overrides.is_current ?? false,
  notification_sent: false,
  notification_sent_at: null,
  created_by: 1,
  created_by_name: 'Admin',
  published_by: null,
  published_by_name: null,
  created_at: overrides.created_at ?? '2024-01-01T00:00:00Z',
});

const SAMPLE_COMPARISON = {
  version1: makeVersion({ id: 1, is_current: true }),
  version2: makeVersion({ id: 2 }),
  diff_html: '<div>changed text here</div>',
  changes_count: 3,
};

const DEFAULT_PROPS = {
  documentId: 10,
  version1Id: 1,
  version2Id: 2,
  onClose: vi.fn(),
};

describe('LegalDocVersionComparison', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockCompareVersions.mockResolvedValue({ success: true, data: SAMPLE_COMPARISON });
  });

  // ── Loading state ─────────────────────────────────────────────────────────
  it('shows an aria-busy loading indicator while fetching', () => {
    mockCompareVersions.mockReturnValue(new Promise(() => {}));
    render(<LegalDocVersionComparison {...DEFAULT_PROPS} />);

    const spinners = screen.getAllByRole('status');
    const busy = spinners.find(el => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeInTheDocument();
  });

  // ── Populated state ───────────────────────────────────────────────────────
  it('renders diff content after successful load', async () => {
    render(<LegalDocVersionComparison {...DEFAULT_PROPS} />);

    await waitFor(() => {
      expect(screen.getByText('changed text here')).toBeInTheDocument();
    });
  });

  it('shows the "Current" chip for the version marked is_current', async () => {
    render(<LegalDocVersionComparison {...DEFAULT_PROPS} />);

    await waitFor(() => {
      expect(screen.getByText('Current')).toBeInTheDocument();
    });
  });

  it('shows "Changes Detected" when changes_count > 0', async () => {
    render(<LegalDocVersionComparison {...DEFAULT_PROPS} />);

    await waitFor(() => {
      expect(screen.getByText('Changes Detected')).toBeInTheDocument();
    });
  });

  it('shows "No changes" when changes_count is 0', async () => {
    mockCompareVersions.mockResolvedValue({
      success: true,
      data: { ...SAMPLE_COMPARISON, changes_count: 0 },
    });
    render(<LegalDocVersionComparison {...DEFAULT_PROPS} />);

    await waitFor(() => {
      expect(screen.getByText('No changes')).toBeInTheDocument();
    });
  });

  it('renders summary_of_changes when present on version1', async () => {
    const v1WithSummary = makeVersion({
      id: 1,
      is_current: true,
      summary_of_changes: 'Breaking change in clause 3',
    });
    mockCompareVersions.mockResolvedValue({
      success: true,
      data: { ...SAMPLE_COMPARISON, version1: v1WithSummary },
    });
    render(<LegalDocVersionComparison {...DEFAULT_PROPS} />);

    await waitFor(() => {
      expect(screen.getByText('Breaking change in clause 3')).toBeInTheDocument();
    });
  });

  it('renders "Content Comparison" heading after load', async () => {
    render(<LegalDocVersionComparison {...DEFAULT_PROPS} />);

    await waitFor(() => {
      expect(screen.getByText('Content Comparison')).toBeInTheDocument();
    });
  });

  // ── Loading clears ─────────────────────────────────────────────────────────
  it('removes the loading spinner after fetch completes', async () => {
    render(<LegalDocVersionComparison {...DEFAULT_PROPS} />);

    await waitFor(() => {
      const busy = screen.queryAllByRole('status').find(
        el => el.getAttribute('aria-busy') === 'true',
      );
      expect(busy).toBeUndefined();
    });
  });

  // ── Error state ───────────────────────────────────────────────────────────
  it('shows "Failed to load" fallback when API returns success=false', async () => {
    mockCompareVersions.mockResolvedValue({ success: false, error: 'Not found' });
    render(<LegalDocVersionComparison {...DEFAULT_PROPS} />);

    await waitFor(() => {
      expect(screen.getByText('Failed to load')).toBeInTheDocument();
    });
  });

  it('calls toast.error and shows failed-to-load when API throws', async () => {
    mockCompareVersions.mockRejectedValue(new Error('Network'));
    render(<LegalDocVersionComparison {...DEFAULT_PROPS} />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  // ── Close button ──────────────────────────────────────────────────────────
  it('calls onClose when close button is pressed', async () => {
    const onClose = vi.fn();
    render(<LegalDocVersionComparison {...DEFAULT_PROPS} onClose={onClose} />);

    await waitFor(() => {
      // spinner gone
      const busy = screen.queryAllByRole('status').find(
        el => el.getAttribute('aria-busy') === 'true',
      );
      expect(busy).toBeUndefined();
    });

    const closeBtn = screen.getByRole('button', { name: /close/i });
    await userEvent.click(closeBtn);

    expect(onClose).toHaveBeenCalled();
  });

  // ── API call args ─────────────────────────────────────────────────────────
  it('calls compareVersions with the correct document and version ids', async () => {
    render(<LegalDocVersionComparison documentId={99} version1Id={7} version2Id={8} onClose={vi.fn()} />);

    await waitFor(() => {
      expect(mockCompareVersions).toHaveBeenCalledWith(99, 7, 8);
    });
  });
});
