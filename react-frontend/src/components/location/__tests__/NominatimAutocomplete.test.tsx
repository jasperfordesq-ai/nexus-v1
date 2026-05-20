// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { HeroUIProvider } from '@heroui/react';
import { MemoryRouter } from 'react-router-dom';
import { NominatimAutocomplete } from '../NominatimAutocomplete';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (k: string, o?: { fallbackValue?: string }) => o?.fallbackValue ?? k,
  }),
}));

function W({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter>{children}</MemoryRouter>
    </HeroUIProvider>
  );
}

const sampleResult = [
  {
    place_id: 1234,
    lat: '53.349805',
    lon: '-6.260310',
    display_name: 'Dublin Castle, Castle Street, Dublin, Ireland',
    name: 'Dublin Castle',
    address: { city: 'Dublin', country: 'Ireland', country_code: 'ie' },
  },
];

// Slightly above the component's 1000ms debounce.
const PAST_DEBOUNCE = 1300;

function getInput() {
  return screen.getByRole('combobox') as HTMLInputElement;
}

describe('NominatimAutocomplete', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('does not query Nominatim until the user has typed MIN_CHARS (3)', async () => {
    const fetchSpy = vi
      .spyOn(global, 'fetch')
      .mockResolvedValue(new Response(JSON.stringify([]), { status: 200 }));

    render(
      <W>
        <NominatimAutocomplete value="ab" onChange={() => {}} />
      </W>
    );
    fireEvent.change(getInput(), { target: { value: 'ab' } });

    await new Promise((r) => setTimeout(r, PAST_DEBOUNCE));
    expect(fetchSpy).not.toHaveBeenCalled();
  });

  it('hits the Nominatim /search endpoint with the typed query after debounce', async () => {
    const fetchSpy = vi
      .spyOn(global, 'fetch')
      .mockResolvedValue(new Response(JSON.stringify(sampleResult), { status: 200 }));

    render(
      <W>
        <NominatimAutocomplete value="" onChange={() => {}} />
      </W>
    );
    fireEvent.change(getInput(), { target: { value: 'dublin' } });

    await waitFor(
      () => {
        expect(fetchSpy).toHaveBeenCalledTimes(1);
      },
      { timeout: 2000 }
    );
    const url = String(fetchSpy.mock.calls[0]?.[0] ?? '');
    expect(url).toContain('https://nominatim.openstreetmap.org/search');
    expect(url).toContain('q=dublin');
    expect(url).toContain('format=json');
    expect(url).toContain('addressdetails=1');
    expect(url).toContain('limit=5');
  });

  it('respects a custom baseUrl (self-hosted, MapTiler, etc.)', async () => {
    const fetchSpy = vi
      .spyOn(global, 'fetch')
      .mockResolvedValue(new Response(JSON.stringify([]), { status: 200 }));

    render(
      <W>
        <NominatimAutocomplete value="" onChange={() => {}} baseUrl="https://geo.example.com" />
      </W>
    );
    fireEvent.change(getInput(), { target: { value: 'london' } });

    await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(1), { timeout: 2000 });
    const url = String(fetchSpy.mock.calls[0]?.[0] ?? '');
    expect(url.startsWith('https://geo.example.com/search')).toBe(true);
  });

  it('emits a structured PlaceResult on selection', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValue(
      new Response(JSON.stringify(sampleResult), { status: 200 })
    );

    const onPlaceSelect = vi.fn();
    render(
      <W>
        <NominatimAutocomplete value="" onChange={() => {}} onPlaceSelect={onPlaceSelect} />
      </W>
    );
    fireEvent.change(getInput(), { target: { value: 'dublin' } });

    await waitFor(() => expect(screen.queryByRole('listbox')).toBeTruthy(), { timeout: 2000 });

    const option = screen.getAllByRole('option')[0]!;
    fireEvent.mouseDown(option);

    expect(onPlaceSelect).toHaveBeenCalledWith(
      expect.objectContaining({
        placeId: 'osm:1234',
        lat: 53.349805,
        lng: -6.26031,
        formattedAddress: 'Dublin Castle, Castle Street, Dublin, Ireland',
        addressComponents: expect.objectContaining({
          city: 'Dublin',
          country: 'Ireland',
          countryCode: 'IE',
        }),
      })
    );
  });

  it('falls back gracefully (no listbox) when Nominatim returns an HTTP error', async () => {
    const fetchSpy = vi
      .spyOn(global, 'fetch')
      .mockResolvedValue(new Response('rate limited', { status: 429 }));

    render(
      <W>
        <NominatimAutocomplete value="" onChange={() => {}} />
      </W>
    );
    fireEvent.change(getInput(), { target: { value: 'dublin' } });

    await waitFor(() => expect(fetchSpy).toHaveBeenCalledTimes(1), { timeout: 2000 });
    // After the failed fetch resolves, no suggestions should be shown.
    await new Promise((r) => setTimeout(r, 100));
    expect(screen.queryByRole('listbox')).toBeNull();
  });
});
