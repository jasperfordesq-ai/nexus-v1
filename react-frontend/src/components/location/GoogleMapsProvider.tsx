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
