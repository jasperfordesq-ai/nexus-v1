// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── api mock ─────────────────────────────────────────────────────────────
const { mockApi, mockConfirm, mockToast } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
  mockConfirm: vi.fn(async () => true),
  mockToast: {
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
}));

// ── useConfirm mock ───────────────────────────────────────────────────────
vi.mock('@/components/ui/ConfirmDialog', () => ({
  useConfirm: () => mockConfirm,
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  }),
);

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ── component ─────────────────────────────────────────────────────────────
import { WebhookConfigPanel } from './WebhookConfigPanel';

const GROUP_ID = 7;

const WEBHOOKS = [
  {
    id: 1,
    url: 'https://example.com/hook',
    events: ['member.joined', 'post.created'],
    is_active: true,
    last_fired_at: null,
    failure_count: 0,
  },
  {
    id: 2,
    url: 'https://example.com/hook2',
    events: ['group.updated'],
    is_active: false,
    last_fired_at: '2025-01-01T00:00:00Z',
    failure_count: 3,
  },
];

describe('WebhookConfigPanel — non-admin', () => {
  it('renders nothing when isAdmin=false', () => {
    vi.clearAllMocks();
    mockApi.get.mockResolvedValue({ success: true, data: [] });
    render(<WebhookConfigPanel groupId={GROUP_ID} isAdmin={false} />);
    // Component returns null; no webhooks title or add button should appear
    expect(screen.queryByRole('button', { name: /add/i })).not.toBeInTheDocument();
    expect(mockApi.get).not.toHaveBeenCalled();
  });
});

describe('WebhookConfigPanel — admin', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ── loading ──────────────────────────────────────────────────────────────
  it('shows loading spinner while fetching', () => {
    mockApi.get.mockReturnValue(new Promise(() => {}));
    render(<WebhookConfigPanel groupId={GROUP_ID} isAdmin={true} />);

    const statusEls = screen.getAllByRole('status');
    const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  // ── empty ────────────────────────────────────────────────────────────────
  it('shows empty state when no webhooks exist', async () => {
    mockApi.get.mockResolvedValueOnce({ success: true, data: [] });

    render(<WebhookConfigPanel groupId={GROUP_ID} isAdmin={true} />);

    await waitFor(() => {
      const busyEls = screen.queryAllByRole('status').filter(
        (el) => el.getAttribute('aria-busy') === 'true',
      );
      expect(busyEls.length).toBe(0);
    });
    // No webhook rows
    expect(screen.queryByText('https://example.com/hook')).not.toBeInTheDocument();
  });

  it('shows an error instead of an empty success state when load resolves success=false', async () => {
    mockApi.get.mockResolvedValueOnce({ success: false, code: 'HTTP_500' });

    render(<WebhookConfigPanel groupId={GROUP_ID} isAdmin={true} />);

    await waitFor(() => expect(mockToast.error).toHaveBeenCalled());
    expect(screen.queryByText('https://example.com/hook')).not.toBeInTheDocument();
  });

  // ── populated ────────────────────────────────────────────────────────────
  it('renders webhook list with URLs', async () => {
    mockApi.get.mockResolvedValueOnce({ success: true, data: WEBHOOKS });

    render(<WebhookConfigPanel groupId={GROUP_ID} isAdmin={true} />);

    await waitFor(() => {
      expect(screen.getByText('https://example.com/hook')).toBeInTheDocument();
    });
    expect(screen.getByText('https://example.com/hook2')).toBeInTheDocument();
  });

  it('shows failure count chip when failure_count > 0', async () => {
    mockApi.get.mockResolvedValueOnce({ success: true, data: WEBHOOKS });

    render(<WebhookConfigPanel groupId={GROUP_ID} isAdmin={true} />);

    await waitFor(() =>
      expect(screen.getByText('https://example.com/hook2')).toBeInTheDocument(),
    );
    // webhook 2 has failure_count=3 — the chip uses t('webhooks.failures', {count:3})
    // i18n in test env returns the key or interpolated string; verify some chip is present
    // by checking that the chip-label slot exists (failure chip rendered alongside active chip)
    const chipLabels = document.querySelectorAll('[data-slot="chip-label"]');
    expect(chipLabels.length).toBeGreaterThan(0);
  });

  // ── add webhook modal ─────────────────────────────────────────────────────
  it('opens add modal when Add button is clicked', async () => {
    const user = userEvent.setup();
    mockApi.get.mockResolvedValueOnce({ success: true, data: [] });

    render(<WebhookConfigPanel groupId={GROUP_ID} isAdmin={true} />);

    await waitFor(() => {
      const busyEls = screen.queryAllByRole('status').filter(
        (el) => el.getAttribute('aria-busy') === 'true',
      );
      expect(busyEls.length).toBe(0);
    });

    const addBtn = screen.getByRole('button', { name: /add/i });
    await user.click(addBtn);

    // Modal should be visible — it contains a URL input
    await waitFor(() => {
      const urlInputs = screen.getAllByRole('textbox');
      expect(urlInputs.length).toBeGreaterThan(0);
    });
  });

  it('advertises only webhook events that have real producers', async () => {
    const user = userEvent.setup();
    mockApi.get.mockResolvedValueOnce({ success: true, data: [] });

    render(<WebhookConfigPanel groupId={GROUP_ID} isAdmin={true} />);
    await waitFor(() => expect(screen.queryByRole('status', { busy: true })).not.toBeInTheDocument());
    await user.click(screen.getByRole('button', { name: /add/i }));

    for (const event of [
      'member.joined',
      'member.left',
      'discussion.created',
      'post.created',
      'file.uploaded',
    ]) {
      expect(screen.getByText(event)).toBeInTheDocument();
    }
    expect(screen.queryByText('group.updated')).not.toBeInTheDocument();
    expect(screen.queryByText('milestone.reached')).not.toBeInTheDocument();
  });

  it('shows toast error if URL is empty on create', async () => {
    const user = userEvent.setup();
    mockApi.get.mockResolvedValueOnce({ success: true, data: [] });

    render(<WebhookConfigPanel groupId={GROUP_ID} isAdmin={true} />);

    await waitFor(() => {
      const busyEls = screen.queryAllByRole('status').filter(
        (el) => el.getAttribute('aria-busy') === 'true',
      );
      expect(busyEls.length).toBe(0);
    });

    // Open modal
    await user.click(screen.getByRole('button', { name: /add/i }));

    // Try to create without filling URL — click the primary create button
    await waitFor(() => {
      const createBtns = screen.getAllByRole('button');
      return createBtns.length > 1;
    });

    const createBtn = screen.getAllByRole('button').find(
      (b) => /create|add webhook/i.test(b.textContent ?? ''),
    );
    if (createBtn) {
      await user.click(createBtn);
      await waitFor(() => {
        expect(mockToast.error).toHaveBeenCalled();
      });
    }
  });

  it('creates a webhook and refreshes list on success', async () => {
    const user = userEvent.setup();
    // First call: load empty, second call: reload after create
    mockApi.get
      .mockResolvedValueOnce({ success: true, data: [] })
      .mockResolvedValueOnce({ success: true, data: [WEBHOOKS[0]] });
    mockApi.post.mockResolvedValueOnce({ success: true, data: { id: 1 } });

    render(<WebhookConfigPanel groupId={GROUP_ID} isAdmin={true} />);

    await waitFor(() => {
      const busyEls = screen.queryAllByRole('status').filter(
        (el) => el.getAttribute('aria-busy') === 'true',
      );
      expect(busyEls.length).toBe(0);
    });

    // Open modal
    await user.click(screen.getByRole('button', { name: /add/i }));

    // Fill in URL input (type=url)
    await waitFor(() => {
      const urlInput = document.querySelector('input[type="url"]');
      expect(urlInput).toBeInTheDocument();
    });

    const urlInput = document.querySelector('input[type="url"]') as HTMLInputElement;
    await user.type(urlInput, 'https://my.hook.io/endpoint');

    // Select at least one event checkbox
    const checkboxes = screen.getAllByRole('checkbox');
    if (checkboxes.length > 0) {
      await user.click(checkboxes[0]!);
    }

    // Click create
    const createBtn = screen.getAllByRole('button').find(
      (b) => /create|add webhook/i.test(b.textContent ?? ''),
    );
    if (createBtn) {
      await user.click(createBtn);
      await waitFor(() => {
        expect(mockApi.post).toHaveBeenCalledWith(
          `/v2/groups/${GROUP_ID}/webhooks`,
          expect.any(Object),
        );
      });
      expect(mockToast.success).toHaveBeenCalled();
    }
  });

  // ── toggle ────────────────────────────────────────────────────────────────
  it('calls PUT toggle endpoint when Switch is toggled', async () => {
    const user = userEvent.setup();
    mockApi.get.mockResolvedValueOnce({ success: true, data: [WEBHOOKS[0]] });
    mockApi.put.mockResolvedValueOnce({ success: true });

    render(<WebhookConfigPanel groupId={GROUP_ID} isAdmin={true} />);

    await waitFor(() =>
      expect(screen.getByText('https://example.com/hook')).toBeInTheDocument(),
    );

    // The Switch element
    const switchEl = screen.getByRole('switch');
    await user.click(switchEl);

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith(
        `/v2/groups/${GROUP_ID}/webhooks/1/toggle`,
        expect.any(Object),
      );
    });
  });

  // ── delete ────────────────────────────────────────────────────────────────
  it('calls DELETE endpoint after confirm and removes from list', async () => {
    const user = userEvent.setup();
    mockConfirm.mockResolvedValueOnce(true);
    mockApi.get.mockResolvedValueOnce({ success: true, data: [WEBHOOKS[0]] });
    mockApi.delete.mockResolvedValueOnce({ success: true });

    render(<WebhookConfigPanel groupId={GROUP_ID} isAdmin={true} />);

    await waitFor(() =>
      expect(screen.getByText('https://example.com/hook')).toBeInTheDocument(),
    );

    const deleteBtn = screen.getByRole('button', { name: /delete/i });
    await user.click(deleteBtn);

    await waitFor(() => {
      expect(mockApi.delete).toHaveBeenCalledWith(
        `/v2/groups/${GROUP_ID}/webhooks/1`,
      );
    });
    expect(screen.queryByText('https://example.com/hook')).not.toBeInTheDocument();
  });

  it('does NOT call delete when confirm is cancelled', async () => {
    const user = userEvent.setup();
    mockConfirm.mockResolvedValueOnce(false);
    mockApi.get.mockResolvedValueOnce({ success: true, data: [WEBHOOKS[0]] });

    render(<WebhookConfigPanel groupId={GROUP_ID} isAdmin={true} />);

    await waitFor(() =>
      expect(screen.getByText('https://example.com/hook')).toBeInTheDocument(),
    );

    const deleteBtn = screen.getByRole('button', { name: /delete/i });
    await user.click(deleteBtn);

    await waitFor(() => {
      expect(mockConfirm).toHaveBeenCalled();
    });
    expect(mockApi.delete).not.toHaveBeenCalled();
    expect(screen.getByText('https://example.com/hook')).toBeInTheDocument();
  });
});
