// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import assert from 'node:assert/strict';
import test from 'node:test';

import {
  interpolationSignature,
  interpolationTokens,
} from './check-i18n-interpolation-parity.mjs';

test('extracts i18next and Laravel interpolation tokens with their syntax intact', () => {
  assert.deepEqual(
    interpolationTokens(':guardian sent {{count, number}} items to :recipient_name.'),
    [':guardian', ':recipient_name', '{{count}}'],
  );
});

test('extracts Laravel placeholders at supported punctuation boundaries', () => {
  assert.deepEqual(
    interpolationTokens('Tenant ":name"; report #:incident_id; <strong>:title</strong>; expiry :expiry.:notes'),
    [':expiry', ':incident_id', ':name', ':notes', ':title'],
  );
});

test('preserves duplicate placeholders in the parity signature', () => {
  assert.equal(interpolationSignature(':name met :name in {{community}}'), ':name|:name|{{community}}');
});

test('does not treat URL schemes, clock times, or compact prose labels as placeholders', () => {
  assert.deepEqual(
    interpolationTokens(
      'Open https://example.org at 09:30; mail mailto:name@example.org; Status:active; État:actif; use namespace::value.',
    ),
    [],
  );
});

test('distinguishes Laravel placeholders from i18next placeholders with the same name', () => {
  assert.notEqual(interpolationSignature(':name'), interpolationSignature('{{name}}'));
});
