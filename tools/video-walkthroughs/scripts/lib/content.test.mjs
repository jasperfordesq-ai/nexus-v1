// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import assert from 'node:assert/strict';
import test from 'node:test';

import { validateContent, sceneAudioName, resolveContentPath } from './content.mjs';

test('validateContent accepts a complete walkthrough script', () => {
  const content = {
    id: 'video-01-getting-started',
    title: 'Getting started with Nexus',
    tenantSlug: 'hour-timebank',
    scenes: [
      {
        id: 'intro',
        title: 'Intro',
        narration: 'Welcome to Nexus.',
        actions: ['show-feed'],
      },
    ],
  };

  assert.equal(validateContent(content), content);
});

test('validateContent rejects duplicate or incomplete scenes', () => {
  assert.throws(
    () => validateContent({
      id: 'bad-video',
      title: 'Bad video',
      tenantSlug: 'hour-timebank',
      scenes: [
        { id: 'intro', title: 'Intro', narration: 'Hello.', actions: ['show-feed'] },
        { id: 'intro', title: 'Duplicate', narration: '', actions: [] },
      ],
    }),
    /duplicate scene id|narration/i,
  );
});

test('sceneAudioName produces deterministic safe filenames', () => {
  assert.equal(sceneAudioName(3, 'post an offer'), '03-post-an-offer');
  assert.equal(sceneAudioName(12, 'Wrap / wallet'), '12-wrap-wallet');
});

test('resolveContentPath supports paths relative to the tool root', () => {
  const resolved = resolveContentPath('content/video-01-getting-started.json');
  assert.match(resolved, /tools[\\/]video-walkthroughs[\\/]content[\\/]video-01-getting-started\.json$/);
});
