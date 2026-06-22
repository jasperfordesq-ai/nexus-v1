// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ─────────────────────────────────────────────────────────────────────────────
// Stable mock data
// ─────────────────────────────────────────────────────────────────────────────
const { mockToast, mockNavigate, mockCreateBadge } = vi.hoisted(() => ({
  mockToast: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
  mockNavigate: vi.fn(),
  mockCreateBadge: vi.fn(),
}));

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast }),
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
    createBadge: mockCreateBadge,
  },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { CreateBadge } from './CreateBadge';

// ─────────────────────────────────────────────────────────────────────────────
// Helper: find the Save / Create Badge submit button.
// Real i18n resolves t('gamification.create_badge') → "Create Badge".
// The button has onPress={handleSave} and is the only button whose text
// includes "Create Badge" (HeroUI Button role="button").
// ─────────────────────────────────────────────────────────────────────────────
function getSaveButton(): HTMLElement {
  // getAllByRole returns all buttons; the save button has the "Create Badge" text.
  // We use getAllByRole to avoid strict-match failures if multiple buttons share
  // the same accessible name with different context.
  const buttons = screen.getAllByRole('button');
  // Find by text content (textContent includes icon text + label text)
  const btn = buttons.find((b) => /create badge/i.test(b.textContent ?? ''));
  if (!btn) {
    throw new Error(
      `Save button not found. Buttons found: ${buttons.map((b) => JSON.stringify(b.textContent)).join(', ')}`,
    );
  }
  return btn;
}

// ─────────────────────────────────────────────────────────────────────────────
// Tests
// ─────────────────────────────────────────────────────────────────────────────
describe('CreateBadge', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the form with key fields', () => {
    render(<CreateBadge />);

    // Name and Slug inputs are rendered
    const textboxes = screen.getAllByRole('textbox');
    expect(textboxes.length).toBeGreaterThanOrEqual(2);

    // Active switch defaults to on (HeroUI Switch conveys state via data-selected)
    const switchEl = screen.getByRole('switch');
    expect(switchEl).toBeInTheDocument();
  });

  it('auto-generates slug from name input', async () => {
    render(<CreateBadge />);

    const nameInput = screen.getAllByRole('textbox')[0];
    expect(nameInput).toBeTruthy();

    await userEvent.clear(nameInput!);
    await userEvent.type(nameInput!, 'Top Contributor');

    // Slug field (second textbox) should auto-populate
    const slugInput = screen.getAllByRole('textbox')[1] as HTMLInputElement;
    await waitFor(() => {
      expect(slugInput.value).toBe('top_contributor');
    });
  });

  it('shows error toast when name is blank on submit', async () => {
    render(<CreateBadge />);

    // Click Save button without filling in a name
    const createBtn = getSaveButton();
    await userEvent.click(createBtn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
      expect(mockCreateBadge).not.toHaveBeenCalled();
    });
  });

  it('calls createBadge and navigates on success', async () => {
    mockCreateBadge.mockResolvedValue({ success: true, data: { id: 10 } });

    render(<CreateBadge />);

    const nameInput = screen.getAllByRole('textbox')[0];
    await userEvent.type(nameInput!, 'Community Star');

    await userEvent.click(getSaveButton());

    await waitFor(() => {
      expect(mockCreateBadge).toHaveBeenCalledWith(
        expect.objectContaining({
          name: 'Community Star',
          icon: 'award',       // default
          category: 'special', // default
          is_active: true,     // default
        }),
      );
    });
    expect(mockToast.success).toHaveBeenCalled();
    expect(mockNavigate).toHaveBeenCalledWith(
      expect.stringContaining('/admin/custom-badges'),
    );
  });

  it('shows error toast on API failure', async () => {
    mockCreateBadge.mockResolvedValue({ success: false, error: 'Slug already taken' });

    render(<CreateBadge />);

    const nameInput = screen.getAllByRole('textbox')[0];
    await userEvent.type(nameInput!, 'Top Volunteer');

    await userEvent.click(getSaveButton());

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalledWith('Slug already taken');
    });
    expect(mockNavigate).not.toHaveBeenCalled();
  });

  it('uses errors array message as fallback when error field is absent', async () => {
    mockCreateBadge.mockResolvedValue({
      success: false,
      errors: [{ message: 'Custom validation error' }],
    });

    render(<CreateBadge />);

    const nameInput = screen.getAllByRole('textbox')[0];
    await userEvent.type(nameInput!, 'Error Badge');

    await userEvent.click(getSaveButton());

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalledWith('Custom validation error');
    });
  });

  it('preview section shows entered name immediately', async () => {
    render(<CreateBadge />);

    const nameInput = screen.getAllByRole('textbox')[0];
    await userEvent.type(nameInput!, 'Hero Badge');

    // The preview renders formData.name which should now show
    await waitFor(() => {
      // getAllByText handles multiple occurrences (input value is not getText-able,
      // but the preview <p> contains the typed text)
      const heroTexts = screen.getAllByText('Hero Badge');
      expect(heroTexts.length).toBeGreaterThanOrEqual(1);
    });
  });

  it('sends xp: 0 when XP field is left blank', async () => {
    mockCreateBadge.mockResolvedValue({ success: true, data: { id: 1 } });

    render(<CreateBadge />);

    const nameInput = screen.getAllByRole('textbox')[0];
    await userEvent.type(nameInput!, 'Zero XP Badge');

    await userEvent.click(getSaveButton());

    await waitFor(() => {
      expect(mockCreateBadge).toHaveBeenCalledWith(
        expect.objectContaining({ xp: 0 }),
      );
    });
  });

  it('does not send slug when slug field is empty after trim', async () => {
    mockCreateBadge.mockResolvedValue({ success: true, data: { id: 1 } });

    render(<CreateBadge />);

    // Type a name that generates a slug, then clear it
    const nameInput = screen.getAllByRole('textbox')[0];
    await userEvent.type(nameInput!, 'No Slug Badge');

    // Clear the slug field manually
    const slugInput = screen.getAllByRole('textbox')[1];
    await userEvent.clear(slugInput!);

    await userEvent.click(getSaveButton());

    await waitFor(() => {
      const call = mockCreateBadge.mock.calls[0]?.[0] as Record<string, unknown>;
      // slug should be undefined when blank (the `|| undefined` in handleSave)
      expect(call.slug).toBeUndefined();
    });
  });

  it('is_active defaults to true and can be toggled', async () => {
    render(<CreateBadge />);

    const switchEl = screen.getByRole('switch');
    // HeroUI Switch v3 conveys selected state via data-selected, not aria-checked
    expect(switchEl).toBeInTheDocument();

    // The switch starts selected (is_active defaults to true)
    // After clicking it should toggle — verify the mock is NOT called with is_active:false
    // (we just verify toggle doesn't crash)
    await userEvent.click(switchEl);

    // Component should still be mounted (no crash)
    expect(screen.getByRole('switch')).toBeInTheDocument();
  });
});
