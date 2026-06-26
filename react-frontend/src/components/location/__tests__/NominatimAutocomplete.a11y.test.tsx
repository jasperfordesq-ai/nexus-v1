// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Regression test for the location-autocomplete combobox a11y contract.
 *
 * Locks the fix shared by NominatimAutocomplete / PlaceAutocompleteInput /
 * OsPlacesAutocomplete: arrow-key navigation must move `aria-activedescendant`
 * on the combobox input to a real, existing option `id` (otherwise screen
 * readers announce nothing as the user arrows through results), and the result
 * count must be exposed via a polite live region.
 */

import { useState } from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, within, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { NominatimAutocomplete } from '../NominatimAutocomplete';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      if (key === 'aria.location_results') {
        return `${opts?.count ?? 0} address suggestions available`;
      }
      const labels: Record<string, string> = {
        'aria.clear_location': 'Clear location',
        'location.powered_by_osm': 'Powered by OpenStreetMap',
      };
      return labels[key] ?? key;
    },
    i18n: { language: 'en', changeLanguage: () => Promise.resolve() },
  }),
  Trans: ({ children }: { children: unknown }) => children,
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

const fetchMock = vi.fn();

beforeEach(() => {
  vi.stubGlobal('fetch', fetchMock);
});

afterEach(() => {
  vi.unstubAllGlobals();
  vi.clearAllMocks();
});

function Harness() {
  const [value, setValue] = useState('');
  return <NominatimAutocomplete value={value} onChange={setValue} />;
}

describe('NominatimAutocomplete — combobox a11y contract', () => {
  it('points aria-activedescendant at a real option id on arrow-key navigation', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => [
        { place_id: 1, lat: '53.35', lon: '-6.26', display_name: 'Dublin, Ireland' },
        { place_id: 2, lat: '51.90', lon: '-8.47', display_name: 'Cork, Ireland' },
      ],
    });

    const user = userEvent.setup();
    render(<Harness />);

    const input = screen.getByRole('combobox');
    // No active option before the list opens.
    expect(input).not.toHaveAttribute('aria-activedescendant');

    await user.type(input, 'Dub');

    const listbox = await screen.findByRole('listbox', {}, { timeout: 4000 });
    const options = within(listbox).getAllByRole('option');
    expect(options).toHaveLength(2);

    // Every option carries a stable id so it can be referenced.
    for (const option of options) {
      expect(option.id).toMatch(/-opt-\d+$/);
    }

    // Arrowing down activates the first option AND wires the input to its id.
    await user.keyboard('{ArrowDown}');
    await waitFor(() => {
      const active = input.getAttribute('aria-activedescendant');
      expect(active).toBeTruthy();
      expect(document.getElementById(active as string)).not.toBeNull();
    });

    // The activedescendant must equal the option flagged aria-selected.
    const active = input.getAttribute('aria-activedescendant');
    const selected = within(listbox)
      .getAllByRole('option')
      .find((o) => o.getAttribute('aria-selected') === 'true');
    expect(selected?.id).toBe(active);
  });

  it('announces the result count in a polite live region', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => [
        { place_id: 1, lat: '53.35', lon: '-6.26', display_name: 'Dublin, Ireland' },
        { place_id: 2, lat: '51.90', lon: '-8.47', display_name: 'Cork, Ireland' },
        { place_id: 3, lat: '52.66', lon: '-8.63', display_name: 'Limerick, Ireland' },
      ],
    });

    const user = userEvent.setup();
    render(<Harness />);

    const input = screen.getByRole('combobox');
    await user.type(input, 'Ire');

    await screen.findByRole('listbox', {}, { timeout: 4000 });
    const status = await screen.findByRole('status');
    expect(status).toHaveTextContent('3 address suggestions available');
  });
});
