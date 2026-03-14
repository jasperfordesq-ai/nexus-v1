// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { router } from 'expo-router';

/**
 * Maps a web-format deep-link (e.g. /exchanges/123) to the appropriate
 * mobile screen and navigates to it.
 */
export function navigateToLink(link: string | null): void {
  if (!link) return;
  const match = link.match(/^\/([^/]+)(?:\/(\d+))?/);
  if (!match) return;
  const [, section, id] = match;
  if (id && isNaN(Number(id))) return;
  switch (section) {
    case 'exchanges':
      if (id) router.push({ pathname: '/(modals)/exchange-detail', params: { id } });
      break;
    case 'events':
      if (id) router.push({ pathname: '/(modals)/event-detail', params: { id } });
      break;
    case 'members':
      if (id) router.push({ pathname: '/(modals)/member-profile', params: { id } });
      break;
    case 'messages':
      if (id) router.push({ pathname: '/(modals)/thread', params: { id } });
      else router.push('/(tabs)/messages');
      break;
    default:
      console.warn('[DeepLink] Unhandled link:', link);
      break;
  }
}
