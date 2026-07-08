// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useRef, useCallback, useId, type ReactNode } from 'react';
import MapPin from 'lucide-react/icons/map-pin';
import X from 'lucide-react/icons/x';
import { useTranslation } from 'react-i18next';
import { useMapsLibrary } from '@vis.gl/react-google-maps';
import type { PlaceAutocompleteInputProps, PlaceResult, AddressComponents } from '@/types/google-places';
import { GoogleMapsProvider } from './GoogleMapsProvider';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';

/** Debounce delay for autocomplete requests (ms). */
const DEBOUNCE_MS = 300;

/** Minimum characters before triggering autocomplete. */
const MIN_CHARS = 3;

interface GooglePlaceAutocompleteProps extends PlaceAutocompleteInputProps {
  fallback?: ReactNode;
}

/** Parse Google address components into our structured format. */
function parseAddressComponents(
  components: google.maps.places.AddressComponent[],
): AddressComponents {
  const result: AddressComponents = {};

  for (const c of components) {
    const types = c.types;
    if (types.includes('locality')) {
      result.city = c.longText ?? undefined;
    } else if (types.includes('administrative_area_level_2')) {
      result.county = c.longText ?? undefined;
    } else if (types.includes('administrative_area_level_1')) {
      result.state = c.longText ?? undefined;
    } else if (types.includes('country')) {
      result.country = c.longText ?? undefined;
      result.countryCode = c.shortText ?? undefined;
    } else if (types.includes('postal_code')) {
      result.postalCode = c.longText ?? undefined;
    }
  }

  return result;
}

export function GooglePlaceAutocomplete({ fallback, ...props }: GooglePlaceAutocompleteProps) {
  return (
    <GoogleMapsProvider fallback={fallback}>
      <PlaceAutocompleteWithGoogle {...props} />
    </GoogleMapsProvider>
  );
}

/**
 * Inner component that uses the Google Maps Places library.
 * This must be rendered inside an APIProvider.
 */
function PlaceAutocompleteWithGoogle(props: PlaceAutocompleteInputProps) {
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
  } = props;

  const { t } = useTranslation('common');

  const placesLib = useMapsLibrary('places');
  const [suggestions, setSuggestions] = useState<google.maps.places.AutocompleteSuggestion[]>([]);
  const [isOpen, setIsOpen] = useState(false);
  const listboxId = useId();
  const [activeIndex, setActiveIndex] = useState(-1);

  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const sessionTokenRef = useRef<google.maps.places.AutocompleteSessionToken | null>(null);
  const wrapperRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (placesLib) {
      sessionTokenRef.current = new placesLib.AutocompleteSessionToken();
    }
  }, [placesLib]);

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
      if (!placesLib || input.length < MIN_CHARS) {
        setSuggestions([]);
        setIsOpen(false);
        return;
      }

      try {
        const request: google.maps.places.AutocompleteRequest = {
          input,
          sessionToken: sessionTokenRef.current!,
        };

        const { suggestions: results } =
          await google.maps.places.AutocompleteSuggestion.fetchAutocompleteSuggestions(request);

        setSuggestions(results);
        setIsOpen(results.length > 0);
        setActiveIndex(-1);
      } catch (err) {
        console.warn('Google Places autocomplete error:', err);
        setSuggestions([]);
        setIsOpen(false);
      }
    },
    [placesLib],
  );

  const handleInputChange = useCallback(
    (newValue: string) => {
      onChange?.(newValue);

      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }

      if (!placesLib) return;

      debounceRef.current = setTimeout(() => {
        fetchSuggestions(newValue);
      }, DEBOUNCE_MS);
    },
    [onChange, placesLib, fetchSuggestions],
  );

  const handleSelect = useCallback(
    async (suggestion: google.maps.places.AutocompleteSuggestion) => {
      const placePrediction = suggestion.placePrediction;
      if (!placePrediction || !placesLib) return;

      try {
        const place = placePrediction.toPlace();
        await place.fetchFields({
          fields: ['displayName', 'formattedAddress', 'location', 'addressComponents', 'types'],
        });

        const location = place.location;
        if (!location) return;

        const result: PlaceResult = {
          placeId: placePrediction.placeId,
          formattedAddress: place.formattedAddress || placePrediction.text.toString(),
          lat: location.lat(),
          lng: location.lng(),
          name: place.displayName ?? undefined,
          types: place.types ?? undefined,
          addressComponents: place.addressComponents
            ? parseAddressComponents(place.addressComponents)
            : undefined,
        };

        onChange?.(result.formattedAddress);
        onPlaceSelect?.(result);

        setSuggestions([]);
        setIsOpen(false);
        setActiveIndex(-1);
        sessionTokenRef.current = new placesLib.AutocompleteSessionToken();
      } catch (err) {
        console.warn('Google Places details fetch error:', err);
      }
    },
    [placesLib, onChange, onPlaceSelect],
  );

  const handleClear = useCallback(() => {
    onChange?.('');
    onClear?.();
    setSuggestions([]);
    setIsOpen(false);
    inputRef.current?.focus();
  }, [onChange, onClear]);

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent<HTMLInputElement>) => {
      if (!isOpen || suggestions.length === 0) return;

      switch (e.key) {
        case 'ArrowDown':
          e.preventDefault();
          setActiveIndex((i) => (i + 1) % suggestions.length);
          break;
        case 'ArrowUp':
          e.preventDefault();
          setActiveIndex((i) => (i <= 0 ? suggestions.length - 1 : i - 1));
          break;
        case 'Enter':
          if (activeIndex >= 0) {
            const suggestion = suggestions[activeIndex];
            if (!suggestion) return;
            e.preventDefault();
            void handleSelect(suggestion);
          }
          break;
        case 'Escape':
          setIsOpen(false);
          setActiveIndex(-1);
          break;
      }
    },
    [isOpen, suggestions, activeIndex, handleSelect],
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
        isRequired={isRequired}
        errorMessage={errorMessage}
        isInvalid={isInvalid}
        autoComplete="off"
        role="combobox"
        aria-expanded={isOpen}
        aria-controls={isOpen ? listboxId : undefined}
        aria-autocomplete="list"
        aria-activedescendant={activeIndex >= 0 ? `${listboxId}-${activeIndex}` : undefined}
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

      {isOpen && suggestions.length > 0 && (
        <ul
          id={listboxId}
          role="listbox"
          className="absolute z-50 w-full mt-1 bg-surface border border-theme-default rounded-lg shadow-lg max-h-64 overflow-y-auto"
        >
          {suggestions.map((suggestion, index) => {
            const prediction = suggestion.placePrediction;
            if (!prediction) return null;

            const mainText = prediction.mainText?.toString() ?? prediction.text.toString();
            const secondaryText = prediction.secondaryText?.toString();

            return (
              <li
                key={prediction.placeId}
                id={`${listboxId}-${index}`}
                role="option"
                aria-selected={index === activeIndex}
                onMouseDown={(e) => {
                  e.preventDefault();
                  void handleSelect(suggestion);
                }}
                onMouseEnter={() => setActiveIndex(index)}
                className={`px-3 py-2 cursor-pointer transition-colors ${
                  index === activeIndex ? 'bg-primary/10 text-primary' : 'hover:bg-surface-hover'
                }`}
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

          <li className="px-3 py-1.5 text-right" aria-hidden="true">
            <span className="text-[10px] text-theme-subtle">
              {t('location.powered_by_google')}
            </span>
          </li>
        </ul>
      )}
    </div>
  );
}
