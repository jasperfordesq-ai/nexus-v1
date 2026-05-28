// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import { motion } from './index';

describe('motion proxy', () => {
  it('does not create motion components for symbol metadata probes', () => {
    expect(() => Object.prototype.toString.call(motion)).not.toThrow();
  });
});
