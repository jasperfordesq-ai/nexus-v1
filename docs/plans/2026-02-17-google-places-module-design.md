# Google Places Autocomplete Module — Design Document

**Date:** 2026-02-17
**Status:** Approved
**Replaces:** Mapbox integration (removed entirely)

## Overview

Enterprise-grade Google Places Autocomplete module for Project NEXUS. Replaces all plain text location inputs with Google Places-powered autocomplete, providing world-class address/place suggestions with coordinates. Uses Google's official React library (`@vis.gl/react-google-maps`).

## Architecture

```
┌─────────────────────────────────────────────────────┐
│  React Frontend                                      │
│  ┌───────────────────────────────────────────────┐  │
│  │  GoogleMapsProvider (wraps app in App.tsx)     │  │
│  │  - <APIProvider> from @vis.gl/react-google-maps│  │
│  │  - API key from VITE_GOOGLE_MAPS_API_KEY      │  │
│  │  - Loads 'places' library on demand            │  │
│  └───────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────┐  │
│  │  PlaceAutocompleteInput (reusable component)  │  │
│  │  - HeroUI Input styling pass-through          │  │
│  │  - Google Places suggestions dropdown         │  │
│  │  - Returns: address, lat, lng, components     │  │
│  │  - Graceful fallback to plain text on failure  │  │
│  └───────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────┤
│  PHP Backend                                         │
│  ┌───────────────────────────────────────────────┐  │
│  │  API endpoints accept lat/lng FROM frontend   │  │
│  │  - Skip server-side geocoding when provided   │  │
│  │  - Store place_id for future lookups          │  │
│  └───────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────┐  │
│  │  GeocodingService (simplified, Google-only)   │  │
│  │  - Remove Nominatim provider                  │  │
│  │  - Remove Mapbox references                   │  │
│  │  - Keep for batch/cron geocoding only         │  │
│  └───────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────┘
```

## Component Design

### PlaceAutocompleteInput

Drop-in replacement for HeroUI `<Input>` wherever location is needed.

```tsx
<PlaceAutocompleteInput
  label="Location"
  placeholder="Start typing an address..."
  value={location}
  onPlaceSelect={(place) => {
    setLocation(place.formattedAddress);
    setLatitude(place.lat);
    setLongitude(place.lng);
  }}
  classNames={{
    inputWrapper: 'bg-theme-elevated border-theme-default',
  }}
/>
```

### PlaceResult Type

```ts
interface PlaceResult {
  placeId: string;
  formattedAddress: string;
  lat: number;
  lng: number;
  addressComponents?: {
    city?: string;
    county?: string;
    country?: string;
    countryCode?: string;
    postalCode?: string;
  };
  name?: string;
  types?: string[];
}
```

### Behavior

- User types -> 300ms debounce -> Google Places suggestions appear
- Suggestions render in styled dropdown (glass-card aesthetic, dark mode support)
- User selects -> onPlaceSelect fires with full PlaceResult
- User can clear selection and type freely (graceful degradation)
- Google Maps fails to load -> falls back to plain text input (no crash)
- Full keyboard navigation (arrow keys, Enter, Escape)
- Session tokens handled automatically by new Places API (billing optimization)

## Integration Points (6 forms)

| Form | File | Change |
|------|------|--------|
| Registration (Step 2) | `RegisterPage.tsx` | Replace `<Input>` with `<PlaceAutocompleteInput>`, send lat/lng in registration payload |
| Profile Settings | `SettingsPage.tsx` | Replace `<Input>` with `<PlaceAutocompleteInput>`, send lat/lng on profile save |
| Create/Edit Listing | `CreateListingPage.tsx` | Replace `<Input>` with `<PlaceAutocompleteInput>`, send lat/lng |
| Create/Edit Event | `CreateEventPage.tsx` | Replace `<Input>` with `<PlaceAutocompleteInput>`, send lat/lng |
| Create/Edit Group | `CreateGroupPage.tsx` | Replace `<Input>` with `<PlaceAutocompleteInput>`, send lat/lng |
| Onboarding Wizard | `OnboardingPage.tsx` | Replace `<Input>` with `<PlaceAutocompleteInput>`, send lat/lng |

## Backend Changes

### GeocodingService.php
- Remove Nominatim provider (geocodeWithNominatim method)
- Make Google the sole provider
- Keep for batch/cron backfill of records missing coordinates
- No longer called on individual form submissions (frontend provides coords)

### Validator.php
- Remove `validateIrishLocation()` method entirely (Mapbox dependency)
- Google Places inherently returns valid, real locations

### API Endpoints
- Accept optional `latitude` + `longitude` params alongside `location` text
- When lat/lng provided by frontend, store directly — skip geocoding
- When lat/lng NOT provided (API-only submissions), fall back to GeocodingService
- Affected: UsersApiController, ListingsApiController, EventsApiController, GroupsApiController

### Environment
- Add `GOOGLE_MAPS_API_KEY` to `.env.docker` (backend — already partially exists)
- Add `VITE_GOOGLE_MAPS_API_KEY` to React `.env` files (frontend — new)
- Both use the SAME key (Google Maps JS API key with Places enabled)

## Mapbox Removal Scope

| File | Action |
|------|--------|
| `.env.docker` | Remove `MAPBOX_ACCESS_TOKEN` |
| `docker/docker-compose.yml` | Remove Mapbox env var |
| `src/Core/Validator.php` | Remove `validateIrishLocation()` |
| `views/modern/admin/settings.php` | Remove Mapbox token input section |
| `views/modern/admin/super-admin/tenant-edit.php` | Remove Mapbox JS autocomplete code |
| `views/modern/admin/users/create.php` | Remove `mapbox-location-input-v2` class + hidden lat/lng fields |
| `views/modern/admin/users/edit.php` | Remove `mapbox-location-input-v2` class + hidden lat/lng fields |
| `docker/nginx/default.conf` | Remove Mapbox domain from CSP if present |

## New Files

```
react-frontend/src/
├── components/
│   └── location/
│       ├── PlaceAutocompleteInput.tsx    # Main reusable component
│       ├── GoogleMapsProvider.tsx         # APIProvider wrapper
│       └── index.ts                      # Barrel export
├── types/
│   └── google-places.ts                  # PlaceResult + related types
```

## Dependencies

- `@vis.gl/react-google-maps` — Google's official React library (~11KB gzipped)
- Google Maps JavaScript API loaded from CDN at runtime (~200KB gzipped, cached by browser)
- Places library loaded on-demand (~30-50KB gzipped, only when autocomplete used)

## Cost Considerations

- Google Places Autocomplete (New) uses session-based pricing
- Sessions bundle multiple keystrokes into one billable request
- Place Details fetched on selection (included in session cost)
- $200/month free tier from Google Cloud
- Monitor usage via Google Cloud Console billing dashboard
