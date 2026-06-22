// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/lib/api', () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

vi.mock('@/contexts', () => createMockContexts());

import { OsPlacesAutocomplete } from './OsPlacesAutocomplete';
import { api } from '@/lib/api';

const mockedGet = api.get as ReturnType<typeof vi.fn>;

// ── OS Places response fixture ──
const OS_RESULTS = [
  {
    uprn: '12345678',
    address: '10 Downing Street, Westminster, London',
    postcode: 'SW1A 2AA',
    post_town: 'LONDON',
    lat: 51.5034,
    lng: -0.1276,
  },
  {
    uprn: null,
    address: '10 Baker Street, Marylebone, London',
    postcode: 'NW1 6XE',
    post_town: 'LONDON',
    lat: 51.5227,
    lng: -0.1571,
  },
];

const OS_RESPONSE = { success: true, data: { enabled: true, results: OS_RESULTS } };
const OS_DISABLED = { success: true, data: { enabled: false, results: [] } };

// NOTE: real timers throughout. The component debounces ~350ms; waitFor/findBy poll
// real time. (Mixing vi.useFakeTimers() with waitFor deadlocks the poller.)
describe('OsPlacesAutocomplete', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders a text input', () => {
    render(<OsPlacesAutocomplete value="" onChange={vi.fn()} />);
    expect(screen.getByRole('combobox')).toBeInTheDocument();
  });

  it('renders the label prop', () => {
    render(<OsPlacesAutocomplete value="" onChange={vi.fn()} label="Delivery address" />);
    expect(screen.getByText('Delivery address')).toBeInTheDocument();
  });

  it('does NOT call API when input is fewer than 3 chars (debounce guard)', async () => {
    render(<OsPlacesAutocomplete value="" onChange={vi.fn()} />);
    fireEvent.change(screen.getByRole('combobox'), { target: { value: 'Lo' } }); // 2 chars
    // Wait well past the debounce; a <3-char query must never trigger a fetch.
    await new Promise((r) => setTimeout(r, 450));
    expect(mockedGet).not.toHaveBeenCalled();
  });

  it('calls API after debounce when input has ≥ 3 chars', async () => {
    mockedGet.mockResolvedValue(OS_RESPONSE);
    render(<OsPlacesAutocomplete value="" onChange={vi.fn()} />);

    fireEvent.change(screen.getByRole('combobox'), { target: { value: '10 D' } });
    // Before debounce elapses: not yet called.
    expect(mockedGet).not.toHaveBeenCalled();

    await waitFor(() =>
      expect(mockedGet).toHaveBeenCalledWith(expect.stringContaining('/v2/geo/os-places/search?q=')),
    );
  });

  it('renders suggestion list items after API response', async () => {
    mockedGet.mockResolvedValue(OS_RESPONSE);
    render(<OsPlacesAutocomplete value="" onChange={vi.fn()} />);
    fireEvent.change(screen.getByRole('combobox'), { target: { value: '10 D' } });

    await screen.findByRole('listbox');
    const options = screen.getAllByRole('option');
    expect(options).toHaveLength(2);
    expect(options[0]).toHaveTextContent('10 Downing Street');
    expect(options[1]).toHaveTextContent('10 Baker Street');
  });

  it('fires onChange with formatted address when a suggestion is selected', async () => {
    const handleChange = vi.fn();
    mockedGet.mockResolvedValue(OS_RESPONSE);
    render(<OsPlacesAutocomplete value="" onChange={handleChange} />);
    fireEvent.change(screen.getByRole('combobox'), { target: { value: '10 D' } });

    const options = await screen.findAllByRole('option');
    fireEvent.mouseDown(options[0]);

    expect(handleChange).toHaveBeenCalledWith('10 Downing Street, Westminster, London');
  });

  it('fires onPlaceSelect with PlaceResult including UPRN when a suggestion with UPRN is selected', async () => {
    const handlePlaceSelect = vi.fn();
    mockedGet.mockResolvedValue(OS_RESPONSE);
    render(<OsPlacesAutocomplete value="" onChange={vi.fn()} onPlaceSelect={handlePlaceSelect} />);
    fireEvent.change(screen.getByRole('combobox'), { target: { value: '10 D' } });

    const options = await screen.findAllByRole('option');
    fireEvent.mouseDown(options[0]); // first result has uprn '12345678'

    expect(handlePlaceSelect).toHaveBeenCalledWith(
      expect.objectContaining({
        uprn: '12345678',
        formattedAddress: '10 Downing Street, Westminster, London',
        lat: 51.5034,
        lng: -0.1276,
        addressComponents: expect.objectContaining({
          postalCode: 'SW1A 2AA',
          country: 'United Kingdom',
          countryCode: 'GB',
        }),
      }),
    );
  });

  it('fires onPlaceSelect without UPRN when result has null UPRN', async () => {
    const handlePlaceSelect = vi.fn();
    mockedGet.mockResolvedValue(OS_RESPONSE);
    render(<OsPlacesAutocomplete value="" onChange={vi.fn()} onPlaceSelect={handlePlaceSelect} />);
    fireEvent.change(screen.getByRole('combobox'), { target: { value: '10 B' } });

    const options = await screen.findAllByRole('option');
    fireEvent.mouseDown(options[1]); // second result has null UPRN

    expect(handlePlaceSelect).toHaveBeenCalledWith(
      expect.objectContaining({
        formattedAddress: '10 Baker Street, Marylebone, London',
        uprn: undefined,
      }),
    );
  });

  it('closes the dropdown and clears suggestions after selection', async () => {
    mockedGet.mockResolvedValue(OS_RESPONSE);
    render(<OsPlacesAutocomplete value="" onChange={vi.fn()} />);
    fireEvent.change(screen.getByRole('combobox'), { target: { value: '10 D' } });

    const options = await screen.findAllByRole('option');
    fireEvent.mouseDown(options[0]);

    await waitFor(() => expect(screen.queryByRole('listbox')).not.toBeInTheDocument());
  });

  it('does not show dropdown when provider is disabled', async () => {
    mockedGet.mockResolvedValue(OS_DISABLED);
    render(<OsPlacesAutocomplete value="" onChange={vi.fn()} />);
    fireEvent.change(screen.getByRole('combobox'), { target: { value: '10 D' } });

    await waitFor(() => expect(mockedGet).toHaveBeenCalled());
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });

  it('does not show dropdown when API call fails', async () => {
    mockedGet.mockRejectedValue(new Error('Network error'));
    render(<OsPlacesAutocomplete value="" onChange={vi.fn()} />);
    fireEvent.change(screen.getByRole('combobox'), { target: { value: '10 D' } });

    await waitFor(() => expect(mockedGet).toHaveBeenCalled());
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });

  it('shows clear button when value is non-empty', () => {
    render(<OsPlacesAutocomplete value="10 Downing Street" onChange={vi.fn()} />);
    expect(screen.getByRole('button')).toBeInTheDocument();
  });

  it('does not show clear button when value is empty', () => {
    render(<OsPlacesAutocomplete value="" onChange={vi.fn()} />);
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('calls onChange with empty string and onClear when clear button is pressed', () => {
    const handleChange = vi.fn();
    const handleClear = vi.fn();
    render(<OsPlacesAutocomplete value="10 Downing Street" onChange={handleChange} onClear={handleClear} />);

    fireEvent.click(screen.getByRole('button'));
    expect(handleChange).toHaveBeenCalledWith('');
    expect(handleClear).toHaveBeenCalled();
  });

  it('encodes the query in the API URL', async () => {
    mockedGet.mockResolvedValue(OS_RESPONSE);
    render(<OsPlacesAutocomplete value="" onChange={vi.fn()} />);
    fireEvent.change(screen.getByRole('combobox'), { target: { value: "King's Road" } });

    await waitFor(() => {
      expect(mockedGet.mock.calls.length).toBeGreaterThan(0);
      expect(mockedGet.mock.calls[0][0] as string).toContain(encodeURIComponent("King's Road"));
    });
  });

  it('keyboard: ArrowDown moves activeIndex down', async () => {
    mockedGet.mockResolvedValue(OS_RESPONSE);
    render(<OsPlacesAutocomplete value="" onChange={vi.fn()} />);
    const input = screen.getByRole('combobox');
    fireEvent.change(input, { target: { value: '10 D' } });

    await screen.findByRole('listbox');
    fireEvent.keyDown(input, { key: 'ArrowDown' });

    await waitFor(() => {
      expect(screen.getAllByRole('option')[0]).toHaveAttribute('aria-selected', 'true');
    });
  });

  it('keyboard: Escape closes the dropdown', async () => {
    mockedGet.mockResolvedValue(OS_RESPONSE);
    render(<OsPlacesAutocomplete value="" onChange={vi.fn()} />);
    const input = screen.getByRole('combobox');
    fireEvent.change(input, { target: { value: '10 D' } });

    await screen.findByRole('listbox');
    fireEvent.keyDown(input, { key: 'Escape' });

    await waitFor(() => expect(screen.queryByRole('listbox')).not.toBeInTheDocument());
  });

  it('keyboard: Enter selects the active suggestion', async () => {
    const handlePlaceSelect = vi.fn();
    mockedGet.mockResolvedValue(OS_RESPONSE);
    render(<OsPlacesAutocomplete value="" onChange={vi.fn()} onPlaceSelect={handlePlaceSelect} />);
    const input = screen.getByRole('combobox');
    fireEvent.change(input, { target: { value: '10 D' } });

    await screen.findByRole('listbox');
    fireEvent.keyDown(input, { key: 'ArrowDown' });
    fireEvent.keyDown(input, { key: 'Enter' });

    expect(handlePlaceSelect).toHaveBeenCalledWith(expect.objectContaining({ uprn: '12345678' }));
  });
});
