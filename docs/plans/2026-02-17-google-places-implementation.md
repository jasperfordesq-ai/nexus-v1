# Google Places Autocomplete Module — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace all Mapbox integration and plain-text location inputs with a Google Places Autocomplete module using Google's official React library.

**Architecture:** `GoogleMapsProvider` wraps the app via `<APIProvider>` from `@vis.gl/react-google-maps`. A reusable `PlaceAutocompleteInput` component uses the new Places API (`AutocompleteSuggestion`) to show suggestions in a HeroUI-styled dropdown. On selection, it returns formatted address + lat/lng + address components. Backend already accepts lat/lng — we just start sending them from the frontend. Mapbox removed entirely.

**Tech Stack:** `@vis.gl/react-google-maps`, Google Places API (New), React 18, TypeScript, HeroUI, Tailwind CSS 4

**Design doc:** `docs/plans/2026-02-17-google-places-module-design.md`

---

## Task 1: Install dependency & configure environment

**Files:**
- Modify: `react-frontend/package.json`
- Modify: `react-frontend/.env.example`
- Modify: `.env.docker`

**Step 1: Install the Google Maps React library**

```bash
cd react-frontend && npm install @vis.gl/react-google-maps
```

**Step 2: Add `VITE_GOOGLE_MAPS_API_KEY` to `.env.example`**

Add to `react-frontend/.env.example`:
```
# Google Maps (Places Autocomplete)
VITE_GOOGLE_MAPS_API_KEY=
```

**Step 3: Add `GOOGLE_MAPS_API_KEY` to `.env.docker`**

Replace the Mapbox section in `.env.docker` (lines 46-47):
```
# Google Maps (Places Autocomplete + Geocoding)
GOOGLE_MAPS_API_KEY=
```

**Step 4: Commit**

```bash
git add react-frontend/package.json react-frontend/package-lock.json react-frontend/.env.example .env.docker
git commit -m "feat(location): install @vis.gl/react-google-maps and configure env vars"
```

---

## Task 2: Create TypeScript types

**Files:**
- Create: `react-frontend/src/types/google-places.ts`
- Modify: `react-frontend/src/types/api.ts` — add `latitude`/`longitude` to `RegisterRequest`

**Step 1: Create the PlaceResult type file**

Create `react-frontend/src/types/google-places.ts`:
```ts
/**
 * Google Places Autocomplete types for Project NEXUS.
 *
 * Used by PlaceAutocompleteInput component and all location forms.
 */

/** Structured result returned when user selects a place suggestion. */
export interface PlaceResult {
  /** Google Place ID — stable identifier for this place. */
  placeId: string;
  /** Human-readable formatted address (e.g., "123 Main St, Dublin, Ireland"). */
  formattedAddress: string;
  /** Latitude coordinate. */
  lat: number;
  /** Longitude coordinate. */
  lng: number;
  /** Parsed address components (city, country, etc.). */
  addressComponents?: AddressComponents;
  /** Place or business name (e.g., "Starbucks", "Dublin Castle"). */
  name?: string;
  /** Google Place types (e.g., ['locality', 'political']). */
  types?: string[];
}

/** Parsed address components from Google Places response. */
export interface AddressComponents {
  city?: string;
  county?: string;
  state?: string;
  country?: string;
  countryCode?: string;
  postalCode?: string;
}

/** Props for PlaceAutocompleteInput component. */
export interface PlaceAutocompleteInputProps {
  /** Current text value of the input. */
  value: string;
  /** Called when user types (updates text only, no place selected yet). */
  onChange?: (value: string) => void;
  /** Called when user selects a place from suggestions. */
  onPlaceSelect?: (place: PlaceResult) => void;
  /** Called when user clears the input. */
  onClear?: () => void;
  /** Input label. */
  label?: string;
  /** Input placeholder text. */
  placeholder?: string;
  /** Whether the field is required. */
  isRequired?: boolean;
  /** Error message to display. */
  errorMessage?: string;
  /** Whether the input is invalid (shows error styling). */
  isInvalid?: boolean;
  /** HeroUI classNames pass-through for input styling. */
  classNames?: Record<string, string>;
  /** Additional class for the wrapper div. */
  className?: string;
  /** Whether to show the MapPin icon. Default: true. */
  showIcon?: boolean;
}
```

**Step 2: Add lat/lng to RegisterRequest in `api.ts`**

In `react-frontend/src/types/api.ts`, find `RegisterRequest` (line 144) and add:
```ts
export interface RegisterRequest {
  email: string;
  password: string;
  password_confirmation: string;
  first_name: string;
  last_name: string;
  tenant_id?: number;
  tenant_slug?: string;
  profile_type?: 'individual' | 'organisation';
  organization_name?: string;
  location?: string;
  latitude?: number;
  longitude?: number;
  phone?: string;
  terms_accepted: boolean;
  newsletter_opt_in?: boolean;
}
```

**Step 3: Commit**

```bash
git add react-frontend/src/types/google-places.ts react-frontend/src/types/api.ts
git commit -m "feat(location): add Google Places TypeScript types and lat/lng to RegisterRequest"
```

---

## Task 3: Create GoogleMapsProvider wrapper

**Files:**
- Create: `react-frontend/src/components/location/GoogleMapsProvider.tsx`

**Step 1: Create the provider component**

Create `react-frontend/src/components/location/GoogleMapsProvider.tsx`:
```tsx
/**
 * GoogleMapsProvider — wraps the app with Google Maps API context.
 *
 * Uses @vis.gl/react-google-maps APIProvider to load the Google Maps
 * JavaScript API. The API key comes from VITE_GOOGLE_MAPS_API_KEY.
 *
 * If no API key is configured, renders children without the provider
 * (graceful degradation — PlaceAutocompleteInput falls back to plain text).
 */

import { type ReactNode } from 'react';
import { APIProvider } from '@vis.gl/react-google-maps';

const GOOGLE_MAPS_API_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY || '';

interface GoogleMapsProviderProps {
  children: ReactNode;
}

export function GoogleMapsProvider({ children }: GoogleMapsProviderProps) {
  if (!GOOGLE_MAPS_API_KEY) {
    return <>{children}</>;
  }

  return (
    <APIProvider apiKey={GOOGLE_MAPS_API_KEY}>
      {children}
    </APIProvider>
  );
}
```

**Step 2: Commit**

```bash
git add react-frontend/src/components/location/GoogleMapsProvider.tsx
git commit -m "feat(location): create GoogleMapsProvider with graceful no-key fallback"
```

---

## Task 4: Create PlaceAutocompleteInput component

**Files:**
- Create: `react-frontend/src/components/location/PlaceAutocompleteInput.tsx`

This is the core component. It uses the Google Places Autocomplete Data API (new) via `useMapsLibrary('places')` from `@vis.gl/react-google-maps`, renders suggestions in a HeroUI-styled dropdown, and returns structured `PlaceResult` data on selection.

**Step 1: Create the component**

Create `react-frontend/src/components/location/PlaceAutocompleteInput.tsx`:
```tsx
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
      result.city = c.longText;
    } else if (types.includes('administrative_area_level_1')) {
      result.county = c.longText;
    } else if (types.includes('administrative_area_level_2')) {
      result.state = c.longText;
    } else if (types.includes('country')) {
      result.country = c.longText;
      result.countryCode = c.shortText;
    } else if (types.includes('postal_code')) {
      result.postalCode = c.longText;
    }
  }

  return result;
}

export function PlaceAutocompleteInput({
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
}: PlaceAutocompleteInputProps) {
  const placesLib = useMapsLibrary('places');

  const [suggestions, setSuggestions] = useState<google.maps.places.AutocompleteSuggestion[]>([]);
  const [isOpen, setIsOpen] = useState(false);
  const [activeIndex, setActiveIndex] = useState(-1);
  const [isGoogleReady, setIsGoogleReady] = useState(false);

  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const sessionTokenRef = useRef<google.maps.places.AutocompleteSessionToken | null>(null);
  const wrapperRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  // Track when Google Places library is loaded
  useEffect(() => {
    if (placesLib) {
      setIsGoogleReady(true);
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

      if (!isGoogleReady) return;

      debounceRef.current = setTimeout(() => {
        fetchSuggestions(newValue);
      }, DEBOUNCE_MS);
    },
    [onChange, isGoogleReady, fetchSuggestions]
  );

  // Handle selection of a suggestion
  const handleSelect = useCallback(
    async (suggestion: google.maps.places.AutocompleteSuggestion) => {
      const placePrediction = suggestion.placePrediction;
      if (!placePrediction || !placesLib) return;

      try {
        // Fetch full place details (uses the session token — bundled billing)
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
          name: place.displayName || undefined,
          types: place.types || undefined,
          addressComponents: place.addressComponents
            ? parseAddressComponents(place.addressComponents)
            : undefined,
        };

        // Update the text input
        onChange?.(result.formattedAddress);
        onPlaceSelect?.(result);

        // Close dropdown and reset session token for next search
        setSuggestions([]);
        setIsOpen(false);
        setActiveIndex(-1);
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
                  e.preventDefault(); // Prevent blur before click registers
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

          {/* Google attribution — required by ToS */}
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
```

**Step 2: Commit**

```bash
git add react-frontend/src/components/location/PlaceAutocompleteInput.tsx
git commit -m "feat(location): create PlaceAutocompleteInput with Google Places Autocomplete (New)"
```

---

## Task 5: Create barrel export and wire provider into App.tsx

**Files:**
- Create: `react-frontend/src/components/location/index.ts`
- Modify: `react-frontend/src/App.tsx` — wrap with `GoogleMapsProvider`

**Step 1: Create barrel export**

Create `react-frontend/src/components/location/index.ts`:
```ts
export { GoogleMapsProvider } from './GoogleMapsProvider';
export { PlaceAutocompleteInput } from './PlaceAutocompleteInput';
```

**Step 2: Wire `GoogleMapsProvider` into App.tsx**

In `react-frontend/src/App.tsx`, import and wrap.

Add import at top with other imports:
```tsx
import { GoogleMapsProvider } from '@/components/location';
```

In the `App()` function (around line 510), wrap `<BrowserRouter>` with `<GoogleMapsProvider>`:
```tsx
function App() {
  return (
    <ErrorBoundary>
      <HelmetProvider>
        <ThemeProvider>
          <GoogleMapsProvider>
            <BrowserRouter>
              <HeroUIRouterProvider>
                <ScrollToTop />
                <ToastProvider>
                  <Suspense fallback={<LoadingScreen message="Loading..." />}>
                    <Routes>
                      <Route path="/*" element={<TenantShell appRoutes={AppRoutes} />}>
                        {AppRoutes()}
                      </Route>
                    </Routes>
                  </Suspense>
                </ToastProvider>
              </HeroUIRouterProvider>
            </BrowserRouter>
          </GoogleMapsProvider>
        </ThemeProvider>
      </HelmetProvider>
    </ErrorBoundary>
  );
}
```

**Step 3: Commit**

```bash
git add react-frontend/src/components/location/index.ts react-frontend/src/App.tsx
git commit -m "feat(location): wire GoogleMapsProvider into App.tsx + barrel exports"
```

---

## Task 6: Integrate into RegisterPage

**Files:**
- Modify: `react-frontend/src/pages/auth/RegisterPage.tsx`

**What to change:**

1. Add state for `latitude` and `longitude`:
```tsx
const [latitude, setLatitude] = useState<number | undefined>();
const [longitude, setLongitude] = useState<number | undefined>();
```

2. Replace the plain `<Input>` for location (around line 450-464) with:
```tsx
<PlaceAutocompleteInput
  label="Location"
  placeholder="Your town or city"
  value={location}
  onChange={(val) => setLocation(val)}
  onPlaceSelect={(place) => {
    setLocation(place.formattedAddress);
    setLatitude(place.lat);
    setLongitude(place.lng);
  }}
  onClear={() => {
    setLocation('');
    setLatitude(undefined);
    setLongitude(undefined);
  }}
  classNames={{
    inputWrapper: 'glass-card border-glass-border hover:border-glass-border-hover',
    label: 'text-theme-muted',
    input: 'text-theme-primary placeholder:text-theme-subtle',
  }}
/>
```

3. Add import:
```tsx
import { PlaceAutocompleteInput } from '@/components/location';
```

4. Remove `MapPin` from the lucide-react import (no longer needed for location field — component has its own icon).

5. In `handleSubmit` (line 241-254), add lat/lng to the register payload:
```tsx
const success = await register({
  first_name: firstName,
  last_name: lastName,
  email,
  password,
  password_confirmation: passwordConfirm,
  tenant_id: tenantId,
  profile_type: profileType,
  organization_name: profileType === 'organisation' ? organizationName : undefined,
  location: location || undefined,
  latitude: latitude,
  longitude: longitude,
  phone: phone || undefined,
  terms_accepted: termsAccepted,
  newsletter_opt_in: newsletterOptIn,
});
```

**Step: Commit**

```bash
git add react-frontend/src/pages/auth/RegisterPage.tsx
git commit -m "feat(location): integrate PlaceAutocompleteInput into RegisterPage"
```

---

## Task 7: Integrate into SettingsPage (Profile)

**Files:**
- Modify: `react-frontend/src/pages/settings/SettingsPage.tsx`

**What to change:**

1. Add lat/lng fields to `profileData` state interface and initial state.

2. Replace the plain `<Input>` for location (around line 850-856) with `<PlaceAutocompleteInput>`.

3. Add lat/lng to the `saveProfile` payload (around line 317-329).

4. Add import for `PlaceAutocompleteInput`.

**Step: Commit**

```bash
git add react-frontend/src/pages/settings/SettingsPage.tsx
git commit -m "feat(location): integrate PlaceAutocompleteInput into SettingsPage"
```

---

## Task 8: Integrate into CreateListingPage

**Files:**
- Modify: `react-frontend/src/pages/listings/CreateListingPage.tsx`

**What to change:**

1. Add `latitude` and `longitude` to the FormData interface and `initialFormData`.

2. Replace the plain `<Input>` for location (around line 307-318) with `<PlaceAutocompleteInput>`.

3. In `handleSubmit`, include `latitude`/`longitude` in the payload.

4. In `loadListing()` (editing), populate lat/lng from the loaded listing data.

5. Add import for `PlaceAutocompleteInput`.

**Step: Commit**

```bash
git add react-frontend/src/pages/listings/CreateListingPage.tsx
git commit -m "feat(location): integrate PlaceAutocompleteInput into CreateListingPage"
```

---

## Task 9: Integrate into CreateEventPage

**Files:**
- Modify: `react-frontend/src/pages/events/CreateEventPage.tsx`

**What to change:**

1. Add `latitude` and `longitude` to the FormData interface (line 47-57) and `initialFormData` (line 59-69).

2. Find and replace the location `<Input>` with `<PlaceAutocompleteInput>`.

3. In the submit handler, include lat/lng in the API payload.

4. When loading an existing event for editing, populate lat/lng from event data.

5. Add import for `PlaceAutocompleteInput`.

**Step: Commit**

```bash
git add react-frontend/src/pages/events/CreateEventPage.tsx
git commit -m "feat(location): integrate PlaceAutocompleteInput into CreateEventPage"
```

---

## Task 10: Integrate into CreateGroupPage

**Files:**
- Modify: `react-frontend/src/pages/groups/CreateGroupPage.tsx`

**What to change:**

1. Add `latitude` and `longitude` to the FormData interface (line 34-39) and `initialFormData` (line 41-46).

2. Find and replace the location `<Input>` with `<PlaceAutocompleteInput>`.

3. In the submit handler, include lat/lng in the API payload.

4. When loading an existing group for editing, populate lat/lng from group data.

5. Add import for `PlaceAutocompleteInput`.

**Step: Commit**

```bash
git add react-frontend/src/pages/groups/CreateGroupPage.tsx
git commit -m "feat(location): integrate PlaceAutocompleteInput into CreateGroupPage"
```

---

## Task 11: Remove Mapbox — Backend cleanup

**Files:**
- Modify: `.env.docker` — remove Mapbox vars (already done in Task 1)
- Modify: `docker/docker-compose.yml` — remove `MAPBOX_ACCESS_TOKEN` env var (line 40)
- Modify: `src/Core/Validator.php` — remove `validateIrishLocation()` method (lines 31-67)
- Modify: `src/Controllers/AuthController.php` — remove Mapbox location validation call (lines 264-268)
- Modify: `docker/nginx/default.conf` — remove `api.mapbox.com` from CSP header (line 17)

**Details for each file:**

**docker/docker-compose.yml:** Remove line:
```yaml
      - MAPBOX_ACCESS_TOKEN=${MAPBOX_ACCESS_TOKEN:-}
```

**src/Core/Validator.php:** Remove the entire `validateIrishLocation` method (lines 31-67). Keep `isIrishPhone` method.

**src/Controllers/AuthController.php:** Remove lines 264-268:
```php
            // Location Validation (Mapbox)
            if ($location) {
                $locError = \Nexus\Core\Validator::validateIrishLocation($location);
                if ($locError) $errors[] = $locError;
            }
```

**docker/nginx/default.conf:** In the CSP header (line 17), remove `https://api.mapbox.com` from `script-src`, `style-src`, and `connect-src` directives. Add Google Maps domains instead:
- `script-src`: add `https://maps.googleapis.com`
- `connect-src`: add `https://maps.googleapis.com https://places.googleapis.com`

**Step: Commit**

```bash
git add docker/docker-compose.yml src/Core/Validator.php src/Controllers/AuthController.php docker/nginx/default.conf
git commit -m "refactor(location): remove all Mapbox integration and update CSP for Google Maps"
```

---

## Task 12: Remove Mapbox — Legacy PHP admin cleanup

**Files:**
- Modify: `views/modern/admin/settings.php` — remove Mapbox token input section
- Modify: `views/modern/admin/super-admin/tenant-edit.php` — remove Mapbox JS autocomplete (lines 228-247 form fields, lines 458-546 JavaScript)
- Modify: `views/modern/admin/users/create.php` — remove `mapbox-location-input-v2` class and hidden lat/lng fields
- Modify: `views/modern/admin/users/edit.php` — remove `mapbox-location-input-v2` class and hidden lat/lng fields

**For settings.php:** Find the Mapbox Access Token label/input block (around line 328-330) and remove it entirely.

**For tenant-edit.php:** Find and remove:
- The Mapbox location search input and hidden fields (lines 228-247)
- The entire `MAPBOX_TOKEN` JavaScript block (lines 458-546) that implements client-side autocomplete

**For users/create.php and users/edit.php:** Change `class="admin-input mapbox-location-input-v2"` to just `class="admin-input"` and remove associated hidden lat/lng fields if they exist.

**Step: Commit**

```bash
git add views/modern/admin/settings.php views/modern/admin/super-admin/tenant-edit.php views/modern/admin/users/create.php views/modern/admin/users/edit.php
git commit -m "refactor(admin): remove Mapbox references from legacy PHP admin views"
```

---

## Task 13: Simplify GeocodingService — Google-only

**Files:**
- Modify: `src/Services/GeocodingService.php`

**What to change:**

1. Remove `geocodeWithNominatim()` method entirely (lines 104-150).
2. Remove the rate limiting logic for Nominatim (`$lastRequestTime` property, sleep logic).
3. Make `geocodeWithGoogle()` the primary (and only) provider.
4. Update `geocode()` to call Google directly (no Nominatim attempt, no fallback chain).
5. Update the class docblock to say "Google Maps Geocoding API" only.

The updated `geocode()` method:
```php
public static function geocode(string $address): ?array
{
    if (empty(trim($address))) {
        return null;
    }

    $address = self::sanitizeAddress($address);
    if ($address === null) {
        return null;
    }

    $cached = self::getCached($address);
    if ($cached !== null) {
        return $cached;
    }

    $result = self::geocodeWithGoogle($address);

    if ($result !== null) {
        self::cacheResult($address, $result);
    }

    return $result;
}
```

**Step: Commit**

```bash
git add src/Services/GeocodingService.php
git commit -m "refactor(geocoding): remove Nominatim, simplify to Google-only provider"
```

---

## Task 14: Add Google Maps API key to docker-compose.yml

**Files:**
- Modify: `docker/docker-compose.yml` — add `GOOGLE_MAPS_API_KEY` env var to the app service

**Step 1:** In the app service `environment` section, add:
```yaml
      - GOOGLE_MAPS_API_KEY=${GOOGLE_MAPS_API_KEY:-}
```

**Step 2: Commit**

```bash
git add docker/docker-compose.yml
git commit -m "chore(docker): add GOOGLE_MAPS_API_KEY to app container environment"
```

---

## Task 15: Build verification

**Step 1: TypeScript check**
```bash
cd react-frontend && npm run lint
```
Expected: No errors.

**Step 2: Build check**
```bash
cd react-frontend && npm run build
```
Expected: Build succeeds. Bundle size should increase by ~11KB (gzipped) for the Google Maps wrapper.

**Step 3: Verify no Mapbox references remain**
```bash
grep -ri "mapbox" --include="*.php" --include="*.tsx" --include="*.ts" --include="*.yml" --include="*.conf" --include="*.env*" . | grep -v node_modules | grep -v ".git/" | grep -v "docs/plans"
```
Expected: No results (except maybe old commit messages or docs).

**Step 4: Final commit if any lint fixes needed**

```bash
git add -A && git commit -m "chore: build verification and cleanup"
```

---

## Summary

| Task | Description | Files |
|------|-------------|-------|
| 1 | Install `@vis.gl/react-google-maps` + env vars | package.json, .env files |
| 2 | TypeScript types (PlaceResult, RegisterRequest) | types/google-places.ts, types/api.ts |
| 3 | GoogleMapsProvider wrapper | components/location/GoogleMapsProvider.tsx |
| 4 | PlaceAutocompleteInput component (core) | components/location/PlaceAutocompleteInput.tsx |
| 5 | Barrel export + wire into App.tsx | components/location/index.ts, App.tsx |
| 6 | Integrate: RegisterPage | pages/auth/RegisterPage.tsx |
| 7 | Integrate: SettingsPage | pages/settings/SettingsPage.tsx |
| 8 | Integrate: CreateListingPage | pages/listings/CreateListingPage.tsx |
| 9 | Integrate: CreateEventPage | pages/events/CreateEventPage.tsx |
| 10 | Integrate: CreateGroupPage | pages/groups/CreateGroupPage.tsx |
| 11 | Remove Mapbox: backend + nginx | docker-compose, Validator, AuthController, nginx |
| 12 | Remove Mapbox: legacy PHP admin | settings.php, tenant-edit.php, user forms |
| 13 | Simplify GeocodingService: Google-only | GeocodingService.php |
| 14 | Add Google API key to Docker | docker-compose.yml |
| 15 | Build verification + cleanup | (verification) |
