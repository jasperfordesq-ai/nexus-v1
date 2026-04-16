// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import { getNotificationDisplayText } from './notificationText';

describe('getNotificationDisplayText', () => {
  it('returns plain notification copy unchanged', () => {
    expect(getNotificationDisplayText({
      message: 'You have been granted the "ID Verified" verification badge',
      body: '',
      title: '',
    })).toBe('You have been granted the "ID Verified" verification badge');
  });

  it('resolves known service translation keys', () => {
    expect(getNotificationDisplayText({
      message: 'svc_notifications.gamification.badge_earned',
      body: '',
      title: '',
    })).toBe('Badge earned');
  });

  it('resolves known email translation keys', () => {
    expect(getNotificationDisplayText({
      message: 'emails_misc.stories.new_story_notification',
      body: '',
      title: '',
    })).toBe('New story notification');
  });

  it('humanizes unknown notification keys instead of showing raw keys', () => {
    expect(getNotificationDisplayText({
      message: 'notifications.group_joined',
      body: '',
      title: '',
    })).toBe('Group joined');
  });
});

