// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

type RedirectEvent = {
  path: string | null;
  initial: boolean;
};

const KNOWN_SECTIONS = new Set([
  'exchanges',
  'listings',
  'events',
  'members',
  'profile',
  'users',
  'me',
  'messages',
  'groups',
  'polls',
  'ideation',
  'challenges',
]);

export function redirectSystemPath({ path }: RedirectEvent): string {
  try {
    return mapSystemPathToNativeRoute(path) ?? path ?? '/';
  } catch {
    return path ?? '/';
  }
}

export function mapSystemPathToNativeRoute(rawPath: string | null): string | null {
  const parsed = parseSystemPath(rawPath);
  if (!parsed) return null;

  const { section, segments, params } = parsed;
  const [id, detail] = segments;

  switch (section) {
    case 'exchanges':
    case 'listings':
      if (isCreateAlias(id)) return appendParams('/(modals)/new-exchange', params);
      return id ? appendParams('/(modals)/exchange-detail', { ...params, id }) : '/(tabs)/exchanges';

    case 'events':
      if (isCreateAlias(id)) return appendParams('/(modals)/new-event', params);
      return id ? appendParams('/(modals)/event-detail', { ...params, id }) : '/(tabs)/events';

    case 'groups':
      if (isCreateAlias(id)) return appendParams('/(modals)/new-group', params);
      return id ? appendParams('/(modals)/group-detail', { ...params, id }) : '/(modals)/groups';

    case 'members':
    case 'profile':
      return id ? appendParams('/(modals)/member-profile', { ...params, id }) : '/(modals)/members';

    case 'users':
      if (id && detail === 'appreciations') return appendParams('/(modals)/appreciations', { ...params, userId: id });
      if (id && detail === 'collections') return appendParams('/(modals)/profile-collections', { ...params, userId: id, scope: 'public' });
      return id ? appendParams('/(modals)/member-profile', { ...params, id }) : '/(modals)/members';

    case 'me':
      if (id === 'collections') {
        return appendParams('/(modals)/profile-collections', segments[1] ? { ...params, collectionId: segments[1] } : params);
      }
      return null;

    case 'messages':
      return mapMessagePath(segments, params);

    case 'polls':
      return appendParams('/(modals)/polls', isCreateAlias(id) ? { ...params, create: '1' } : params);

    case 'ideation':
    case 'challenges':
      if (isCreateAlias(id)) return appendParams('/(modals)/new-challenge', params);
      return id ? appendParams('/(modals)/ideation-detail', { ...params, id }) : '/(modals)/ideation';

    default:
      return null;
  }
}

function parseSystemPath(rawPath: string | null): { section: string; segments: string[]; params: Record<string, string> } | null {
  const trimmed = rawPath?.trim();
  if (!trimmed) return null;
  const normalized = trimmed.includes('://') || trimmed.startsWith('/') ? trimmed : `/${trimmed}`;
  const url = new URL(normalized, 'https://app.project-nexus.ie');

  let pathSegments = url.pathname.split('/').filter(Boolean).map(decodeURIComponent);
  if (url.protocol === 'nexus:' && url.host && KNOWN_SECTIONS.has(url.host)) {
    pathSegments = [url.host, ...pathSegments];
  }
  if (pathSegments.length === 0) return null;

  let [section, ...segments] = pathSegments;
  if (!KNOWN_SECTIONS.has(section) && pathSegments[1] && KNOWN_SECTIONS.has(pathSegments[1])) {
    section = pathSegments[1];
    segments = pathSegments.slice(2);
  }
  if (!KNOWN_SECTIONS.has(section)) return null;

  return {
    section,
    segments,
    params: Object.fromEntries(url.searchParams.entries()),
  };
}

function mapMessagePath(segments: string[], queryParams: Record<string, string>): string {
  const [branch, detailId] = segments;
  const params = { ...queryParams };
  if (params.context && !params.context_type) {
    params.context_type = params.context;
  }
  delete params.context;

  const queryRecipientId = params.user ?? params.to ?? params.to_user;
  delete params.user;
  delete params.to;
  delete params.to_user;

  if (branch === 'new' && detailId) {
    return appendParams('/(modals)/thread', { ...params, recipientId: detailId });
  }
  if (queryRecipientId) {
    return appendParams('/(modals)/thread', { ...params, recipientId: queryRecipientId });
  }
  if (branch && branch !== 'new') {
    return appendParams('/(modals)/thread', { ...params, id: branch });
  }
  return branch === 'new' ? appendParams('/(modals)/new-message', params) : appendParams('/(tabs)/messages', params);
}

function appendParams(pathname: string, params: Record<string, string | undefined>): string {
  const searchParams = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value) searchParams.set(key, value);
  });
  const query = searchParams.toString();
  return query ? `${pathname}?${query}` : pathname;
}

function isCreateAlias(value: string | undefined): boolean {
  return value === 'new' || value === 'create';
}
