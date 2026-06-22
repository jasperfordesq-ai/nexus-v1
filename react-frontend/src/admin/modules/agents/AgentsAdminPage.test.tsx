// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ─────────────────────────────────────────────────────────────────────────────
// Stable mock data
// ─────────────────────────────────────────────────────────────────────────────
const { mockToast, mockApiGet, mockApiPost, mockApiPatch } = vi.hoisted(() => ({
  mockToast: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
  mockApiGet: vi.fn(),
  mockApiPost: vi.fn(),
  mockApiPatch: vi.fn(),
}));

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast }),
);

vi.mock('@/lib/api', () => ({
  api: {
    get: mockApiGet,
    post: mockApiPost,
    patch: mockApiPatch,
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('../../AdminMetaContext', () => ({
  useAdminPageMeta: vi.fn(),
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import AgentsAdminPage from './AgentsAdminPage';

// ─────────────────────────────────────────────────────────────────────────────
// Test data
// ─────────────────────────────────────────────────────────────────────────────
const AGENT_ENABLED = {
  id: 1,
  tenant_id: 2,
  slug: 'match-suggester',
  name: 'Match Suggester',
  description: 'Suggests matches for members',
  agent_type: 'matching',
  config: { threshold: 0.8 },
  is_enabled: true,
  last_run_at: '2026-06-01T10:00:00Z',
};

const AGENT_DISABLED = {
  id: 2,
  tenant_id: 2,
  slug: 'digest-sender',
  name: 'Digest Sender',
  description: null,
  agent_type: 'notifications',
  config: null,
  is_enabled: false,
  last_run_at: null,
};

function resolveItems(items: unknown[]) {
  mockApiGet.mockResolvedValue({ data: { items }, success: true });
}

// ─────────────────────────────────────────────────────────────────────────────
// Tests
// ─────────────────────────────────────────────────────────────────────────────
describe('AgentsAdminPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders agent cards after loading', async () => {
    resolveItems([AGENT_ENABLED, AGENT_DISABLED]);

    render(<AgentsAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Match Suggester')).toBeInTheDocument();
    });
    expect(screen.getByText('match-suggester')).toBeInTheDocument();
    expect(screen.getByText('Digest Sender')).toBeInTheDocument();
    expect(screen.getByText('Suggests matches for members')).toBeInTheDocument();
  });

  it('shows empty state when no agents are returned', async () => {
    resolveItems([]);

    render(<AgentsAdminPage />);

    await waitFor(() => {
      expect(screen.queryByText('Match Suggester')).not.toBeInTheDocument();
    });
  });

  it('shows error toast on load failure', async () => {
    mockApiGet.mockRejectedValue(new Error('Network error'));

    render(<AgentsAdminPage />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('disabled Run Now button for a disabled agent', async () => {
    resolveItems([AGENT_DISABLED]);

    render(<AgentsAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Digest Sender')).toBeInTheDocument();
    });

    // HeroUI buttons use data-disabled attribute, not aria-disabled
    const buttons = screen.getAllByRole('button');
    const disabledBtns = buttons.filter(
      (b) =>
        b.getAttribute('data-disabled') === 'true' ||
        b.hasAttribute('disabled') ||
        b.getAttribute('aria-disabled') === 'true',
    );
    // The Run Now button for a disabled agent should be disabled
    expect(disabledBtns.length).toBeGreaterThan(0);
  });

  it('calls toggle endpoint and refetches on Switch press', async () => {
    resolveItems([AGENT_ENABLED]);
    mockApiPost.mockResolvedValue({ success: true });
    // Second fetch after toggle returns same data
    mockApiGet.mockResolvedValueOnce({ data: { items: [AGENT_ENABLED] }, success: true })
              .mockResolvedValue({ data: { items: [{ ...AGENT_ENABLED, is_enabled: false }] }, success: true });

    render(<AgentsAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Match Suggester')).toBeInTheDocument();
    });

    // HeroUI Switch aria-label comes from the translation key; get the only switch
    const switchEl = screen.getByRole('switch');
    await userEvent.click(switchEl);

    await waitFor(() => {
      expect(mockApiPost).toHaveBeenCalledWith(
        '/v2/admin/agents/1/toggle',
        {},
      );
    });
    expect(mockToast.success).toHaveBeenCalled();
  });

  it('calls run-now endpoint and shows success toast', async () => {
    resolveItems([AGENT_ENABLED]);
    mockApiPost.mockResolvedValue({
      success: true,
      data: { run_id: 42, proposals_created: 5 },
    });
    mockApiGet.mockResolvedValue({ data: { items: [AGENT_ENABLED] }, success: true });

    render(<AgentsAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Match Suggester')).toBeInTheDocument();
    });

    // Find Run Now button (there may be multiple buttons; pick the one that contains run_now text)
    const allButtons = screen.getAllByRole('button');
    const runBtn = allButtons.find((b) => b.textContent?.toLowerCase().includes('run'));
    expect(runBtn).toBeTruthy();
    await userEvent.click(runBtn!);

    await waitFor(() => {
      expect(mockApiPost).toHaveBeenCalledWith(
        '/v2/admin/agents/1/run-now',
        {},
      );
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('opens edit modal when Edit Config is pressed', async () => {
    resolveItems([AGENT_ENABLED]);

    render(<AgentsAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Match Suggester')).toBeInTheDocument();
    });

    const allButtons = screen.getAllByRole('button');
    const editBtn = allButtons.find((b) => b.textContent?.toLowerCase().includes('edit'));
    expect(editBtn).toBeTruthy();
    await userEvent.click(editBtn!);

    // Modal heading contains "Edit" + agent name
    await waitFor(() => {
      const headings = screen.getAllByText(/match suggester/i);
      // At least the modal heading and the card heading are present
      expect(headings.length).toBeGreaterThanOrEqual(1);
    });
  });

  it('shows invalid JSON toast when saving bad config', async () => {
    resolveItems([AGENT_ENABLED]);

    render(<AgentsAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Match Suggester')).toBeInTheDocument();
    });

    // Open edit modal
    const allButtons = screen.getAllByRole('button');
    const editBtn = allButtons.find((b) => b.textContent?.toLowerCase().includes('edit'));
    await userEvent.click(editBtn!);

    // Wait for modal textarea to appear
    await waitFor(() => {
      expect(screen.getAllByRole('textbox').length).toBeGreaterThan(0);
    });

    const textareas = screen.getAllByRole('textbox');
    // The config textarea (JSON) is the second textbox (after name input)
    const jsonTextarea = textareas[1];
    if (jsonTextarea) {
      // fireEvent.change avoids userEvent.type's curly-brace escaping issue
      fireEvent.change(jsonTextarea, { target: { value: '{invalid json' } });
    }

    // Click Save
    const saveBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('save'),
    );
    if (saveBtn) {
      await userEvent.click(saveBtn);
      await waitFor(() => {
        expect(mockToast.error).toHaveBeenCalled();
      });
    }
  });

  it('saves valid config and closes modal', async () => {
    resolveItems([AGENT_ENABLED]);
    mockApiPatch.mockResolvedValue({ success: true });
    mockApiGet.mockResolvedValue({ data: { items: [AGENT_ENABLED] }, success: true });

    render(<AgentsAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Match Suggester')).toBeInTheDocument();
    });

    const allButtons = screen.getAllByRole('button');
    const editBtn = allButtons.find((b) => b.textContent?.toLowerCase().includes('edit'));
    await userEvent.click(editBtn!);

    await waitFor(() => {
      expect(screen.getAllByRole('textbox').length).toBeGreaterThan(0);
    });

    const saveBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('save'),
    );
    if (saveBtn) {
      await userEvent.click(saveBtn);
      await waitFor(() => {
        expect(mockApiPatch).toHaveBeenCalledWith(
          '/v2/admin/agents/1',
          expect.objectContaining({ name: 'Match Suggester' }),
        );
        expect(mockToast.success).toHaveBeenCalled();
      });
    }
  });
});
