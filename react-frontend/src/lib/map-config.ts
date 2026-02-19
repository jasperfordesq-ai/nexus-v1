// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/** Whether Google Maps features are available (API key configured). */
export const MAPS_ENABLED = !!import.meta.env.VITE_GOOGLE_MAPS_API_KEY;
