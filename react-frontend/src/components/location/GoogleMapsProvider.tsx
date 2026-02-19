// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GoogleMapsProvider — wraps the app with Google Maps API context.
 *
 * Uses @vis.gl/react-google-maps APIProvider to load the Google Maps
 * JavaScript API. The API key comes from VITE_GOOGLE_MAPS_API_KEY.
 *
 * If no API key is configured, renders children without the provider
 * (graceful degradation — PlaceAutocompleteInput falls back to plain text).
 *
 * If the API key fails auth (billing not enabled, restricted, etc.),
 * the error dialog is suppressed and map components gracefully return null.
 */

import { type ReactNode, useEffect } from 'react';
import { APIProvider } from '@vis.gl/react-google-maps';

const GOOGLE_MAPS_API_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY || '';

interface GoogleMapsProviderProps {
  children: ReactNode;
}

export function GoogleMapsProvider({ children }: GoogleMapsProviderProps) {
  // Suppress the Google Maps "This page can't load Google Maps correctly" dialog.
  // When auth fails (billing, key restrictions), Google calls window.gm_authFailure.
  // By defining it, we prevent the default alert dialog from appearing.
  // Individual map components detect AUTH_FAILURE via useApiLoadingStatus() and return null.
  useEffect(() => {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (window as any).gm_authFailure = () => {
      if (import.meta.env.DEV) {
        console.warn(
          '[GoogleMaps] Auth failure — check billing is enabled and API key restrictions in Google Cloud Console.'
        );
      }
    };
  }, []);

  if (!GOOGLE_MAPS_API_KEY) {
    return <>{children}</>;
  }

  return (
    <APIProvider apiKey={GOOGLE_MAPS_API_KEY}>
      {children}
    </APIProvider>
  );
}
