// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock api ────────────────────────────────────────────────────────────────
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

// ─── Contexts ────────────────────────────────────────────────────────────────
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Test User' },
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
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Stub HeroUI components (problematic in jsdom) ───────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    // HeroUI Button uses React Aria onPress — stub to a native button with onClick
    Button: ({ children, onPress, onClick, 'aria-label': ariaLabel, startContent, endContent, isLoading, isDisabled, isIconOnly, size, variant, color, className, type: btnType }: {
      children?: React.ReactNode;
      onPress?: () => void;
      onClick?: () => void;
      'aria-label'?: string;
      startContent?: React.ReactNode;
      endContent?: React.ReactNode;
      isLoading?: boolean;
      isDisabled?: boolean;
      isIconOnly?: boolean;
      size?: string;
      variant?: string;
      color?: string;
      className?: string;
      type?: 'button' | 'submit' | 'reset';
    }) => (
      <button
        aria-label={ariaLabel}
        onClick={() => { onPress?.(); onClick?.(); }}
        disabled={isDisabled}
        type={btnType ?? 'button'}
        className={className}
      >
        {startContent}{children}{endContent}
      </button>
    ),
    Select: ({ label, children, onChange }: { label?: string; children?: React.ReactNode; onChange?: (e: React.ChangeEvent<HTMLSelectElement>) => void }) => (
      <select aria-label={label ?? ''} onChange={onChange}>
        {children}
      </select>
    ),
    SelectItem: ({ children, id }: { children?: React.ReactNode; id?: string }) => (
      <option value={id ?? ''}>{children}</option>
    ),
    GlassCard: ({ children, className }: { children?: React.ReactNode; className?: string }) => (
      <div data-testid="glass-card" className={className}>{children}</div>
    ),
    Chip: ({ children }: { children?: React.ReactNode }) => (
      <span data-testid="chip">{children}</span>
    ),
    Input: ({ label, 'aria-label': ariaLabel, placeholder, value, onChange, onKeyDown, type, ...rest }: {
      label?: string;
      'aria-label'?: string;
      placeholder?: string;
      value?: string;
      onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
      onKeyDown?: (e: React.KeyboardEvent<HTMLInputElement>) => void;
      type?: string;
      [k: string]: unknown;
    }) => (
      <input
        type={type ?? 'text'}
        aria-label={ariaLabel ?? label ?? placeholder ?? ''}
        placeholder={placeholder}
        value={value}
        onChange={onChange}
        onKeyDown={onKeyDown}
      />
    ),
  };
});

// ─── Fixtures ────────────────────────────────────────────────────────────────
const defaultFilters = {
  type: 'all',
  category_id: '',
  date_from: '',
  date_to: '',
  sort: 'relevance',
  skills: '',
  location: '',
};

describe('AdvancedSearchFilters', () => {
  const onChangeMock = vi.fn();
  const onApplyMock = vi.fn();
  const onResetMock = vi.fn();

  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue({ success: true, data: [] });
  });

  it('renders the Advanced Filters toggle button', async () => {
    const { AdvancedSearchFilters } = await import('./AdvancedSearchFilters');
    render(
      <AdvancedSearchFilters
        filters={defaultFilters}
        onChange={onChangeMock}
        onApply={onApplyMock}
        onReset={onResetMock}
      />
    );
    // The button uses the i18n key 'advanced_filters' — look for any button
    const btn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('filter') ||
      b.textContent?.toLowerCase().includes('filter')
    );
    expect(btn).toBeInTheDocument();
  });

  it('does not show filter panel when collapsed', async () => {
    const { AdvancedSearchFilters } = await import('./AdvancedSearchFilters');
    render(
      <AdvancedSearchFilters
        filters={defaultFilters}
        onChange={onChangeMock}
        onApply={onApplyMock}
        onReset={onResetMock}
      />
    );
    expect(screen.queryByTestId('glass-card')).not.toBeInTheDocument();
  });

  it('expands filter panel when toggle button is clicked', async () => {
    const { AdvancedSearchFilters } = await import('./AdvancedSearchFilters');
    render(
      <AdvancedSearchFilters
        filters={defaultFilters}
        onChange={onChangeMock}
        onApply={onApplyMock}
        onReset={onResetMock}
      />
    );
    const toggleBtn = screen.getAllByRole('button')[0];
    fireEvent.click(toggleBtn);
    await waitFor(() => {
      expect(screen.getByTestId('glass-card')).toBeInTheDocument();
    });
  });

  it('fetches categories and tags when expanded', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/v2/categories')) {
        return Promise.resolve({ success: true, data: [{ id: 5, name: 'Tech', slug: 'tech' }] });
      }
      if (url.includes('/v2/listings/tags')) {
        return Promise.resolve({ success: true, data: [{ tag: 'coding', count: 10 }] });
      }
      return Promise.resolve({ success: true, data: [] });
    });

    const { AdvancedSearchFilters } = await import('./AdvancedSearchFilters');
    render(
      <AdvancedSearchFilters
        filters={defaultFilters}
        onChange={onChangeMock}
        onApply={onApplyMock}
        onReset={onResetMock}
      />
    );
    const toggleBtn = screen.getAllByRole('button')[0];
    fireEvent.click(toggleBtn);

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith(expect.stringContaining('/v2/categories'));
    });
    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith(expect.stringContaining('/v2/listings/tags'));
    });
  });

  it('shows popular tags as clickable buttons when expanded', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/v2/listings/tags')) {
        return Promise.resolve({
          success: true,
          data: [{ tag: 'gardening', count: 5 }, { tag: 'cooking', count: 3 }],
        });
      }
      return Promise.resolve({ success: true, data: [] });
    });

    const { AdvancedSearchFilters } = await import('./AdvancedSearchFilters');
    render(
      <AdvancedSearchFilters
        filters={defaultFilters}
        onChange={onChangeMock}
        onApply={onApplyMock}
        onReset={onResetMock}
      />
    );
    fireEvent.click(screen.getAllByRole('button')[0]);

    await waitFor(() => {
      expect(screen.getByText('gardening')).toBeInTheDocument();
    });
    expect(screen.getByText('cooking')).toBeInTheDocument();
  });

  it('clicking a popular tag calls onChange with that skill', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/v2/listings/tags')) {
        return Promise.resolve({ success: true, data: [{ tag: 'painting', count: 7 }] });
      }
      return Promise.resolve({ success: true, data: [] });
    });

    const { AdvancedSearchFilters } = await import('./AdvancedSearchFilters');
    render(
      <AdvancedSearchFilters
        filters={defaultFilters}
        onChange={onChangeMock}
        onApply={onApplyMock}
        onReset={onResetMock}
      />
    );
    fireEvent.click(screen.getAllByRole('button')[0]);

    await waitFor(() => screen.getByText('painting'));
    fireEvent.click(screen.getByText('painting'));

    expect(onChangeMock).toHaveBeenCalledWith(
      expect.objectContaining({ skills: 'painting' })
    );
  });

  it('shows active skill chips when skills filter is set', async () => {
    const { AdvancedSearchFilters } = await import('./AdvancedSearchFilters');
    render(
      <AdvancedSearchFilters
        filters={{ ...defaultFilters, skills: 'coding,design' }}
        onChange={onChangeMock}
        onApply={onApplyMock}
        onReset={onResetMock}
      />
    );
    fireEvent.click(screen.getAllByRole('button')[0]);

    await waitFor(() => {
      const chips = screen.getAllByTestId('chip');
      const texts = chips.map((c) => c.textContent);
      expect(texts).toContain('coding');
      expect(texts).toContain('design');
    });
  });

  it('calls onApply when Apply Filters button is clicked', async () => {
    const { AdvancedSearchFilters } = await import('./AdvancedSearchFilters');
    render(
      <AdvancedSearchFilters
        filters={defaultFilters}
        onChange={onChangeMock}
        onApply={onApplyMock}
        onReset={onResetMock}
      />
    );
    // Expand the panel first
    fireEvent.click(screen.getAllByRole('button')[0]);
    await waitFor(() => screen.getByTestId('glass-card'));

    // The Apply button is inside the panel — it has color="primary" and shows 'filter_apply' i18n key.
    // After expanding there are multiple buttons. The Apply one is NOT the toggle (index 0).
    // It's the LAST button in the panel (Reset is second-to-last, Apply is last).
    const allButtons = screen.getAllByRole('button');
    // Apply is the very last button inside the glass-card panel
    const applyBtn = allButtons[allButtons.length - 1];
    fireEvent.click(applyBtn);

    expect(onApplyMock).toHaveBeenCalled();
  });

  it('calls onReset when Reset button is clicked', async () => {
    const { AdvancedSearchFilters } = await import('./AdvancedSearchFilters');
    render(
      <AdvancedSearchFilters
        filters={{ ...defaultFilters, type: 'users' }}
        onChange={onChangeMock}
        onApply={onApplyMock}
        onReset={onResetMock}
      />
    );
    fireEvent.click(screen.getAllByRole('button')[0]);
    await waitFor(() => screen.getByTestId('glass-card'));

    const resetBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('reset') ||
      b.textContent?.toLowerCase().includes('clear')
    );
    expect(resetBtn).toBeInTheDocument();
    if (resetBtn) fireEvent.click(resetBtn);

    expect(onResetMock).toHaveBeenCalled();
  });

  it('shows active filter count badge when filters are set', async () => {
    const { AdvancedSearchFilters } = await import('./AdvancedSearchFilters');
    render(
      <AdvancedSearchFilters
        filters={{ ...defaultFilters, type: 'events', date_from: '2025-01-01' }}
        onChange={onChangeMock}
        onApply={onApplyMock}
        onReset={onResetMock}
      />
    );
    // 2 active filters (type + date_from) — chip shows "2"
    const chip = screen.getByTestId('chip');
    expect(chip).toHaveTextContent('2');
  });

  it('adding a skill via Enter key calls onChange', async () => {
    const { AdvancedSearchFilters } = await import('./AdvancedSearchFilters');
    render(
      <AdvancedSearchFilters
        filters={defaultFilters}
        onChange={onChangeMock}
        onApply={onApplyMock}
        onReset={onResetMock}
      />
    );
    fireEvent.click(screen.getAllByRole('button')[0]);
    await waitFor(() => screen.getByTestId('glass-card'));

    // Find the skills input by its placeholder/aria-label
    const skillInput = screen.getByRole('textbox', { name: /skill/i });
    fireEvent.change(skillInput, { target: { value: 'yoga' } });
    fireEvent.keyDown(skillInput, { key: 'Enter' });

    expect(onChangeMock).toHaveBeenCalledWith(
      expect.objectContaining({ skills: 'yoga' })
    );
  });

  it('location input calls onChange with updated location', async () => {
    const { AdvancedSearchFilters } = await import('./AdvancedSearchFilters');
    render(
      <AdvancedSearchFilters
        filters={defaultFilters}
        onChange={onChangeMock}
        onApply={onApplyMock}
        onReset={onResetMock}
      />
    );
    fireEvent.click(screen.getAllByRole('button')[0]);
    await waitFor(() => screen.getByTestId('glass-card'));

    // Location input placeholder
    const locInput = screen.getByRole('textbox', { name: /location/i });
    fireEvent.change(locInput, { target: { value: 'Dublin' } });

    expect(onChangeMock).toHaveBeenCalledWith(
      expect.objectContaining({ location: 'Dublin' })
    );
  });
});
