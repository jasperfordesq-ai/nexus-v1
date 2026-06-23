// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import { act } from 'react';
import React from 'react';

// ─── No @/lib/api import in this component — it uses global fetch ─────────────
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Stub HeroUI Input + Button (real components misfire in jsdom) ────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    Input: ({ label, placeholder, value, onChange, onKeyDown, onFocus, endContent, role: roleProp, 'aria-expanded': ariaExpanded, 'aria-haspopup': ariaHaspopup }: {
      label?: string;
      placeholder?: string;
      value?: string;
      onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
      onKeyDown?: (e: React.KeyboardEvent<HTMLInputElement>) => void;
      onFocus?: () => void;
      endContent?: React.ReactNode;
      role?: string;
      'aria-expanded'?: boolean | string;
      'aria-haspopup'?: string;
      [k: string]: unknown;
    }) => {
      const inputId = 'stub-input';
      return (
        <div>
          {label && <label htmlFor={inputId}>{label}</label>}
          <input
            id={inputId}
            placeholder={placeholder}
            value={value}
            onChange={onChange}
            onKeyDown={onKeyDown}
            onFocus={onFocus}
            role={roleProp}
            aria-expanded={ariaExpanded}
            aria-haspopup={ariaHaspopup}
          />
          {endContent}
        </div>
      );
    },
    Button: ({ children, onPress, onClick, 'aria-label': ariaLabel, isIconOnly, ...rest }: {
      children?: React.ReactNode;
      onPress?: () => void;
      onClick?: () => void;
      'aria-label'?: string;
      isIconOnly?: boolean;
      [k: string]: unknown;
    }) => (
      <button
        aria-label={ariaLabel}
        onClick={() => { onPress?.(); onClick?.(); }}
      >
        {children}
      </button>
    ),
  };
});

// ─── Contexts ────────────────────────────────────────────────────────────────
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
    useToast: () => ({
      success: vi.fn(),
      error: vi.fn(),
      info: vi.fn(),
      warning: vi.fn(),
      showToast: vi.fn(),
    }),
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Helpers ─────────────────────────────────────────────────────────────────
function makeFetchResponse(data: unknown) {
  return Promise.resolve({
    ok: true,
    json: () => Promise.resolve(data),
  } as Response);
}

const nominatimResult = {
  place_id: 1001,
  lat: '53.3498',
  lon: '-6.2603',
  display_name: 'Dublin, County Dublin, Leinster, Ireland',
  name: 'Dublin',
  type: 'city',
  address: {
    city: 'Dublin',
    county: 'County Dublin',
    state: 'Leinster',
    country: 'Ireland',
    country_code: 'ie',
    postcode: 'D01',
  },
};

const TEST_BASE = 'http://nominatim.test';

// Helper: trigger handleInputChange + advance debounce + flush all promises
async function typeAndDebounce(input: HTMLElement, value: string) {
  fireEvent.change(input, { target: { value } });
  act(() => { vi.advanceTimersByTime(1200); });
  // Flush the fetch Promise chain (fetch → .json() → setState)
  await act(async () => { await Promise.resolve(); });
  await act(async () => { await Promise.resolve(); });
  await act(async () => { await Promise.resolve(); });
}

describe('NominatimAutocomplete', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    vi.useFakeTimers();
    vi.stubGlobal('fetch', vi.fn());
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
  });

  it('renders a combobox input', async () => {
    const { NominatimAutocomplete } = await import('./NominatimAutocomplete');
    render(
      <NominatimAutocomplete
        value=""
        onChange={vi.fn()}
        baseUrl={TEST_BASE}
      />
    );
    expect(screen.getByRole('combobox')).toBeInTheDocument();
  });

  it('renders provided label', async () => {
    const { NominatimAutocomplete } = await import('./NominatimAutocomplete');
    render(
      <NominatimAutocomplete
        value=""
        onChange={vi.fn()}
        label="Search location"
        baseUrl={TEST_BASE}
      />
    );
    expect(screen.getByLabelText(/Search location/i)).toBeInTheDocument();
  });

  it('does not fetch when fewer than 3 characters typed', async () => {
    const { NominatimAutocomplete } = await import('./NominatimAutocomplete');
    render(
      <NominatimAutocomplete
        value=""
        onChange={vi.fn()}
        baseUrl={TEST_BASE}
      />
    );
    const input = screen.getByRole('combobox');
    await typeAndDebounce(input, 'Du');
    expect(vi.mocked(fetch)).not.toHaveBeenCalled();
  });

  it('fetches suggestions after debounce when 3+ chars typed', async () => {
    vi.mocked(fetch).mockReturnValue(makeFetchResponse([nominatimResult]));

    const { NominatimAutocomplete } = await import('./NominatimAutocomplete');
    render(
      <NominatimAutocomplete
        value=""
        onChange={vi.fn()}
        baseUrl={TEST_BASE}
      />
    );
    const input = screen.getByRole('combobox');
    await typeAndDebounce(input, 'Dub');

    expect(vi.mocked(fetch)).toHaveBeenCalledWith(
      expect.stringContaining('Dub'),
      expect.objectContaining({ headers: expect.objectContaining({ Accept: 'application/json' }) })
    );
  });

  it('shows suggestion items in a listbox after successful fetch', async () => {
    vi.mocked(fetch).mockReturnValue(makeFetchResponse([nominatimResult]));

    const { NominatimAutocomplete } = await import('./NominatimAutocomplete');
    render(
      <NominatimAutocomplete
        value=""
        onChange={vi.fn()}
        baseUrl={TEST_BASE}
      />
    );
    const input = screen.getByRole('combobox');
    await typeAndDebounce(input, 'Dublin');

    expect(screen.getByRole('listbox')).toBeInTheDocument();
    expect(screen.getByRole('option')).toBeInTheDocument();
    expect(screen.getByText('Dublin')).toBeInTheDocument();
  });

  it('calls onPlaceSelect with mapped PlaceResult when option clicked', async () => {
    vi.mocked(fetch).mockReturnValue(makeFetchResponse([nominatimResult]));
    const onPlaceSelect = vi.fn();
    const onChange = vi.fn();

    const { NominatimAutocomplete } = await import('./NominatimAutocomplete');
    render(
      <NominatimAutocomplete
        value=""
        onChange={onChange}
        onPlaceSelect={onPlaceSelect}
        baseUrl={TEST_BASE}
      />
    );
    await typeAndDebounce(screen.getByRole('combobox'), 'Dublin');

    const option = screen.getByRole('option');
    fireEvent.mouseDown(option);

    expect(onPlaceSelect).toHaveBeenCalledWith(
      expect.objectContaining({
        placeId: 'osm:1001',
        lat: 53.3498,
        lng: -6.2603,
      })
    );
  });

  it('hides listbox after a suggestion is selected', async () => {
    vi.mocked(fetch).mockReturnValue(makeFetchResponse([nominatimResult]));

    const { NominatimAutocomplete } = await import('./NominatimAutocomplete');
    render(
      <NominatimAutocomplete
        value=""
        onChange={vi.fn()}
        onPlaceSelect={vi.fn()}
        baseUrl={TEST_BASE}
      />
    );
    await typeAndDebounce(screen.getByRole('combobox'), 'Dublin');
    expect(screen.getByRole('listbox')).toBeInTheDocument();

    fireEvent.mouseDown(screen.getByRole('option'));

    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });

  it('shows a clear button when value is non-empty', async () => {
    const { NominatimAutocomplete } = await import('./NominatimAutocomplete');
    render(
      <NominatimAutocomplete
        value="Dublin"
        onChange={vi.fn()}
        baseUrl={TEST_BASE}
      />
    );
    const clearBtn = screen.getByRole('button', { name: /clear/i });
    expect(clearBtn).toBeInTheDocument();
  });

  it('calls onChange with empty string when clear button is pressed', async () => {
    const onChange = vi.fn();
    const onClear = vi.fn();

    const { NominatimAutocomplete } = await import('./NominatimAutocomplete');
    render(
      <NominatimAutocomplete
        value="Dublin"
        onChange={onChange}
        onClear={onClear}
        baseUrl={TEST_BASE}
      />
    );
    const clearBtn = screen.getByRole('button', { name: /clear/i });
    // Button stub calls onPress via onClick — fireEvent.click works
    fireEvent.click(clearBtn);

    expect(onChange).toHaveBeenCalledWith('');
    expect(onClear).toHaveBeenCalled();
  });

  it('does not show clear button when value is empty', async () => {
    const { NominatimAutocomplete } = await import('./NominatimAutocomplete');
    render(
      <NominatimAutocomplete
        value=""
        onChange={vi.fn()}
        baseUrl={TEST_BASE}
      />
    );
    expect(screen.queryByRole('button', { name: /clear/i })).not.toBeInTheDocument();
  });

  it('closes listbox on Escape key', async () => {
    vi.mocked(fetch).mockReturnValue(makeFetchResponse([nominatimResult]));

    const { NominatimAutocomplete } = await import('./NominatimAutocomplete');
    render(
      <NominatimAutocomplete
        value=""
        onChange={vi.fn()}
        baseUrl={TEST_BASE}
      />
    );
    await typeAndDebounce(screen.getByRole('combobox'), 'Dublin');
    expect(screen.getByRole('listbox')).toBeInTheDocument();

    fireEvent.keyDown(screen.getByRole('combobox'), { key: 'Escape' });

    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });

  it('shows no listbox when fetch returns empty array', async () => {
    vi.mocked(fetch).mockReturnValue(makeFetchResponse([]));

    const { NominatimAutocomplete } = await import('./NominatimAutocomplete');
    render(
      <NominatimAutocomplete
        value=""
        onChange={vi.fn()}
        baseUrl={TEST_BASE}
      />
    );
    await typeAndDebounce(screen.getByRole('combobox'), 'Xyz');

    expect(vi.mocked(fetch)).toHaveBeenCalled();
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });

  it('shows no listbox on fetch error', async () => {
    vi.mocked(fetch).mockRejectedValue(new Error('Network error'));

    const { NominatimAutocomplete } = await import('./NominatimAutocomplete');
    render(
      <NominatimAutocomplete
        value=""
        onChange={vi.fn()}
        baseUrl={TEST_BASE}
      />
    );
    await typeAndDebounce(screen.getByRole('combobox'), 'Berlin');

    expect(vi.mocked(fetch)).toHaveBeenCalled();
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });
});
