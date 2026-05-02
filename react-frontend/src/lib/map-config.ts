// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Whether Google Maps UI entry points should be shown.
 *
 * The browser key is fetched at runtime by GoogleMapsProvider so it is not
 * baked into static frontend assets. Use VITE_GOOGLE_MAPS_ENABLED=0 only for
 * builds that should hide map affordances entirely.
 */
export const MAPS_ENABLED = import.meta.env.VITE_GOOGLE_MAPS_ENABLED !== '0';
