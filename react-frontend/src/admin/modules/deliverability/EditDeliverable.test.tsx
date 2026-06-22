// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── Mock react-router-dom (preserve real hooks, override useParams/useNavigate) ──
const mockNavigate = vi.fn();

vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...actual,
    useParams: () => ({ id: '7' }),
    useNavigate: () => mockNavigate,
  };
});

// ── Mock adminDeliverability API ──────────────────────────────────────────────
const mockGet = vi.fn();
const mockUpdate = vi.fn();

vi.mock('../../api/adminApi', () => ({
  adminDeliverability: {
    get: (...args: unknown[]) => mockGet(...args),
    update: (...args: unknown[]) => mockUpdate(...args),
  },
}));

// ── Mock AdminMetaContext ─────────────────────────────────────────────────────
vi.mock('../../AdminMetaContext', () => ({
  useAdminPageMeta: vi.fn(),
}));

// ── Stable toast ─────────────────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

vi.mock('../../components', () => ({
  PageHeader: ({ title }: { title: string }) => <h1>{title}</h1>,
}));

// ── Sample deliverable data ───────────────────────────────────────────────────
const DELIVERABLE = {
  id: 7,
  title: 'Launch Beta Feature',
  description: 'Ship the beta feature to production',
  priority: 'high',
  status: 'in_progress',
  due_date: '2026-12-31',
  assigned_to: 42,
};

import { EditDeliverable } from './EditDeliverable';

describe('EditDeliverable — loading', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGet.mockReturnValue(new Promise(() => {})); // pending
  });

  it('shows loading spinner (aria-busy=true)', () => {
    render(<EditDeliverable />);
    const spinners = screen.getAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeInTheDocument();
  });
});

describe('EditDeliverable — populated', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGet.mockResolvedValue({ success: true, data: DELIVERABLE });
    mockUpdate.mockResolvedValue({ success: true });
  });

  it('fetches deliverable with correct id', async () => {
    render(<EditDeliverable />);
    await waitFor(() => {
      expect(mockGet).toHaveBeenCalledWith(7);
    });
  });

  it('pre-fills the title field with fetched value', async () => {
    render(<EditDeliverable />);
    await waitFor(() => {
      const titleInput = screen.getByDisplayValue('Launch Beta Feature');
      expect(titleInput).toBeInTheDocument();
    });
  });

  it('pre-fills the description textarea', async () => {
    render(<EditDeliverable />);
    await waitFor(() => {
      expect(screen.getByDisplayValue('Ship the beta feature to production')).toBeInTheDocument();
    });
  });

  it('spinner gone after data loads', async () => {
    render(<EditDeliverable />);
    await waitFor(() => {
      const busy = screen.queryAllByRole('status').find(
        (el) => el.getAttribute('aria-busy') === 'true',
      );
      expect(busy).toBeUndefined();
    });
  });

  it('calls update API with form data on Save click', async () => {
    const user = userEvent.setup();
    render(<EditDeliverable />);
    await waitFor(() => screen.getByDisplayValue('Launch Beta Feature'));

    // Click Save button
    const saveBtn = screen.getByRole('button', { name: /save/i });
    await user.click(saveBtn);

    await waitFor(() => {
      expect(mockUpdate).toHaveBeenCalledWith(
        7,
        expect.objectContaining({ title: 'Launch Beta Feature' }),
      );
    });
  });

  it('shows success toast and navigates away on successful save', async () => {
    const user = userEvent.setup();
    render(<EditDeliverable />);
    await waitFor(() => screen.getByDisplayValue('Launch Beta Feature'));

    await user.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
      expect(mockNavigate).toHaveBeenCalled();
    });
  });

  it('shows error toast when update API fails', async () => {
    mockUpdate.mockResolvedValueOnce({ success: false });
    const user = userEvent.setup();
    render(<EditDeliverable />);
    await waitFor(() => screen.getByDisplayValue('Launch Beta Feature'));

    await user.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows warning toast and does not call update when title is empty', async () => {
    const user = userEvent.setup();
    render(<EditDeliverable />);
    await waitFor(() => screen.getByDisplayValue('Launch Beta Feature'));

    // Clear the title input
    const titleInput = screen.getByDisplayValue('Launch Beta Feature');
    await user.clear(titleInput);

    await user.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() => {
      expect(mockToast.warning).toHaveBeenCalled();
      expect(mockUpdate).not.toHaveBeenCalled();
    });
  });

  it('navigates back when Cancel button pressed', async () => {
    const user = userEvent.setup();
    render(<EditDeliverable />);
    await waitFor(() => screen.getByDisplayValue('Launch Beta Feature'));

    const cancelBtn = screen.getByRole('button', { name: /cancel/i });
    await user.click(cancelBtn);

    expect(mockNavigate).toHaveBeenCalled();
  });
});

describe('EditDeliverable — load error', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGet.mockRejectedValue(new Error('Network error'));
  });

  it('shows error toast when load fails', async () => {
    render(<EditDeliverable />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});

describe('EditDeliverable — load returns failure', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGet.mockResolvedValue({ success: false });
  });

  it('shows error toast when API returns success=false', async () => {
    render(<EditDeliverable />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});
