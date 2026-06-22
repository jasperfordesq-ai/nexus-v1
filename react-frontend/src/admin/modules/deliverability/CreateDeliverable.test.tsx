// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
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
const mockCreate = vi.hoisted(() => vi.fn());

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
  adminDeliverability: {
    create: mockCreate,
  },
}));

vi.mock('../../AdminMetaContext', () => ({
  useAdminPageMeta: vi.fn(),
}));

import { CreateDeliverable } from './CreateDeliverable';

describe('CreateDeliverable', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ── Initial render ────────────────────────────────────────────────────────
  it('renders the form with title label', () => {
    render(<CreateDeliverable />);
    // real en translation: "Title"
    expect(screen.getByText('Title')).toBeInTheDocument();
  });

  it('renders the Description label', () => {
    render(<CreateDeliverable />);
    // real en translation: "Description"
    expect(screen.getByText('Description')).toBeInTheDocument();
  });

  it('renders the Save Deliverable button', () => {
    render(<CreateDeliverable />);
    // real en translation: "Save Deliverable"
    expect(screen.getByRole('button', { name: /save deliverable/i })).toBeInTheDocument();
  });

  it('renders the Cancel button', () => {
    render(<CreateDeliverable />);
    // real en translation: "Cancel"
    expect(screen.getByRole('button', { name: /^cancel$/i })).toBeInTheDocument();
  });

  it('renders the Back button', () => {
    render(<CreateDeliverable />);
    // real en translation: "Back"
    expect(screen.getByRole('button', { name: /^back$/i })).toBeInTheDocument();
  });

  // ── Validation: empty title ───────────────────────────────────────────────
  it('shows a warning toast when saving with empty title', async () => {
    render(<CreateDeliverable />);

    const saveBtn = screen.getByRole('button', { name: /save deliverable/i });
    await userEvent.click(saveBtn);

    await waitFor(() => {
      // real en translation: "Title is required"
      expect(mockToast.warning).toHaveBeenCalledWith('Title is required');
    });
    expect(mockCreate).not.toHaveBeenCalled();
  });

  // ── Successful submit ─────────────────────────────────────────────────────
  it('calls adminDeliverability.create and shows success toast on success', async () => {
    mockCreate.mockResolvedValue({ success: true, data: { id: 42 } });
    render(<CreateDeliverable />);

    // HeroUI Input — find by role "textbox" with accessible name "Title"
    const titleInput = screen.getByRole('textbox', { name: /^title$/i });
    await userEvent.type(titleInput, 'My Deliverable');

    const saveBtn = screen.getByRole('button', { name: /save deliverable/i });
    await userEvent.click(saveBtn);

    await waitFor(() => {
      expect(mockCreate).toHaveBeenCalledWith(
        expect.objectContaining({ title: 'My Deliverable' }),
      );
    });

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
      expect(mockNavigate).toHaveBeenCalled();
    });
  });

  // ── API failure ───────────────────────────────────────────────────────────
  it('shows error toast when API returns success=false', async () => {
    mockCreate.mockResolvedValue({ success: false });
    render(<CreateDeliverable />);

    const titleInput = screen.getByRole('textbox', { name: /^title$/i });
    fireEvent.change(titleInput, { target: { value: 'Some title' } });

    const saveBtn = screen.getByRole('button', { name: /save deliverable/i });
    await userEvent.click(saveBtn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
    expect(mockNavigate).not.toHaveBeenCalled();
  });

  it('shows error toast when API throws', async () => {
    mockCreate.mockRejectedValue(new Error('Network'));
    render(<CreateDeliverable />);

    const titleInput = screen.getByRole('textbox', { name: /^title$/i });
    fireEvent.change(titleInput, { target: { value: 'Throw title' } });

    const saveBtn = screen.getByRole('button', { name: /save deliverable/i });
    await userEvent.click(saveBtn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  // ── Navigation ────────────────────────────────────────────────────────────
  it('navigates when Cancel button is clicked', async () => {
    render(<CreateDeliverable />);

    const cancelBtn = screen.getByRole('button', { name: /^cancel$/i });
    await userEvent.click(cancelBtn);

    expect(mockNavigate).toHaveBeenCalled();
  });

  it('navigates when Back button is clicked', async () => {
    render(<CreateDeliverable />);

    const backBtn = screen.getByRole('button', { name: /^back$/i });
    await userEvent.click(backBtn);

    expect(mockNavigate).toHaveBeenCalled();
  });
});
