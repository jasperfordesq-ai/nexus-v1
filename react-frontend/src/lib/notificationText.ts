// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import i18n from '@/i18n';
import type { Notification } from '@/types/api';

const KNOWN_NOTIFICATION_NAMESPACES = new Set([
  'notifications',
  'svc_notifications',
  'svc_notifications_2',
  'emails_misc',
  'emails_notifications',
]);

function looksLikeTranslationKey(value: string): boolean {
  return /^[a-z0-9_]+(?:\.[a-z0-9_]+)+$/i.test(value.trim());
}

function humanizeNotificationKey(key: string): string {
  const lastSegment = key.split('.').pop() ?? key;
  const withSpaces = lastSegment.replace(/_/g, ' ').trim();

  if (!withSpaces) {
    return '';
  }

  return withSpaces.charAt(0).toUpperCase() + withSpaces.slice(1);
}

function resolveTranslationKey(key: string): string | null {
  const trimmedKey = key.trim();
  const segments = trimmedKey.split('.');

  if (segments.length < 2) {
    return null;
  }

  const namespace = segments[0];
  const translationKey = segments.slice(1).join('.');

  if (!namespace || !KNOWN_NOTIFICATION_NAMESPACES.has(namespace)) {
    return null;
  }

  const translated = i18n.t(translationKey, {
    ns: namespace,
    defaultValue: trimmedKey,
  });

  if (!translated || translated === trimmedKey || translated.includes('{{')) {
    return null;
  }

  return translated;
}

export function getNotificationDisplayText(notification: Pick<Notification, 'message' | 'body' | 'title'>): string {
  const rawText = [notification.message, notification.body, notification.title]
    .find((value): value is string => typeof value === 'string' && value.trim().length > 0)
    ?.trim();

  if (!rawText) {
    return '';
  }

  if (!looksLikeTranslationKey(rawText)) {
    return rawText;
  }

  return resolveTranslationKey(rawText) ?? humanizeNotificationKey(rawText);
}
