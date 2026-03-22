// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { router } from 'expo-router';
import * as Sentry from '@sentry/react-native';

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
    case 'blog':
    case 'blog-post':
      if (id) router.push({ pathname: '/(modals)/blog-post', params: { id } });
      break;
    case 'groups':
      if (id) router.push({ pathname: '/(modals)/group-detail', params: { id } });
      break;
    case 'organisations':
      if (id) router.push({ pathname: '/(modals)/organisation-detail', params: { id } });
      break;
    case 'volunteering':
      if (id) router.push({ pathname: '/(modals)/volunteering-detail', params: { id } });
      break;
    case 'goals':
      router.push('/(modals)/goals');
      break;
    case 'endorsements':
      router.push('/(modals)/endorsements');
      break;
    case 'gamification':
      router.push('/(modals)/gamification');
      break;
    case 'federation':
      if (id) router.push({ pathname: '/(modals)/federation-partner', params: { id } });
      else router.push('/(modals)/federation');
      break;
    case 'wallet':
      router.push('/(modals)/wallet');
      break;
    case 'notifications':
      router.push('/(modals)/notifications');
      break;
    case 'chat':
      router.push('/(modals)/chat');
      break;
    default:
      Sentry.captureMessage(`[DeepLink] Unhandled link: ${link}`, 'warning');
      break;
  }
}
