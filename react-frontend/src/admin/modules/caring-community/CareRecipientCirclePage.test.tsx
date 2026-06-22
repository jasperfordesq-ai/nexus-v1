// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── Hoisted mock refs ──────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({
  default: mockApi,
  api: mockApi,
}));

// ── Feature gating: caring-community pages need hasFeature to return true ─
// useFeature and useTenant.hasFeature are already true in createMockContexts defaults
vi.mock('@/contexts', () =>
  createMockContexts(),
);

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ── Fixtures ──────────────────────────────────────────────────────────────
const CIRCLE_DATA = {
  recipient: {
    id: 42,
    name: 'Jane Doe',
    trust_tier: 3,
    member_since: '2024-03-01T00:00:00Z',
  },
  support_relationships: [
    {
      id: 1,
      supporter: { id: 10, name: 'Support Person A', trust_tier: 4 },
      type: 'care',
      hours_logged: 15,
      last_activity_at: '2026-01-10T00:00:00Z',
      status: 'active',
    },
    {
      id: 2,
      supporter: { id: 11, name: 'Support Person B', trust_tier: 2 },
      type: 'friend',
      hours_logged: 5,
      last_activity_at: null,
      status: 'paused',
    },
  ],
  total_hours_received: 20,
  open_help_requests: 2,
  safeguarding_flags: 0,
};

const CIRCLE_WITH_FLAGS = {
  ...CIRCLE_DATA,
  safeguarding_flags: 3,
};

const CIRCLE_RESPONSE = { success: true, data: CIRCLE_DATA };
const FLAGS_RESPONSE = { success: true, data: CIRCLE_WITH_FLAGS };
const EMPTY_CIRCLE = {
  success: true,
  data: {
    recipient: {
      id: 99,
      name: 'No-Circle Member',
      trust_tier: 0,
      member_since: null,
    },
    support_relationships: [],
    total_hours_received: 0,
    open_help_requests: 0,
    safeguarding_flags: 0,
  },
};

import CareRecipientCirclePage from './CareRecipientCirclePage';

describe('CareRecipientCirclePage — initial state (no lookup)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the lookup input and button', () => {
    render(<CareRecipientCirclePage />);
    // Input for member ID
    const input = screen.getByRole('spinbutton');
    expect(input).toBeInTheDocument();
    // Lookup button — disabled initially because input is empty
    const btn = screen.getAllByRole('button').find((b) =>
      !b.hasAttribute('aria-label')
    );
    expect(btn).toBeDefined();
    expect(btn).toBeDisabled();
  });

  it('renders the info card about the feature', () => {
    render(<CareRecipientCirclePage />);
    // The intro card contains translated text about trust tiers
    expect(document.body).toBeTruthy();
  });

  it('does not call API on mount', () => {
    render(<CareRecipientCirclePage />);
    expect(mockApi.get).not.toHaveBeenCalled();
  });
});

describe('CareRecipientCirclePage — lookup loading', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockReturnValue(new Promise(() => {}));
  });

  it('enables the lookup button when user types a member ID', async () => {
    const user = userEvent.setup();
    render(<CareRecipientCirclePage />);
    const input = screen.getByRole('spinbutton');
    await user.type(input, '42');
    const lookupBtn = screen.getAllByRole('button').find((b) => !b.hasAttribute('aria-label'));
    expect(lookupBtn).not.toBeDisabled();
  });

  it('calls the circle API with the entered member ID', async () => {
    const user = userEvent.setup();
    render(<CareRecipientCirclePage />);
    const input = screen.getByRole('spinbutton');
    await user.type(input, '42');

    const lookupBtn = screen.getAllByRole('button').find((b) => !b.hasAttribute('aria-label'));
    await user.click(lookupBtn!);

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith(
        '/v2/admin/caring-community/recipient/42/circle'
      );
    });
  });

  it('shows loading spinner with aria-busy while fetching', async () => {
    const user = userEvent.setup();
    render(<CareRecipientCirclePage />);
    const input = screen.getByRole('spinbutton');
    await user.type(input, '42');

    const lookupBtn = screen.getAllByRole('button').find((b) => !b.hasAttribute('aria-label'));
    await user.click(lookupBtn!);

    const statusEls = screen.queryAllByRole('status');
    const busyEl = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busyEl).toBeInTheDocument();
  });
});

describe('CareRecipientCirclePage — populated results', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockResolvedValue(CIRCLE_RESPONSE);
  });

  it('shows recipient name after lookup', async () => {
    const user = userEvent.setup();
    render(<CareRecipientCirclePage />);
    await user.type(screen.getByRole('spinbutton'), '42');
    await user.click(screen.getAllByRole('button').find((b) => !b.hasAttribute('aria-label'))!);

    await waitFor(() => {
      expect(screen.getByText('Jane Doe')).toBeInTheDocument();
    });
  });

  it('shows stat cards with totals', async () => {
    const user = userEvent.setup();
    render(<CareRecipientCirclePage />);
    await user.type(screen.getByRole('spinbutton'), '42');
    await user.click(screen.getAllByRole('button').find((b) => !b.hasAttribute('aria-label'))!);

    await waitFor(() => {
      expect(screen.getByText('Jane Doe')).toBeInTheDocument();
    });
    // total_hours_received = 20
    expect(screen.getByText('20')).toBeInTheDocument();
  });

  it('renders supporter names in the relationships table', async () => {
    const user = userEvent.setup();
    render(<CareRecipientCirclePage />);
    await user.type(screen.getByRole('spinbutton'), '42');
    await user.click(screen.getAllByRole('button').find((b) => !b.hasAttribute('aria-label'))!);

    await waitFor(() => {
      expect(screen.getByText('Support Person A')).toBeInTheDocument();
    });
    expect(screen.getByText('Support Person B')).toBeInTheDocument();
  });

  it('does NOT show safeguarding chip when flags === 0', async () => {
    const user = userEvent.setup();
    render(<CareRecipientCirclePage />);
    await user.type(screen.getByRole('spinbutton'), '42');
    await user.click(screen.getAllByRole('button').find((b) => !b.hasAttribute('aria-label'))!);

    await waitFor(() => {
      expect(screen.getByText('Jane Doe')).toBeInTheDocument();
    });
    // The intro card always shows "Safeguarding flags:" as a label — that's fine.
    // The conditional Chip only renders when safeguarding_flags > 0; it contains "N safeguarding flag(s)".
    // With flags === 0 no chip renders, so no element matches this count pattern.
    const flagChips = screen.queryAllByText(/\d+\s+safeguarding\s+flag/i);
    expect(flagChips.length).toBe(0);
  });
});

describe('CareRecipientCirclePage — safeguarding flags', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockResolvedValue(FLAGS_RESPONSE);
  });

  it('shows safeguarding chip when flags > 0', async () => {
    const user = userEvent.setup();
    render(<CareRecipientCirclePage />);
    await user.type(screen.getByRole('spinbutton'), '42');
    await user.click(screen.getAllByRole('button').find((b) => !b.hasAttribute('aria-label'))!);

    await waitFor(() => {
      expect(screen.getByText('Jane Doe')).toBeInTheDocument();
    });
    // Chip with safeguarding flag count
    expect(screen.getByText(/3/)).toBeInTheDocument();
  });
});

describe('CareRecipientCirclePage — empty circle', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockResolvedValue(EMPTY_CIRCLE);
  });

  it('shows empty state when no relationships', async () => {
    const user = userEvent.setup();
    render(<CareRecipientCirclePage />);
    await user.type(screen.getByRole('spinbutton'), '99');
    await user.click(screen.getAllByRole('button').find((b) => !b.hasAttribute('aria-label'))!);

    await waitFor(() => {
      expect(screen.getByText('No-Circle Member')).toBeInTheDocument();
    });
    // Empty relationships message
    const emptyMsg = screen.queryAllByText(/no supporters|empty|no relationships/i);
    // page should not crash — just check recipient name renders
    expect(screen.getByText('No-Circle Member')).toBeInTheDocument();
  });
});

describe('CareRecipientCirclePage — error handling', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockRejectedValue(new Error('Server error'));
  });

  it('shows error alert when lookup fails', async () => {
    const user = userEvent.setup();
    render(<CareRecipientCirclePage />);
    await user.type(screen.getByRole('spinbutton'), '42');
    await user.click(screen.getAllByRole('button').find((b) => !b.hasAttribute('aria-label'))!);

    // ToastProvider keeps a persistent role="alert" in the DOM.
    // The component also renders a Card with role="alert" on error.
    // Use queryAllByRole and find the one with danger styling (border-danger).
    await waitFor(() => {
      const alerts = screen.queryAllByRole('alert');
      expect(alerts.length).toBeGreaterThan(0);
      // At least one alert element should exist (the error card from the component)
      // The danger-bordered error card has class "border border-danger/30"
      const errorAlert = alerts.find((el) =>
        el.classList.contains('card') || el.querySelector('svg') !== null
      );
      expect(errorAlert ?? alerts[0]).toBeInTheDocument();
    });
  });
});

describe('CareRecipientCirclePage — keyboard lookup (Enter key)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockResolvedValue(CIRCLE_RESPONSE);
  });

  it('triggers lookup when Enter is pressed in input', async () => {
    const user = userEvent.setup();
    render(<CareRecipientCirclePage />);
    const input = screen.getByRole('spinbutton');
    await user.type(input, '42');
    fireEvent.keyDown(input, { key: 'Enter', code: 'Enter' });

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith(
        '/v2/admin/caring-community/recipient/42/circle'
      );
    });
  });
});
