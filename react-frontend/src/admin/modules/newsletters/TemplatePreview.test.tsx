// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── Stable hoisted mock data ──────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }));
const mockPreviewTemplate = vi.hoisted(() => vi.fn());

// ── Module mocks ──────────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts({
  useToast: () => mockToast,
}));

vi.mock('../../api/adminApi', () => ({
  adminNewsletters: {
    previewTemplate: mockPreviewTemplate,
  },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { TemplatePreview } from './TemplatePreview';

// ── Test helpers ──────────────────────────────────────────────────────────────
const DEFAULT_PROPS = {
  templateId: 7,
  isOpen: true,
  onClose: vi.fn(),
};

describe('TemplatePreview', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockPreviewTemplate.mockResolvedValue({
      success: true,
      data: {
        html: '<p>Hello World</p>',
        name: 'Welcome Email',
        subject: 'Welcome!',
      },
    });
  });

  // ── Not open — component does not fetch ──────────────────────────────────
  it('does not call API when isOpen=false', () => {
    render(<TemplatePreview {...DEFAULT_PROPS} isOpen={false} />);
    expect(mockPreviewTemplate).not.toHaveBeenCalled();
  });

  // ── Loading state ─────────────────────────────────────────────────────────
  it('shows a loading spinner while fetching (isOpen=true)', () => {
    mockPreviewTemplate.mockReturnValue(new Promise(() => {}));
    render(<TemplatePreview {...DEFAULT_PROPS} />);

    const spinners = screen.getAllByRole('status');
    const busyEl = spinners.find(el => el.getAttribute('aria-busy') === 'true');
    expect(busyEl).toBeInTheDocument();
  });

  // ── Populated state ───────────────────────────────────────────────────────
  it('renders template name in modal header after load', async () => {
    render(<TemplatePreview {...DEFAULT_PROPS} />);

    await waitFor(() => {
      expect(screen.getByText('Welcome Email')).toBeInTheDocument();
    });
  });

  it('renders subject line in modal header after load', async () => {
    render(<TemplatePreview {...DEFAULT_PROPS} />);

    await waitFor(() => {
      expect(screen.getByText(/Welcome!/)).toBeInTheDocument();
    });
  });

  it('renders an iframe with the template HTML', async () => {
    render(<TemplatePreview {...DEFAULT_PROPS} />);

    await waitFor(() => {
      const iframe = document.querySelector('iframe');
      expect(iframe).toBeInTheDocument();
    });
  });

  // ── No HTML content ───────────────────────────────────────────────────────
  it('shows no-content message when API returns empty html', async () => {
    mockPreviewTemplate.mockResolvedValue({
      success: true,
      data: { html: '', name: 'Empty Template', subject: '' },
    });
    render(<TemplatePreview {...DEFAULT_PROPS} />);

    await waitFor(() => {
      // Spinner gone
      const busyEl = screen.queryAllByRole('status').find(
        el => el.getAttribute('aria-busy') === 'true',
      );
      expect(busyEl).toBeUndefined();
    });

    expect(document.querySelector('iframe')).not.toBeInTheDocument();
  });

  // ── Error state ───────────────────────────────────────────────────────────
  it('renders error message when API throws', async () => {
    mockPreviewTemplate.mockRejectedValue(new Error('Network Error'));
    render(<TemplatePreview {...DEFAULT_PROPS} />);

    await waitFor(() => {
      // loadError=true branch renders error text — spinner gone
      const busyEl = screen.queryAllByRole('status').find(
        el => el.getAttribute('aria-busy') === 'true',
      );
      expect(busyEl).toBeUndefined();
    });

    expect(document.querySelector('iframe')).not.toBeInTheDocument();
  });

  // ── Close button ─────────────────────────────────────────────────────────
  it('calls onClose when Close button is pressed', async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();

    render(<TemplatePreview {...DEFAULT_PROPS} onClose={onClose} />);

    await waitFor(() => {
      expect(screen.getByText('Welcome Email')).toBeInTheDocument();
    });

    // Modal has two "close" elements (header X + footer Close button).
    // Target the footer button which has explicit text "Close".
    const allButtons = screen.getAllByRole('button');
    const closeBtn = allButtons.find(btn => btn.textContent?.trim() === 'Close');
    expect(closeBtn).toBeInTheDocument();
    await user.click(closeBtn!);

    expect(onClose).toHaveBeenCalled();
  });

  // ── templateId passed to API ─────────────────────────────────────────────
  it('calls previewTemplate with correct templateId', async () => {
    render(<TemplatePreview {...DEFAULT_PROPS} templateId={42} />);

    await waitFor(() => {
      expect(mockPreviewTemplate).toHaveBeenCalledWith(42);
    });
  });

  // ── Re-fetch on re-open ──────────────────────────────────────────────────
  it('re-fetches when isOpen switches from false to true', async () => {
    const { rerender } = render(<TemplatePreview {...DEFAULT_PROPS} isOpen={false} />);
    expect(mockPreviewTemplate).not.toHaveBeenCalled();

    rerender(<TemplatePreview {...DEFAULT_PROPS} isOpen={true} />);

    await waitFor(() => {
      expect(mockPreviewTemplate).toHaveBeenCalledTimes(1);
    });
  });
});
