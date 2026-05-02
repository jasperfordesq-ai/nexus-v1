// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GoogleMapsProvider - wraps Maps surfaces with Google Maps API context.
 *
 * Fetches the browser API key at runtime so production builds do not bake the
 * key into public HTML or static JS assets. The provider is mounted only by
 * map/autocomplete components, so ordinary page views do not load Google Maps.
 */

import { createContext, type ReactNode, useContext, useEffect, useState } from 'react';
import { APIProvider } from '@vis.gl/react-google-maps';

declare global {
  interface Window {
    gm_authFailure?: () => void;
  }
}

const MAPS_CONFIG_URL = `${import.meta.env.VITE_API_BASE || '/api'}/v2/config/google-maps`;

export interface GoogleMapsConfig {
  enabled: boolean;
  apiKey: string;
  mapId: string | null;
}

const GoogleMapsConfigContext = createContext<GoogleMapsConfig | null>(null);

let configPromise: Promise<GoogleMapsConfig> | null = null;

export function resetGoogleMapsConfigForTests() {
  if (import.meta.env.MODE === 'test') {
    configPromise = null;
  }
}

async function fetchGoogleMapsConfig(): Promise<GoogleMapsConfig> {
  if (!configPromise) {
    configPromise = fetch(MAPS_CONFIG_URL, {
      headers: { Accept: 'application/json' },
      credentials: 'include',
    })
      .then(async (response) => {
        if (!response.ok) {
          throw new Error(`Google Maps config failed: ${response.status}`);
        }

        const payload = await response.json();
        const data = payload?.data ?? {};

        return {
          enabled: Boolean(data.enabled && data.apiKey),
          apiKey: typeof data.apiKey === 'string' ? data.apiKey : '',
          mapId: typeof data.mapId === 'string' && data.mapId !== '' ? data.mapId : null,
        };
      })
      .catch((error) => {
        if (import.meta.env.DEV) {
          console.warn('[GoogleMaps] Config fetch failed.', error);
        }

        return { enabled: false, apiKey: '', mapId: null };
      });
  }

  return configPromise;
}

interface GoogleMapsProviderProps {
  children: ReactNode;
  fallback?: ReactNode;
}

export function useGoogleMapsConfig() {
  return useContext(GoogleMapsConfigContext);
}

export function GoogleMapsProvider({ children, fallback = null }: GoogleMapsProviderProps) {
  const [config, setConfig] = useState<GoogleMapsConfig | null>(null);

  useEffect(() => {
    window.gm_authFailure = () => {
      if (import.meta.env.DEV) {
        console.warn(
          '[GoogleMaps] Auth failure - check billing is enabled and API key restrictions in Google Cloud Console.'
        );
      }
    };
  }, []);

  useEffect(() => {
    let isMounted = true;

    fetchGoogleMapsConfig().then((nextConfig) => {
      if (isMounted) setConfig(nextConfig);
    });

    return () => {
      isMounted = false;
    };
  }, []);

  if (!config || !config.enabled || !config.apiKey) {
    return <>{fallback}</>;
  }

  return (
    <APIProvider apiKey={config.apiKey}>
      <GoogleMapsConfigContext.Provider value={config}>
        {children}
      </GoogleMapsConfigContext.Provider>
    </APIProvider>
  );
}
