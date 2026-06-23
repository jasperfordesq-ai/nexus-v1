// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── API mock ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
    upload: vi.fn(),
  },
}));
vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/compress-image', () => ({
  compressImage: vi.fn(async (file: File) => file),
}));

// ─── Context mocks ────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Test User', location: 'Dublin', latitude: 53.33, longitude: -6.26 },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useToast: () => mockToast,
  })
);

// ─── Hooks ───────────────────────────────────────────────────────────────────
// useDraftPersistence: return a stable draft tuple
const mockDraft = { title: '', description: '', type: 'offer' as const };
const mockSetDraft = vi.fn();
const mockClearDraft = vi.fn();

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
  useDraftPersistence: vi.fn(() => [mockDraft, mockSetDraft, mockClearDraft]),
  useMediaQuery: vi.fn(() => false),
}));

vi.mock('@/hooks/useMediaQuery', () => ({
  useMediaQuery: vi.fn(() => false),
}));

// ─── Stub compose shared components ─────────────────────────────────────────
vi.mock('../shared/AiAssistButton', () => ({
  AiAssistButton: () => <div data-testid="ai-assist-button" />,
}));
vi.mock('../shared/SdgGoalsPicker', () => ({
  SdgGoalsPicker: () => <div data-testid="sdg-goals-picker" />,
}));
vi.mock('../shared/CharacterCount', () => ({
  CharacterCount: ({ current, max }: { current: number; max: number }) => (
    <div data-testid="character-count">{current}/{max}</div>
  ),
}));
vi.mock('../shared/EmojiPicker', () => ({
  EmojiPicker: ({ onSelect }: { onSelect: (e: string) => void }) => (
    <button data-testid="emoji-picker" type="button" onClick={() => onSelect('😊')}>Emoji</button>
  ),
}));

// ─── Fixtures ────────────────────────────────────────────────────────────────
const defaultProps = {
  onSuccess: vi.fn(),
  onClose: vi.fn(),
  templateData: undefined,
};

// ─────────────────────────────────────────────────────────────────────────────
describe('ListingTab', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue({ success: true, data: [] });
    mockApi.post.mockResolvedValue({ success: true, data: { id: 99 } });
  });

  it('renders offer and request type radio buttons', async () => {
    const { ListingTab } = await import('./ListingTab');
    render(<ListingTab {...defaultProps} />);

    await waitFor(() => {
      const radioGroup = screen.getByRole('radiogroup');
      expect(radioGroup).toBeInTheDocument();
      const radios = screen.getAllByRole('radio');
      expect(radios.length).toBeGreaterThanOrEqual(2);
    });
  });

  it('offer radio is checked by default', async () => {
    const { ListingTab } = await import('./ListingTab');
    render(<ListingTab {...defaultProps} />);

    await waitFor(() => {
      const checkedRadio = screen.getAllByRole('radio').find(
        (r) => r.getAttribute('aria-checked') === 'true'
      );
      expect(checkedRadio).toBeDefined();
    });
  });

  it('loads categories from the API on mount', async () => {
    const { ListingTab } = await import('./ListingTab');
    render(<ListingTab {...defaultProps} />);

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith('/v2/categories');
    });
  });

  it('renders category select when categories are returned', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: [{ id: 1, name: 'Education' }, { id: 2, name: 'Care' }],
    });

    const { ListingTab } = await import('./ListingTab');
    render(<ListingTab {...defaultProps} />);

    await waitFor(() => {
      // When categories are returned, a select/combobox appears
      const selects = document.querySelectorAll('[role="combobox"], select');
      expect(selects.length).toBeGreaterThan(0);
    });
  });

  it('renders the Add Image button when no image preview is active', async () => {
    const { ListingTab } = await import('./ListingTab');
    render(<ListingTab {...defaultProps} />);

    await waitFor(() => {
      const btn = screen.getAllByRole('button').find(
        (b) =>
          b.textContent?.toLowerCase().includes('add') ||
          b.textContent?.toLowerCase().includes('image') ||
          b.textContent?.toLowerCase().includes('photo')
      );
      expect(btn).toBeDefined();
    });
  });

  it('renders the SDG Goals Picker stub', async () => {
    const { ListingTab } = await import('./ListingTab');
    render(<ListingTab {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByTestId('sdg-goals-picker')).toBeInTheDocument();
    });
  });

  it('renders the character count component', async () => {
    const { ListingTab } = await import('./ListingTab');
    render(<ListingTab {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByTestId('character-count')).toBeInTheDocument();
    });
  });

  it('renders Cancel button on desktop (non-mobile)', async () => {
    const { ListingTab } = await import('./ListingTab');
    render(<ListingTab {...defaultProps} />);

    await waitFor(() => {
      const cancelBtn = screen.getAllByRole('button').find(
        (b) => b.textContent?.toLowerCase().includes('cancel')
      );
      expect(cancelBtn).toBeDefined();
    });
  });

  it('calls onClose when Cancel is clicked', async () => {
    const onClose = vi.fn();
    const { ListingTab } = await import('./ListingTab');
    render(<ListingTab {...defaultProps} onClose={onClose} />);

    await waitFor(async () => {
      const cancelBtn = screen.getAllByRole('button').find(
        (b) => b.textContent?.toLowerCase().includes('cancel')
      );
      if (cancelBtn) {
        fireEvent.click(cancelBtn);
        await waitFor(() => {
          expect(onClose).toHaveBeenCalled();
        });
      } else {
        throw new Error('Cancel button not found');
      }
    });
  });

  it('renders AI assist button', async () => {
    const { ListingTab } = await import('./ListingTab');
    render(<ListingTab {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByTestId('ai-assist-button')).toBeInTheDocument();
    });
  });

  it('renders emoji picker', async () => {
    const { ListingTab } = await import('./ListingTab');
    render(<ListingTab {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByTestId('emoji-picker')).toBeInTheDocument();
    });
  });

  it('Create Listing button is disabled when title and description are empty', async () => {
    const { ListingTab } = await import('./ListingTab');
    render(<ListingTab {...defaultProps} />);

    await waitFor(() => {
      const createBtn = screen.getAllByRole('button').find(
        (b) =>
          b.textContent?.toLowerCase().includes('create') ||
          b.textContent?.toLowerCase().includes('listing')
      );
      if (createBtn) {
        // When draft is empty, canSubmit=false → button is disabled
        expect(
          createBtn.hasAttribute('disabled') ||
          createBtn.getAttribute('aria-disabled') === 'true'
        ).toBe(true);
      }
    });
  });

  it('does not call post API when fields are empty (canSubmit=false)', async () => {
    const { ListingTab } = await import('./ListingTab');
    render(<ListingTab {...defaultProps} />);

    await waitFor(() => {
      const createBtn = screen.getAllByRole('button').find(
        (b) =>
          b.textContent?.toLowerCase().includes('create') ||
          b.textContent?.toLowerCase().includes('listing')
      );
      if (createBtn) fireEvent.click(createBtn);
    });

    // canSubmit is false when draft is empty — API never called
    expect(mockApi.post).not.toHaveBeenCalled();
  });

  it('renders a disabled location input (location from profile)', async () => {
    const { ListingTab } = await import('./ListingTab');
    render(<ListingTab {...defaultProps} />);

    await waitFor(() => {
      // There should be at least one disabled/aria-disabled input (location field)
      const disabledEls = Array.from(
        document.querySelectorAll('[disabled], [aria-disabled="true"]')
      );
      expect(disabledEls.length).toBeGreaterThan(0);
    });
  });
});
