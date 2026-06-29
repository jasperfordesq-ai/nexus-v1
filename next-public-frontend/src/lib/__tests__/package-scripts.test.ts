// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import packageJson from '../../../package.json';

describe('Next public frontend package scripts', () => {
  it('keeps manifest validation in the default isolated check command', () => {
    expect(packageJson.scripts['check:manifests']).toBe('vitest run src/lib/__tests__/shadow-manifest-validation.test.ts');
    expect(packageJson.scripts.check).toContain('npm run check:manifests');
  });
});
