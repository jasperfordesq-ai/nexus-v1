// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import assert from 'node:assert/strict';
import test from 'node:test';

import {
  formatSrtTimestamp,
  mergeSceneSrts,
  offsetSrtText,
  parseSrtTimestamp,
  singleCueSrt,
} from './srt.mjs';

test('SRT timestamp parsing and formatting is reversible', () => {
  assert.equal(parseSrtTimestamp('00:01:02,345'), 62345);
  assert.equal(formatSrtTimestamp(62345), '00:01:02,345');
});

test('offsetSrtText shifts every cue by the requested offset', () => {
  const shifted = offsetSrtText('1\n00:00:01,000 --> 00:00:02,500\nHello\n', 2250);
  assert.match(shifted, /00:00:03,250 --> 00:00:04,750/);
});

test('mergeSceneSrts applies cumulative scene offsets and renumbers cues', () => {
  const merged = mergeSceneSrts([
    { srt: '1\n00:00:00,000 --> 00:00:01,000\nOne\n', startsAtMs: 0 },
    { srt: '7\n00:00:00,500 --> 00:00:01,000\nTwo\n', startsAtMs: 1500 },
  ]);

  assert.match(merged, /^1\n00:00:00,000 --> 00:00:01,000\nOne\n\n2\n00:00:02,000 --> 00:00:02,500\nTwo\n?$/);
});

test('singleCueSrt creates a valid fallback caption cue', () => {
  const srt = singleCueSrt('Hello world', 1750);
  assert.match(srt, /^1\n00:00:00,000 --> 00:00:01,750\nHello world\n?$/);
});
