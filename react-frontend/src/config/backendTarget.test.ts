// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import { normalizeBackendTarget } from './backendTarget';

describe('normalizeBackendTarget', () => {
  it('defaults to laravel when the value is missing', () => {
    expect(normalizeBackendTarget(undefined)).toBe('laravel');
  });

  it('defaults to laravel when the value is unknown', () => {
    expect(normalizeBackendTarget('rails')).toBe('laravel');
  });

  it('accepts laravel', () => {
    expect(normalizeBackendTarget('laravel')).toBe('laravel');
  });

  it('accepts dotnet', () => {
    expect(normalizeBackendTarget('dotnet')).toBe('dotnet');
  });
});
