// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { router, type Href } from 'expo-router';
import * as Sentry from '@sentry/react-native';

const knownSections = new Set([
  'exchanges',
  'listings',
  'events',
  'members',
  'messages',
  'blog',
  'blog-post',
  'groups',
  'jobs',
  'job',
  'organisations',
  'organizations',
  'organisation',
  'organization',
  'volunteering',
  'goals',
  'endorsements',
  'gamification',
  'federation',
  'wallet',
  'notifications',
  'chat',
  'search',
]);

/**
 * Maps a web-format deep-link (e.g. /exchanges/123) to the appropriate
 * mobile screen and navigates to it.
 */
export function navigateToLink(link: string | null): void {
  if (!link) return;
  const parsed = parseLink(link);
  if (!parsed) return;
  const { section, segments, params } = parsed;
  const [id] = segments;
  switch (section) {
    case 'exchanges':
    case 'listings':
      if (id) router.push({ pathname: '/(modals)/exchange-detail', params: { id } });
      else router.replace('/(tabs)/exchanges');
      break;
    case 'events':
      if (id) router.push({ pathname: '/(modals)/event-detail', params: { id } });
      else router.replace('/(tabs)/events');
      break;
    case 'members':
      if (id) router.push({ pathname: '/(modals)/member-profile', params: { id } });
      else router.push('/(modals)/members');
      break;
    case 'messages':
      if (id) router.push({ pathname: '/(modals)/thread', params: { id } });
      else router.push('/(tabs)/messages');
      break;
    case 'blog':
    case 'blog-post':
      if (id) router.push({ pathname: '/(modals)/blog-post', params: { id } });
      else router.push('/(modals)/blog');
      break;
    case 'groups':
      if (id) router.push({ pathname: '/(modals)/group-detail', params: { id } });
      else router.push('/(modals)/groups');
      break;
    case 'jobs':
      if (id) router.push({ pathname: '/(modals)/job-detail', params: { id } });
      else router.push('/(modals)/jobs');
      break;
    case 'job':
      if (id) router.push({ pathname: '/(modals)/job-detail', params: { id } });
      else router.push('/(modals)/jobs');
      break;
    case 'organisations':
    case 'organizations':
      if (id) router.push({ pathname: '/(modals)/organisation-detail', params: { id } });
      else router.push('/(modals)/organisations');
      break;
    case 'organisation':
    case 'organization':
      if (id) router.push({ pathname: '/(modals)/organisation-detail', params: { id } });
      else router.push('/(modals)/organisations');
      break;
    case 'volunteering':
      if (id) router.push({ pathname: '/(modals)/volunteering-detail', params: { id } });
      else router.push('/(modals)/volunteering');
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
      navigateFederation(segments, params);
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
    case 'search':
      router.push('/(modals)/search');
      break;
    default:
      Sentry.captureMessage(`[DeepLink] Unhandled link: ${link}`, 'warning');
      break;
  }
}

function parseLink(link: string): { section: string; segments: string[]; params: Record<string, string> } | null {
  let url: URL;
  try {
    url = new URL(link, 'https://app.project-nexus.ie');
  } catch {
    return null;
  }

  const pathSegments = url.pathname.split('/').filter(Boolean).map(decodeURIComponent);
  if (pathSegments.length === 0) return null;

  let [section, ...segments] = pathSegments;
  if (!knownSections.has(section) && pathSegments[1] && knownSections.has(pathSegments[1])) {
    section = pathSegments[1];
    segments = pathSegments.slice(2);
  }

  return {
    section,
    segments,
    params: Object.fromEntries(url.searchParams.entries()),
  };
}

function navigateFederation(segments: string[], queryParams: Record<string, string>): void {
  const [branch, detailId] = segments;
  if (!branch) {
    router.push('/(modals)/federation');
    return;
  }

  switch (branch) {
    case 'partners':
      if (detailId) {
        router.push({ pathname: '/(modals)/federation-partner', params: { id: detailId } });
      } else {
        pushWithOptionalParams('/(modals)/federation-partners', queryParams);
      }
      break;
    case 'members':
      if (detailId) {
        router.push({ pathname: '/(modals)/member-profile', params: { id: detailId, ...queryParams } });
      } else {
        pushWithOptionalParams('/(modals)/federation-members', queryParams);
      }
      break;
    case 'messages':
      pushWithOptionalParams('/(modals)/federation-messages', queryParams);
      break;
    case 'listings':
      pushWithOptionalParams('/(modals)/federation-listings', queryParams);
      break;
    case 'groups':
      pushWithOptionalParams('/(modals)/federation-groups', queryParams);
      break;
    case 'events':
      pushWithOptionalParams('/(modals)/federation-events', queryParams);
      break;
    case 'settings':
      pushWithOptionalParams('/(modals)/federation-settings', queryParams);
      break;
    case 'onboarding':
      pushWithOptionalParams('/(modals)/federation-onboarding', queryParams);
      break;
    case 'connections':
      pushWithOptionalParams('/(modals)/federation-connections', queryParams);
      break;
    default:
      router.push({ pathname: '/(modals)/federation-partner', params: { id: branch, ...queryParams } });
      break;
  }
}

function pushWithOptionalParams(pathname: string, params: Record<string, string>): void {
  if (Object.keys(params).length > 0) {
    router.push({ pathname, params } as unknown as Href);
  } else {
    router.push(pathname as Href);
  }
}
