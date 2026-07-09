// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

export type BackendTarget = 'laravel' | 'dotnet';

export function normalizeBackendTarget(value: string | undefined): BackendTarget {
  return value === 'dotnet' || value === 'laravel' ? value : 'laravel';
}

export const backendTarget = normalizeBackendTarget(import.meta.env.VITE_BACKEND_TARGET);

export const isLaravelBackend = backendTarget === 'laravel';
export const isDotnetBackend = backendTarget === 'dotnet';
