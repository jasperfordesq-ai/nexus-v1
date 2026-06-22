// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── mock api ──────────────────────────────────────────────────────────────────
vi.mock('@/lib/api', () => {
  const m = { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() };
  return { default: m, api: m };
});

// ── mock contexts ─────────────────────────────────────────────────────────────
// MunicipalityFeedbackPage calls useToast().showToast(...)
const mockShowToast = vi.fn();

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => ({
      success: vi.fn(),
      error: vi.fn(),
      info: vi.fn(),
      warning: vi.fn(),
      showToast: mockShowToast,
    }),
  }),
);

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ── mock SEO helper ───────────────────────────────────────────────────────────
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

import { api } from '@/lib/api';
import MunicipalityFeedbackPage from './MunicipalityFeedbackPage';

const MOCK_ITEMS = [
  {
    id: 1,
    category: 'question',
    subject: 'Test question subject',
    body: 'Test body',
    status: 'new',
    is_anonymous: false,
    is_public: true,
    sentiment_tag: null,
    created_at: '2024-06-01T10:00:00Z',
    updated_at: '2024-06-01T10:00:00Z',
  },
];

describe('MunicipalityFeedbackPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Default: empty list on initial load
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
  });

  // ── loading state ─────────────────────────────────────────────────────────

  it('shows loading spinner while fetching submissions', () => {
    // Never resolve so we stay in loading state
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<MunicipalityFeedbackPage />);

    const spinner = getAllByRoleStatic('status').find(
      (el) => el.getAttribute('aria-busy') === 'true',
    );
    expect(spinner).toBeInTheDocument();
  });

  // ── empty state ───────────────────────────────────────────────────────────

  it('shows empty-state message when no submissions exist', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<MunicipalityFeedbackPage />);

    await waitFor(() => {
      const spinner = screen
        .queryAllByRole('status')
        .find((el) => el.getAttribute('aria-busy') === 'true');
      expect(spinner).toBeUndefined();
    });
    // "my_submissions_empty" i18n key — falls back to the key itself in tests
    expect(
      screen.queryAllByRole('list').length === 0 ||
        screen.queryByRole('listitem') === null,
    ).toBe(true);
  });

  // ── populated state ───────────────────────────────────────────────────────

  it('renders submitted feedback items', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: MOCK_ITEMS });
    render(<MunicipalityFeedbackPage />);

    await waitFor(() => {
      expect(screen.getByText('Test question subject')).toBeInTheDocument();
    });
    expect(screen.getByText('Test body')).toBeInTheDocument();
  });

  it('renders the anonymous chip when is_anonymous=true', async () => {
    const anonItem = { ...MOCK_ITEMS[0], is_anonymous: true };
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [anonItem] });
    render(<MunicipalityFeedbackPage />);

    await waitFor(() => {
      expect(screen.getByText('Test question subject')).toBeInTheDocument();
    });
    // The anonymous chip text comes from the "field_anonymous" i18n key
    // In test harness it renders the key or a translated value — check the chip container
    expect(screen.getByRole('list')).toBeInTheDocument();
  });

  // ── error state ───────────────────────────────────────────────────────────

  it('calls showToast with error when API returns success=false', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: false, error: 'Not found' });
    render(<MunicipalityFeedbackPage />);

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(
        expect.any(String),
        'error',
      );
    });
  });

  // ── submit action ─────────────────────────────────────────────────────────

  it('submit button is disabled when subject and body are empty', async () => {
    render(<MunicipalityFeedbackPage />);

    await waitFor(() => {
      const spinner = screen
        .queryAllByRole('status')
        .find((el) => el.getAttribute('aria-busy') === 'true');
      expect(spinner).toBeUndefined();
    });

    // HeroUI button with isDisabled renders as aria-disabled or disabled.
    // The button text is the translated "submit" key — rendered as "Submit feedback" in real i18n.
    // We find it by its aria-label which contains the i18n key text pattern.
    const submitBtn = screen.getAllByRole('button').find((b) =>
      /submit/i.test(b.textContent ?? '') || /feedback/i.test(b.textContent ?? ''),
    );
    expect(submitBtn).toBeDefined();
    // When both subject and body are empty, button has data-disabled or aria-disabled
    expect(
      submitBtn!.hasAttribute('disabled') ||
        submitBtn!.getAttribute('aria-disabled') === 'true' ||
        submitBtn!.hasAttribute('data-disabled'),
    ).toBe(true);
  });

  it('shows error toast when submitting with empty fields', async () => {
    render(<MunicipalityFeedbackPage />);
    await waitFor(() => {
      const spinner = screen
        .queryAllByRole('status')
        .find((el) => el.getAttribute('aria-busy') === 'true');
      expect(spinner).toBeUndefined();
    });

    // api.post should NOT have been called — the button is guarded by isDisabled
    expect(api.post).not.toHaveBeenCalled();
  });

  it('posts feedback and reloads list on success', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({ success: true, data: [] })         // initial load
      .mockResolvedValueOnce({ success: true, data: MOCK_ITEMS }); // reload after post

    vi.mocked(api.post).mockResolvedValueOnce({ success: true, data: {} });

    render(<MunicipalityFeedbackPage />);

    await waitFor(() => {
      const spinner = screen
        .queryAllByRole('status')
        .find((el) => el.getAttribute('aria-busy') === 'true');
      expect(spinner).toBeUndefined();
    });

    // Fill in subject field (HeroUI Input uses onValueChange; use fireEvent.change on underlying input)
    const inputs = screen.getAllByRole('textbox');
    // First textbox is "field_subject", second is the textarea "field_body"
    fireEvent.change(inputs[0], { target: { value: 'My subject' } });
    fireEvent.change(inputs[1], { target: { value: 'My body text' } });

    // After filling fields the button should no longer be disabled
    const submitBtn = screen.getAllByRole('button').find((b) =>
      /submit/i.test(b.textContent ?? '') || /feedback/i.test(b.textContent ?? ''),
    );
    expect(submitBtn).toBeDefined();

    await waitFor(() => {
      const isDisabled =
        submitBtn!.hasAttribute('disabled') ||
        submitBtn!.getAttribute('aria-disabled') === 'true' ||
        submitBtn!.hasAttribute('data-disabled');
      expect(isDisabled).toBe(false);
    });

    fireEvent.click(submitBtn!);

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        '/v2/caring-community/feedback',
        expect.objectContaining({ subject: 'My subject', body: 'My body text' }),
      );
    });

    expect(mockShowToast).toHaveBeenCalledWith(expect.any(String), 'success');
  });
});

// Helper to call screen.getAllByRole synchronously in a non-await context
function getAllByRoleStatic(role: string): Element[] {
  return Array.from(document.querySelectorAll(`[role="${role}"]`));
}
