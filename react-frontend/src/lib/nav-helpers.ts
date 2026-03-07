// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { tokenManager, API_BASE } from '@/lib/api';

/**
 * Navigate to the legacy PHP admin panel by creating a hidden form POST.
 * This bridges the JWT token from the React SPA into a PHP session cookie.
 */
export function navigateToLegacyAdmin(): void {
  const token = tokenManager.getAccessToken();
  const phpOrigin = API_BASE.startsWith('http')
    ? new URL(API_BASE).origin
    : window.location.origin;

  if (token) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `${phpOrigin}/api/auth/admin-session`;
    form.style.display = 'none';

    const tokenInput = document.createElement('input');
    tokenInput.name = 'token';
    tokenInput.value = token;
    form.appendChild(tokenInput);

    const redirectInput = document.createElement('input');
    redirectInput.name = 'redirect';
    redirectInput.value = '/admin-legacy';
    form.appendChild(redirectInput);

    document.body.appendChild(form);
    form.submit();
  } else {
    window.location.href = `${phpOrigin}/admin-legacy`;
  }
}
