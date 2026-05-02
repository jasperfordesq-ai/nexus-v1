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

  it('keeps map affordances enabled unless explicitly disabled at build time', () => {
    expect(MAPS_ENABLED).toBe(import.meta.env.VITE_GOOGLE_MAPS_ENABLED !== '0');
  });
});
