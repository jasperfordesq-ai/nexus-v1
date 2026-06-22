// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── Mock adminGroups API ────────────────────────────────────────────────────
const mockGetPolicies = vi.fn();
const mockSetPolicy = vi.fn();

vi.mock('@/admin/api/adminApi', () => ({
  adminGroups: {
    getPolicies: (...args: unknown[]) => mockGetPolicies(...args),
    setPolicy: (...args: unknown[]) => mockSetPolicy(...args),
  },
}));

// ── Stable toast refs (vi.hoisted pattern) ──────────────────────────────────
const mockToast = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
}));

vi.mock('@/contexts/ToastContext', () => ({
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
  useToast: () => mockToast,
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  }),
);

// ── Sample policy data ───────────────────────────────────────────────────────
const BOOL_POLICY = {
  key: 'allow_posts',
  label: 'Allow posts',
  description: 'Whether members can create posts',
  type: 'boolean' as const,
  value: true,
  category: 'features',
};

const NUM_POLICY = {
  key: 'max_members',
  label: 'Max members',
  description: null,
  type: 'number' as const,
  value: 50,
  category: 'membership',
};

const STR_POLICY = {
  key: 'welcome_msg',
  label: 'Welcome message',
  description: null,
  type: 'string' as const,
  value: 'Hello!',
  category: 'content',
};

import GroupPolicies from './GroupPolicies';

const DEFAULT_PROPS = {
  isOpen: true,
  onClose: vi.fn(),
  typeId: 1,
  typeName: 'Community Group',
};

describe('GroupPolicies — loading', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetPolicies.mockReturnValue(new Promise(() => {})); // never resolves
  });

  it('shows loading text while fetching', () => {
    render(<GroupPolicies {...DEFAULT_PROPS} />);
    // Loading state shows text, not a spinner with aria-busy
    expect(screen.getByText(/loading/i)).toBeInTheDocument();
  });
});

describe('GroupPolicies — populated', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetPolicies.mockResolvedValue({ data: [BOOL_POLICY, NUM_POLICY, STR_POLICY] });
    mockSetPolicy.mockResolvedValue({ success: true });
  });

  it('renders modal header with type name', async () => {
    render(<GroupPolicies {...DEFAULT_PROPS} />);
    await waitFor(() => expect(screen.getByText('Community Group')).toBeInTheDocument());
  });

  it('renders policy labels for all policies', async () => {
    render(<GroupPolicies {...DEFAULT_PROPS} />);
    await waitFor(() => {
      expect(screen.getByText('Allow posts')).toBeInTheDocument();
      expect(screen.getByText('Max members')).toBeInTheDocument();
      expect(screen.getByText('Welcome message')).toBeInTheDocument();
    });
  });

  it('renders boolean policy description', async () => {
    render(<GroupPolicies {...DEFAULT_PROPS} />);
    await waitFor(() => {
      expect(screen.getByText('Whether members can create posts')).toBeInTheDocument();
    });
  });

  it('calls setPolicy and shows success toast when boolean switch toggled', async () => {
    const user = userEvent.setup();
    render(<GroupPolicies {...DEFAULT_PROPS} />);
    await waitFor(() => expect(screen.getByText('Allow posts')).toBeInTheDocument());

    // Switch for the boolean policy
    const switchEl = screen.getByRole('switch');
    await user.click(switchEl);

    await waitFor(() => {
      expect(mockSetPolicy).toHaveBeenCalledWith(1, 'allow_posts', expect.any(Boolean));
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when setPolicy fails', async () => {
    mockSetPolicy.mockResolvedValueOnce({ success: false, error: 'Forbidden' });
    const user = userEvent.setup();
    render(<GroupPolicies {...DEFAULT_PROPS} />);
    await waitFor(() => expect(screen.getByText('Allow posts')).toBeInTheDocument());

    const switchEl = screen.getByRole('switch');
    await user.click(switchEl);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
      expect(mockToast.success).not.toHaveBeenCalled();
    });
  });

  it('calls onClose when Close button pressed', async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();
    render(<GroupPolicies {...DEFAULT_PROPS} onClose={onClose} />);
    await waitFor(() => expect(screen.getByText('Allow posts')).toBeInTheDocument());

    // The ModalFooter Close button has text "Close"; there may be an X icon button too.
    // Pick the one whose accessible name is exactly "close" (i18n: common.close).
    const closeBtns = screen.getAllByRole('button', { name: /close/i });
    // The footer button is the last one with this name
    const footerClose = closeBtns.find((b) => b.textContent?.match(/close/i));
    await user.click(footerClose ?? closeBtns[0]);

    await waitFor(() => expect(onClose).toHaveBeenCalled());
  });
});

describe('GroupPolicies — empty', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetPolicies.mockResolvedValue({ data: [] });
  });

  it('shows empty state when no policies returned', async () => {
    render(<GroupPolicies {...DEFAULT_PROPS} />);
    await waitFor(() => {
      expect(screen.getByText(/no.+policies/i)).toBeInTheDocument();
    });
  });
});

describe('GroupPolicies — error', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetPolicies.mockRejectedValue(new Error('Network error'));
  });

  it('shows error toast on load failure', async () => {
    render(<GroupPolicies {...DEFAULT_PROPS} />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});

describe('GroupPolicies — closed', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('does not fetch policies when isOpen is false', () => {
    render(<GroupPolicies {...DEFAULT_PROPS} isOpen={false} />);
    expect(mockGetPolicies).not.toHaveBeenCalled();
  });
});
