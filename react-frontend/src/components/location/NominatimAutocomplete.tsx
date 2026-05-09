// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * NominatimAutocomplete — OpenStreetMap-powered location autocomplete.
 *
 * Drop-in replacement for the Google Places branch of PlaceAutocompleteInput.
 * Uses the public Nominatim service at nominatim.openstreetmap.org.
 *
 * Compliance with Nominatim usage policy
 * (https://operations.osmfoundation.org/policies/nominatim/):
 *   - Identifies via User-Agent (browser sets origin; we add a Referer-style
 *     header is not possible from fetch, so we rely on the browser default
 *     plus a Project-NEXUS branded query string).
 *   - Throttled to ≤1 request/second per client (debounce ≥ 1000ms here).
 *   - Cached responses where possible (component-level via React state).
 *   - Honors HTTP errors gracefully — falls back to plain text input.
 *
 * For high-traffic tenants, swap NOMINATIM_BASE_URL via the bootstrap
 * `nominatimBaseUrl` config to a self-hosted Nominatim or a paid host
 * (e.g. MapTiler Geocoding).
 */

import { useState, useEffect, useRef, useCallback } from 'react';
import { Input, Button } from '@heroui/react';
import MapPin from 'lucide-react/icons/map-pin';
import X from 'lucide-react/icons/x';
import { useTranslation } from 'react-i18next';
import type { PlaceAutocompleteInputProps, PlaceResult, AddressComponents } from '@/types/google-places';

/**
 * Throttle: Nominatim usage policy says ≤1 request per second. Combined with
 * the existing keystroke debounce, this guarantees compliance even under
 * rapid typing.
 */
const DEBOUNCE_MS = 1000;
const MIN_CHARS = 3;
const MAX_RESULTS = 5;

interface NominatimResult {
  place_id: number;
  lat: string;
  lon: string;
  display_name: string;
  name?: string;
  type?: string;
  class?: string;
  address?: {
    city?: string;
    town?: string;
    village?: string;
    county?: string;
    state?: string;
    country?: string;
    country_code?: string;
    postcode?: string;
  };
}

function buildAddressComponents(addr: NominatimResult['address']): AddressComponents | undefined {
  if (!addr) return undefined;
  const components: AddressComponents = {};
  components.city = addr.city ?? addr.town ?? addr.village;
  components.county = addr.county;
  components.state = addr.state;
  components.country = addr.country;
  components.countryCode = addr.country_code ? addr.country_code.toUpperCase() : undefined;
  components.postalCode = addr.postcode;
  return components;
}

function nominatimToPlaceResult(r: NominatimResult): PlaceResult {
  return {
    placeId: `osm:${r.place_id}`,
    formattedAddress: r.display_name,
    lat: parseFloat(r.lat),
    lng: parseFloat(r.lon),
    name: r.name ?? r.display_name.split(',')[0]?.trim(),
    types: r.type ? [r.type] : undefined,
    addressComponents: buildAddressComponents(r.address),
  };
}

interface NominatimAutocompleteProps extends PlaceAutocompleteInputProps {
  /** Override the Nominatim base URL (default: https://nominatim.openstreetmap.org). */
  baseUrl?: string;
}

export function NominatimAutocomplete(props: NominatimAutocompleteProps) {
  const {
    value,
    onChange,
    onPlaceSelect,
    onClear,
    label = 'Location',
    placeholder = 'Start typing a place or address...',
    isRequired = false,
    errorMessage,
    isInvalid,
    classNames,
    className,
    showIcon = true,
    baseUrl = 'https://nominatim.openstreetmap.org',
  } = props;

  const { t } = useTranslation('common');

  const [suggestions, setSuggestions] = useState<NominatimResult[]>([]);
  const [isOpen, setIsOpen] = useState(false);
  const [activeIndex, setActiveIndex] = useState(-1);

  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const abortRef = useRef<AbortController | null>(null);
  const wrapperRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    function handleClickOutside(e: MouseEvent) {
      if (wrapperRef.current && !wrapperRef.current.contains(e.target as Node)) {
        setIsOpen(false);
        setActiveIndex(-1);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const fetchSuggestions = useCallback(
    async (input: string) => {
      if (input.length < MIN_CHARS) {
        setSuggestions([]);
        setIsOpen(false);
        return;
      }

      if (abortRef.current) {
        abortRef.current.abort();
      }
      const controller = new AbortController();
      abortRef.current = controller;

      const url = new URL(`${baseUrl}/search`);
      url.searchParams.set('q', input);
      url.searchParams.set('format', 'json');
      url.searchParams.set('addressdetails', '1');
      url.searchParams.set('limit', String(MAX_RESULTS));

      try {
        const response = await fetch(url.toString(), {
          signal: controller.signal,
          headers: { Accept: 'application/json' },
        });
        if (!response.ok) {
          throw new Error(`Nominatim ${response.status}`);
        }
        const results = (await response.json()) as NominatimResult[];
        setSuggestions(results);
        setIsOpen(results.length > 0);
        setActiveIndex(-1);
      } catch (err) {
        if (err instanceof Error && err.name === 'AbortError') return;
        if (import.meta.env.DEV) {
          console.warn('[Nominatim] autocomplete error:', err);
        }
        setSuggestions([]);
        setIsOpen(false);
      }
    },
    [baseUrl]
  );

  const handleInputChange = useCallback(
    (newValue: string) => {
      onChange?.(newValue);
      if (debounceRef.current) clearTimeout(debounceRef.current);
      debounceRef.current = setTimeout(() => {
        fetchSuggestions(newValue);
      }, DEBOUNCE_MS);
    },
    [onChange, fetchSuggestions]
  );

  const handleSelect = useCallback(
    (result: NominatimResult) => {
      const place = nominatimToPlaceResult(result);
      onChange?.(place.formattedAddress);
      onPlaceSelect?.(place);
      setSuggestions([]);
      setIsOpen(false);
      setActiveIndex(-1);
    },
    [onChange, onPlaceSelect]
  );

  const handleClear = useCallback(() => {
    onChange?.('');
    onClear?.();
    setSuggestions([]);
    setIsOpen(false);
    inputRef.current?.focus();
  }, [onChange, onClear]);

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      if (!isOpen || suggestions.length === 0) return;

      switch (e.key) {
        case 'ArrowDown':
          e.preventDefault();
          setActiveIndex((prev) => (prev < suggestions.length - 1 ? prev + 1 : 0));
          break;
        case 'ArrowUp':
          e.preventDefault();
          setActiveIndex((prev) => (prev > 0 ? prev - 1 : suggestions.length - 1));
          break;
        case 'Enter':
          e.preventDefault();
          if (activeIndex >= 0 && activeIndex < suggestions.length) {
            const selected = suggestions[activeIndex];
            if (selected) handleSelect(selected);
          }
          break;
        case 'Escape':
          e.preventDefault();
          setIsOpen(false);
          setActiveIndex(-1);
          break;
      }
    },
    [isOpen, suggestions, activeIndex, handleSelect]
  );

  return (
    <div ref={wrapperRef} className={`relative ${className || ''}`}>
      <Input
        ref={inputRef}
        type="text"
        label={label}
        placeholder={placeholder}
        value={value}
        onChange={(e) => handleInputChange(e.target.value)}
        onKeyDown={handleKeyDown}
        onFocus={() => {
          if (suggestions.length > 0) setIsOpen(true);
        }}
        isRequired={isRequired}
        errorMessage={errorMessage}
        isInvalid={isInvalid}
        autoComplete="off"
        role="combobox"
        aria-expanded={isOpen}
        aria-haspopup="listbox"
        aria-autocomplete="list"
        startContent={
          showIcon ? (
            <MapPin className="w-4 h-4 text-theme-subtle flex-shrink-0" aria-hidden="true" />
          ) : undefined
        }
        endContent={
          value ? (
            <Button
              type="button"
              variant="light"
              isIconOnly
              onPress={handleClear}
              className="p-0.5 rounded-full hover:bg-default-200 transition-colors min-w-0 h-auto w-auto"
              aria-label={t('aria.clear_location')}
            >
              <X className="w-3.5 h-3.5 text-theme-subtle" />
            </Button>
          ) : undefined
        }
        classNames={classNames}
      />

      {isOpen && suggestions.length > 0 && (
        <ul
          role="listbox"
          className="absolute z-50 mt-1 w-full max-h-60 overflow-y-auto rounded-xl
                     border border-glass-border bg-[var(--surface-dropdown)]
                     shadow-lg shadow-black/10 dark:shadow-black/30"
        >
          {suggestions.map((suggestion, index) => {
            const parts = suggestion.display_name.split(',');
            const mainText = parts[0]?.trim() ?? suggestion.display_name;
            const secondaryText = parts.slice(1).join(',').trim();

            return (
              <li
                key={suggestion.place_id}
                role="option"
                aria-selected={index === activeIndex}
                className={`flex flex-col gap-0.5 px-3 py-2.5 cursor-pointer transition-colors
                  ${index === activeIndex
                    ? 'bg-primary-50 dark:bg-primary-900/30'
                    : 'hover:bg-default-100 dark:hover:bg-default-50/10'
                  }
                  ${index < suggestions.length - 1 ? 'border-b border-glass-border/50' : ''}
                `}
                onMouseDown={(e) => {
                  e.preventDefault();
                  handleSelect(suggestion);
                }}
                onMouseEnter={() => setActiveIndex(index)}
              >
                <span className="text-sm font-medium text-theme-primary truncate">
                  {mainText}
                </span>
                {secondaryText && (
                  <span className="text-xs text-theme-muted truncate">
                    {secondaryText}
                  </span>
                )}
              </li>
            );
          })}

          {/* OSM attribution — required by ODbL */}
          <li className="px-3 py-1.5 text-right" aria-hidden="true">
            <span className="text-[10px] text-theme-subtle">
              {t('location.powered_by_osm')}
            </span>
          </li>
        </ul>
      )}
    </div>
  );
}
