// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for map-config
 */

import { describe, it, expect } from 'vitest';
import { MAPS_ENABLED } from './map-config';

describe('map-config', () => {
  it('exports MAPS_ENABLED as a boolean', () => {
    expect(typeof MAPS_ENABLED).toBe('boolean');
  });

  it('MAPS_ENABLED reflects whether VITE_GOOGLE_MAPS_API_KEY is set', () => {
    // MAPS_ENABLED is derived from !!import.meta.env.VITE_GOOGLE_MAPS_API_KEY
    // It should be a boolean regardless of env configuration
    if (import.meta.env.VITE_GOOGLE_MAPS_API_KEY) {
      expect(MAPS_ENABLED).toBe(true);
    } else {
      expect(MAPS_ENABLED).toBe(false);
    }
  });
});
