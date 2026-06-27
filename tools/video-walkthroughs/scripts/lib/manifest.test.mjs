// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import assert from 'node:assert/strict';
import test from 'node:test';

import { buildAudioConcatList, cumulativeSceneStarts, validateVoiceoverManifest } from './manifest.mjs';

test('validateVoiceoverManifest accepts generated scene timing metadata', () => {
  const manifest = {
    videoId: 'video-01-getting-started',
    generatedAt: '2026-06-27T12:00:00.000Z',
    scenes: [
      {
        sceneId: 'intro',
        narration: 'Hello.',
        audioPath: 'output/audio/01-intro.mp3',
        srtPath: 'output/captions/01-intro.srt',
        durationSec: 3.5,
      },
    ],
  };

  assert.equal(validateVoiceoverManifest(manifest), manifest);
});

test('cumulativeSceneStarts computes millisecond offsets in scene order', () => {
  assert.deepEqual(
    cumulativeSceneStarts([
      { sceneId: 'a', durationSec: 1.25 },
      { sceneId: 'b', durationSec: 2 },
      { sceneId: 'c', durationSec: 0.5 },
    ]),
    [
      { sceneId: 'a', startsAtMs: 0 },
      { sceneId: 'b', startsAtMs: 1250 },
      { sceneId: 'c', startsAtMs: 3250 },
    ],
  );
});

test('buildAudioConcatList escapes ffmpeg concat file paths', () => {
  const list = buildAudioConcatList([
    'C:/video/audio/01 intro.mp3',
    "C:/video/audio/02 user's request.mp3",
  ]);

  assert.equal(
    list,
    "file 'C:/video/audio/01 intro.mp3'\nfile 'C:/video/audio/02 user'\\''s request.mp3'\n",
  );
});
