// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PlaceAutocompleteInput — provider-dispatched location input.
 *
 * The cost-bearing Google branch is in GooglePlaceAutocomplete.tsx and is
 * lazy-loaded only after a Google-backed field is focused or edited.
 */

import { lazy, Suspense, useState } from 'react';
import MapPin from 'lucide-react/icons/map-pin';
import X from 'lucide-react/icons/x';
import { useTranslation } from 'react-i18next';
import type { PlaceAutocompleteInputProps } from '@/types/google-places';
import { NominatimAutocomplete } from './NominatimAutocomplete';
import { OsPlacesAutocomplete } from './OsPlacesAutocomplete';
import { useTenant } from '@/contexts/TenantContext';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';

const LazyGooglePlaceAutocomplete = lazy(() =>
  import('./GooglePlaceAutocomplete').then((module) => ({
    default: module.GooglePlaceAutocomplete,
  })),
);

/**
 * PlaceAutocompleteInput — the public API.
 *
 * Dispatches on the tenant's `geocodingProvider` setting:
 *   - 'google'    → Google Places Autocomplete, loaded on focus/edit only.
 *   - 'nominatim' → OpenStreetMap Nominatim.
 *   - 'os_places' → Ordnance Survey Places API via the platform proxy.
 */
export function PlaceAutocompleteInput(props: PlaceAutocompleteInputProps) {
  const { geocodingProvider } = useTenant();

  if (geocodingProvider === 'nominatim') {
    return <NominatimAutocomplete {...props} />;
  }

  if (geocodingProvider === 'os_places') {
    return <OsPlacesAutocomplete {...props} />;
  }

  return <DeferredGooglePlaceAutocomplete {...props} />;
}

function DeferredGooglePlaceAutocomplete(props: PlaceAutocompleteInputProps) {
  const [isActive, setIsActive] = useState(false);
  const fallback = (
    <PlaceAutocompleteFallback
      {...props}
      onActivate={() => setIsActive(true)}
    />
  );

  if (!isActive) {
    return fallback;
  }

  return (
    <Suspense fallback={fallback}>
      <LazyGooglePlaceAutocomplete {...props} fallback={fallback} />
    </Suspense>
  );
}

/**
 * Plain text fallback while Google Maps is inactive or unavailable.
 */
function PlaceAutocompleteFallback(props: PlaceAutocompleteInputProps & { onActivate?: () => void }) {
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
    onActivate,
  } = props;

  const { t } = useTranslation('common');

  return (
    <div className={`relative ${className || ''}`}>
      <Input
        type="text"
        label={label}
        placeholder={placeholder}
        value={value}
        onFocus={onActivate}
        onChange={(e) => {
          onActivate?.();
          onChange?.(e.target.value);
        }}
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
            <Button
              type="button"
              variant="light"
              isIconOnly
              onPress={() => {
                onChange?.('');
                onClear?.();
              }}
              className="p-0.5 rounded-full hover:bg-surface-tertiary transition-colors min-w-0 min-h-9 w-auto"
              aria-label={t('aria.clear_location')}
            >
              <X className="w-3.5 h-3.5 text-theme-subtle" />
            </Button>
          ) : undefined
        }
        classNames={classNames}
      />
    </div>
  );
}
