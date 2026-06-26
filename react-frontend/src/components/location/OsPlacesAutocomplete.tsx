// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * OsPlacesAutocomplete — Ordnance Survey Places API address autocomplete.
 *
 * Drop-in replacement for the Google Places / Nominatim branches of
 * PlaceAutocompleteInput, used when the tenant's `geocoding_provider`
 * is `os_places`. Queries the platform's own proxy endpoint
 * (/v2/geo/os-places/search) — the OS Data Hub key never reaches the
 * browser. Results are UPRN-backed (AddressBase), giving validated UK
 * addresses with WGS 84 coordinates; the UPRN is surfaced on the
 * selected PlaceResult for callers that wish to store it.
 *
 * Degrades gracefully: when the proxy reports the provider disabled or
 * a lookup fails, the field keeps working as a plain text input.
 */

import { useState, useEffect, useRef, useCallback, useId } from 'react';
import MapPin from 'lucide-react/icons/map-pin';
import X from 'lucide-react/icons/x';
import { useTranslation } from 'react-i18next';
import type { PlaceAutocompleteInputProps, PlaceResult } from '@/types/google-places';
import { Button, Input } from '@/components/ui';
import { api } from '@/lib/api';

const DEBOUNCE_MS = 350;
const MIN_CHARS = 3;

interface OsPlacesResult {
  uprn: string | null;
  address: string;
  postcode: string | null;
  post_town: string | null;
  lat: number;
  lng: number;
}

interface OsPlacesSearchResponse {
  enabled: boolean;
  results: OsPlacesResult[];
}

function osPlacesToPlaceResult(r: OsPlacesResult): PlaceResult {
  return {
    placeId: r.uprn ? `uprn:${r.uprn}` : `osplaces:${r.lat},${r.lng}`,
    formattedAddress: r.address,
    lat: r.lat,
    lng: r.lng,
    name: r.address.split(',')[0]?.trim(),
    uprn: r.uprn ?? undefined,
    addressComponents: {
      city: r.post_town ?? undefined,
      country: 'United Kingdom',
      countryCode: 'GB',
      postalCode: r.postcode ?? undefined,
    },
  };
}

export function OsPlacesAutocomplete(props: PlaceAutocompleteInputProps) {
  const {
    value,
    onChange,
    onPlaceSelect,
    onClear,
    label = 'Location',
    placeholder = 'Start typing a UK address...',
    isRequired = false,
    errorMessage,
    isInvalid,
    classNames,
    className,
    showIcon = true,
  } = props;

  const { t } = useTranslation('common');

  const [suggestions, setSuggestions] = useState<OsPlacesResult[]>([]);
  const [isOpen, setIsOpen] = useState(false);
  const listboxId = useId();
  const [activeIndex, setActiveIndex] = useState(-1);

  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const requestSeqRef = useRef(0);
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

  const fetchSuggestions = useCallback(async (input: string) => {
    if (input.length < MIN_CHARS) {
      setSuggestions([]);
      setIsOpen(false);
      return;
    }

    const seq = ++requestSeqRef.current;
    try {
      const response = await api.get<OsPlacesSearchResponse>(
        `/v2/geo/os-places/search?q=${encodeURIComponent(input)}`
      );
      // Discard responses that arrive after a newer query was issued
      if (seq !== requestSeqRef.current) return;

      const results = response.success && response.data?.enabled ? response.data.results : [];
      setSuggestions(results);
      setIsOpen(results.length > 0);
      setActiveIndex(-1);
    } catch (err) {
      if (seq !== requestSeqRef.current) return;
      if (import.meta.env.DEV) {
        console.warn('[OsPlaces] autocomplete error:', err);
      }
      setSuggestions([]);
      setIsOpen(false);
    }
  }, []);

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
    (result: OsPlacesResult) => {
      const place = osPlacesToPlaceResult(result);
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
        aria-controls={listboxId}
        aria-activedescendant={activeIndex >= 0 ? `${listboxId}-opt-${activeIndex}` : undefined}
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
              className="p-0.5 rounded-full hover:bg-surface-tertiary transition-colors min-w-0 min-h-9 w-auto"
              aria-label={t('aria.clear_location')}
            >
              <X className="w-3.5 h-3.5 text-theme-subtle" />
            </Button>
          ) : undefined
        }
        classNames={classNames}
      />

      <div className="sr-only" role="status" aria-live="polite">
        {isOpen && suggestions.length > 0
          ? t('aria.location_results', { count: suggestions.length })
          : ''}
      </div>

      {isOpen && suggestions.length > 0 && (
        <ul
          id={listboxId}
          role="listbox"
          className="absolute z-50 mt-1 w-full max-h-60 overflow-y-auto rounded-xl
                     border border-glass-border bg-[var(--surface-dropdown)]
                     shadow-lg shadow-black/10 dark:shadow-black/30"
        >
          {suggestions.map((suggestion, index) => {
            const parts = suggestion.address.split(',');
            const mainText = parts[0]?.trim() ?? suggestion.address;
            const secondaryText = parts.slice(1).join(',').trim();

            return (
              <li
                key={suggestion.uprn ?? `${suggestion.lat},${suggestion.lng}`}
                id={`${listboxId}-opt-${index}`}
                role="option"
                aria-selected={index === activeIndex}
                className={`flex flex-col gap-0.5 px-3 py-2.5 cursor-pointer transition-colors
                  ${index === activeIndex
                    ? 'bg-accent-soft dark:bg-accent-soft'
                    : 'hover:bg-surface-secondary dark:hover:bg-surface-secondary/10'
                  }
                  ${index < suggestions.length - 1 ? 'border-b border-glass-border/50' : ''}
                `}
                onMouseDown={(e) => {
                  e.preventDefault();
                  handleSelect(suggestion);
                }}
                onMouseEnter={() => setActiveIndex(index)}
              >
                <span className="text-sm font-medium text-theme-primary">{mainText}</span>
                {secondaryText && (
                  <span className="text-xs text-theme-subtle">{secondaryText}</span>
                )}
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
