/**
 * PlaceAutocompleteInput — Google Places-powered location input.
 *
 * Drop-in replacement for HeroUI <Input> wherever location is needed.
 * Uses Google Places Autocomplete (New) API for suggestions.
 * Falls back to plain text input if Google Maps API is not loaded.
 *
 * @example
 * <PlaceAutocompleteInput
 *   label="Location"
 *   placeholder="Start typing an address..."
 *   value={location}
 *   onPlaceSelect={(place) => {
 *     setLocation(place.formattedAddress);
 *     setLatitude(place.lat);
 *     setLongitude(place.lng);
 *   }}
 *   onChange={(val) => setLocation(val)}
 * />
 */

import { useState, useEffect, useRef, useCallback } from 'react';
import { Input } from '@heroui/react';
import { MapPin, X } from 'lucide-react';
import { useMapsLibrary } from '@vis.gl/react-google-maps';
import type { PlaceAutocompleteInputProps, PlaceResult, AddressComponents } from '@/types/google-places';

/** Debounce delay for autocomplete requests (ms). */
const DEBOUNCE_MS = 300;

/** Minimum characters before triggering autocomplete. */
const MIN_CHARS = 3;

/** Parse Google address components into our structured format. */
function parseAddressComponents(
  components: google.maps.places.AddressComponent[]
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

  // Load the Places library via the Google Maps provider
  const placesLib = useMapsLibrary('places');

  const [suggestions, setSuggestions] = useState<google.maps.places.AutocompleteSuggestion[]>([]);
  const [isOpen, setIsOpen] = useState(false);
  const [activeIndex, setActiveIndex] = useState(-1);

  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const sessionTokenRef = useRef<google.maps.places.AutocompleteSessionToken | null>(null);
  const wrapperRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  // Create session token when Places library loads
  useEffect(() => {
    if (placesLib) {
      sessionTokenRef.current = new placesLib.AutocompleteSessionToken();
    }
  }, [placesLib]);

  // Close dropdown on outside click
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

  // Fetch suggestions from Google Places
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
    [placesLib]
  );

  // Handle input change with debounce
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
    [onChange, placesLib, fetchSuggestions]
  );

  // Handle selection of a suggestion
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

        // Fresh session token for next search
        sessionTokenRef.current = new placesLib.AutocompleteSessionToken();
      } catch (err) {
        console.warn('Google Places details fetch error:', err);
      }
    },
    [placesLib, onChange, onPlaceSelect]
  );

  // Handle clear
  const handleClear = useCallback(() => {
    onChange?.('');
    onClear?.();
    setSuggestions([]);
    setIsOpen(false);
    inputRef.current?.focus();
  }, [onChange, onClear]);

  // Keyboard navigation
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
            handleSelect(suggestions[activeIndex]);
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
            <button
              type="button"
              onClick={handleClear}
              className="p-0.5 rounded-full hover:bg-default-200 transition-colors"
              aria-label="Clear location"
            >
              <X className="w-3.5 h-3.5 text-theme-subtle" />
            </button>
          ) : undefined
        }
        classNames={classNames}
      />

      {/* Suggestions dropdown */}
      {isOpen && suggestions.length > 0 && (
        <ul
          role="listbox"
          className="absolute z-50 mt-1 w-full max-h-60 overflow-y-auto rounded-xl
                     border border-glass-border bg-theme-surface/95 backdrop-blur-xl
                     shadow-lg shadow-black/10 dark:shadow-black/30"
        >
          {suggestions.map((suggestion, index) => {
            const prediction = suggestion.placePrediction;
            if (!prediction) return null;

            const mainText = prediction.mainText?.toString() || '';
            const secondaryText = prediction.secondaryText?.toString() || '';

            return (
              <li
                key={prediction.placeId}
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
                  e.preventDefault(); // Prevent input blur before click registers
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

          {/* Google attribution — required by Terms of Service */}
          <li className="px-3 py-1.5 text-right" aria-hidden="true">
            <span className="text-[10px] text-theme-subtle">
              Powered by Google
            </span>
          </li>
        </ul>
      )}
    </div>
  );
}

/**
 * PlaceAutocompleteInput — the public API.
 *
 * Renders the Google-powered autocomplete when APIProvider is available.
 * The GoogleMapsProvider in App.tsx gracefully skips APIProvider when no
 * API key is set, so useMapsLibrary will return null and the component
 * works as a plain text input (suggestions just never appear).
 */
export function PlaceAutocompleteInput(props: PlaceAutocompleteInputProps) {
  // If no API key configured, GoogleMapsProvider renders without APIProvider.
  // In that case, useMapsLibrary isn't available, so we render a plain fallback.
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;
  if (!apiKey) {
    return <PlaceAutocompleteFallback {...props} />;
  }

  return <PlaceAutocompleteWithGoogle {...props} />;
}

/**
 * Plain text fallback when Google Maps API is not available.
 */
function PlaceAutocompleteFallback(props: PlaceAutocompleteInputProps) {
  const {
    value,
    onChange,
    onClear,
    label = 'Location',
    placeholder = 'Enter your location...',
    isRequired = false,
    errorMessage,
    isInvalid,
    classNames,
    className,
    showIcon = true,
  } = props;

  return (
    <div className={`relative ${className || ''}`}>
      <Input
        type="text"
        label={label}
        placeholder={placeholder}
        value={value}
        onChange={(e) => onChange?.(e.target.value)}
        isRequired={isRequired}
        errorMessage={errorMessage}
        isInvalid={isInvalid}
        autoComplete="address-level2"
        startContent={
          showIcon ? (
            <MapPin className="w-4 h-4 text-theme-subtle flex-shrink-0" aria-hidden="true" />
          ) : undefined
        }
        endContent={
          value ? (
            <button
              type="button"
              onClick={() => {
                onChange?.('');
                onClear?.();
              }}
              className="p-0.5 rounded-full hover:bg-default-200 transition-colors"
              aria-label="Clear location"
            >
              <X className="w-3.5 h-3.5 text-theme-subtle" />
            </button>
          ) : undefined
        }
        classNames={classNames}
      />
    </div>
  );
}
