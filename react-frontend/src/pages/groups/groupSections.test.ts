// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it, vi } from 'vitest';
import { getAvailableGroupSections, isGroupSectionKey } from './groupSections';

const enabled = vi.fn(() => true);

describe('getAvailableGroupSections', () => {
  it('limits nonmembers to safe entry sections and available subgroup metadata', () => {
    expect(getAvailableGroupSections({
      hasGroupTab: enabled,
      hasSubgroups: true,
      userIsAdmin: false,
      userIsMember: false,
    })).toEqual(['feed', 'subgroups', 'discussion']);
  });

  it('includes configured member sections but excludes admin analytics', () => {
    const sections = getAvailableGroupSections({
      hasGroupTab: enabled,
      hasSubgroups: false,
      userIsAdmin: false,
      userIsMember: true,
    });

    expect(sections).toContain('files');
    expect(sections).toContain('challenges');
    expect(sections).not.toContain('subgroups');
    expect(sections).not.toContain('analytics');
  });

  it('honours tenant tab configuration for every section including subgroups', () => {
    const sections = getAvailableGroupSections({
      hasGroupTab: (key) => key !== 'tab_feed' && key !== 'tab_subgroups',
      hasSubgroups: true,
      userIsAdmin: true,
      userIsMember: true,
    });

    expect(sections).not.toContain('feed');
    expect(sections).not.toContain('subgroups');
    expect(sections).toContain('analytics');
    expect(sections).toContain('automation');
  });

  it('cross-gates Events by the tenant feature without hiding admin automation', () => {
    const sections = getAvailableGroupSections({
      hasGroupTab: enabled,
      hasSubgroups: false,
      userIsAdmin: true,
      userIsMember: true,
      hasEventsFeature: false,
    });

    expect(sections).not.toContain('events');
    expect(sections).toContain('automation');
  });
});

describe('isGroupSectionKey', () => {
  it('accepts known keys and rejects arbitrary query values', () => {
    expect(isGroupSectionKey('discussion')).toBe(true);
    expect(isGroupSectionKey('automation')).toBe(true);
    expect(isGroupSectionKey('unknown')).toBe(false);
    expect(isGroupSectionKey(null)).toBe(false);
  });
});
