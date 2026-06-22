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
const mockNavigate = vi.hoisted(() => vi.fn());
const mockListBadges = vi.hoisted(() => vi.fn());
const mockDeleteBadge = vi.hoisted(() => vi.fn());

// ── Module mocks ──────────────────────────────────────────────────────────────
vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  }),
);

vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

vi.mock('../../api/adminApi', () => ({
  adminGamification: {
    listBadges: mockListBadges,
    deleteBadge: mockDeleteBadge,
  },
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

import { CustomBadges } from './CustomBadges';

// ── Fixtures ──────────────────────────────────────────────────────────────────
const CUSTOM_BADGE_1 = {
  id: 1,
  key: 'helper',
  name: 'Helper Badge',
  description: 'Given to helpful members',
  icon: 'star',
  type: 'custom' as const,
  awarded_count: 5,
};

const CUSTOM_BADGE_2 = {
  id: 2,
  key: 'pioneer',
  name: 'Pioneer',
  description: '',
  icon: 'flag',
  type: 'custom' as const,
  awarded_count: 0,
};

const BUILTIN_BADGE = {
  id: 3,
  key: 'first_post',
  name: 'First Post',
  description: 'First listing posted',
  icon: 'pen',
  type: 'built_in' as const,
  awarded_count: 100,
};

describe('CustomBadges', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Default: two custom + one built-in
    mockListBadges.mockResolvedValue({
      success: true,
      data: [CUSTOM_BADGE_1, CUSTOM_BADGE_2, BUILTIN_BADGE],
    });
  });

  // ── Loading state ──────────────────────────────────────────────────────────
  it('shows aria-busy loading skeletons while fetching', () => {
    mockListBadges.mockReturnValue(new Promise(() => {}));
    render(<CustomBadges />);

    const loadingEls = screen.getAllByRole('status');
    const busy = loadingEls.find(el => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeInTheDocument();
  });

  // ── Populated state ────────────────────────────────────────────────────────
  it('renders only custom badges (filters out built_in)', async () => {
    render(<CustomBadges />);

    await waitFor(() => {
      expect(screen.getByText('Helper Badge')).toBeInTheDocument();
      expect(screen.getByText('Pioneer')).toBeInTheDocument();
    });

    // Built-in badge must NOT appear
    expect(screen.queryByText('First Post')).not.toBeInTheDocument();
  });

  it('renders badge descriptions when present', async () => {
    render(<CustomBadges />);

    await waitFor(() => {
      expect(screen.getByText('Given to helpful members')).toBeInTheDocument();
    });
  });

  it('loading spinner is gone after fetch completes', async () => {
    render(<CustomBadges />);

    await waitFor(() => {
      const busy = screen.queryAllByRole('status').find(
        el => el.getAttribute('aria-busy') === 'true',
      );
      expect(busy).toBeUndefined();
    });
  });

  // ── Empty state ────────────────────────────────────────────────────────────
  it('shows empty state "No custom badges" when there are no custom badges', async () => {
    mockListBadges.mockResolvedValue({
      success: true,
      data: [BUILTIN_BADGE], // only built-in
    });
    render(<CustomBadges />);

    // real en translation: "No custom badges"
    await waitFor(() => {
      expect(screen.getByText('No custom badges')).toBeInTheDocument();
    });
  });

  it('shows empty state "No custom badges" when data array is empty', async () => {
    mockListBadges.mockResolvedValue({ success: true, data: [] });
    render(<CustomBadges />);

    await waitFor(() => {
      expect(screen.getByText('No custom badges')).toBeInTheDocument();
    });
  });

  // ── Error state ────────────────────────────────────────────────────────────
  it('shows error toast when listBadges returns success=false', async () => {
    mockListBadges.mockResolvedValue({ success: false });
    render(<CustomBadges />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  // ── Delete flow ────────────────────────────────────────────────────────────
  it('opens a confirm modal when a delete button is clicked', async () => {
    render(<CustomBadges />);

    await waitFor(() => {
      expect(screen.getByText('Helper Badge')).toBeInTheDocument();
    });

    // real en translation for aria-label: "Delete {{name}}" → "Delete Helper Badge"
    const deleteBtns = screen.getAllByRole('button', {
      name: /^delete /i,
    });
    expect(deleteBtns.length).toBeGreaterThan(0);
    await userEvent.click(deleteBtns[0]);

    // real en translation: "Delete Badge"
    expect(screen.getByText('Delete Badge')).toBeInTheDocument();
  });

  it('calls deleteBadge and shows success toast on confirmation', async () => {
    mockDeleteBadge.mockResolvedValue({ success: true });
    render(<CustomBadges />);

    await waitFor(() => {
      expect(screen.getByText('Helper Badge')).toBeInTheDocument();
    });

    const deleteBtns = screen.getAllByRole('button', { name: /^delete /i });
    await userEvent.click(deleteBtns[0]);

    // Confirm button in the modal — real en translation: "Delete"
    // Multiple Delete buttons exist; pick the one inside the modal confirm
    const allDeleteBtns = screen.getAllByRole('button', { name: /^delete$/i });
    // Click the last one (the confirm button in the modal footer)
    await userEvent.click(allDeleteBtns[allDeleteBtns.length - 1]);

    await waitFor(() => {
      expect(mockDeleteBadge).toHaveBeenCalledWith(CUSTOM_BADGE_1.id);
    });

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when deleteBadge fails', async () => {
    mockDeleteBadge.mockResolvedValue({ success: false });
    render(<CustomBadges />);

    await waitFor(() => {
      expect(screen.getByText('Helper Badge')).toBeInTheDocument();
    });

    const deleteBtns = screen.getAllByRole('button', { name: /^delete /i });
    await userEvent.click(deleteBtns[0]);

    const allDeleteBtns = screen.getAllByRole('button', { name: /^delete$/i });
    await userEvent.click(allDeleteBtns[allDeleteBtns.length - 1]);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  // ── Create badge navigation ────────────────────────────────────────────────
  it('renders a Create Badge link', async () => {
    render(<CustomBadges />);

    await waitFor(() => {
      expect(screen.getByText('Helper Badge')).toBeInTheDocument();
    });

    // The "Create Badge" link wraps a Button
    expect(screen.getByRole('link')).toBeInTheDocument();
  });
});
