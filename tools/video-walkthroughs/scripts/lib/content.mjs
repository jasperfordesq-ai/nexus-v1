// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
export const TOOL_ROOT = path.resolve(__dirname, '../..');

export function resolveContentPath(inputPath = 'content/video-01-getting-started.json') {
  const cwdPath = path.resolve(process.cwd(), inputPath);
  if (fs.existsSync(cwdPath)) return cwdPath;
  return path.resolve(TOOL_ROOT, inputPath);
}

export function loadContent(inputPath) {
  const resolved = resolveContentPath(inputPath);
  return validateContent(JSON.parse(fs.readFileSync(resolved, 'utf8')));
}

export function validateContent(content) {
  if (!content || typeof content !== 'object') {
    throw new Error('Walkthrough content must be a JSON object.');
  }
  if (!isNonEmptyString(content.id)) throw new Error('Walkthrough content requires an id.');
  if (!isNonEmptyString(content.title)) throw new Error('Walkthrough content requires a title.');
  if (!isNonEmptyString(content.tenantSlug)) throw new Error('Walkthrough content requires tenantSlug.');
  if (!Array.isArray(content.scenes) || content.scenes.length === 0) {
    throw new Error('Walkthrough content requires at least one scene.');
  }

  const seen = new Set();
  for (const [index, scene] of content.scenes.entries()) {
    if (!scene || typeof scene !== 'object') throw new Error(`Scene ${index + 1} must be an object.`);
    if (!isNonEmptyString(scene.id)) throw new Error(`Scene ${index + 1} requires an id.`);
    if (seen.has(scene.id)) throw new Error(`Walkthrough content has duplicate scene id "${scene.id}".`);
    seen.add(scene.id);
    if (!isNonEmptyString(scene.title)) throw new Error(`Scene "${scene.id}" requires a title.`);
    if (!isNonEmptyString(scene.narration)) throw new Error(`Scene "${scene.id}" requires narration.`);
    if (!Array.isArray(scene.actions) || scene.actions.length === 0) {
      throw new Error(`Scene "${scene.id}" requires at least one action.`);
    }
  }

  return content;
}

export function sceneAudioName(sceneIndex, sceneId) {
  const number = String(sceneIndex).padStart(2, '0');
  const slug = String(sceneId)
    .toLowerCase()
    .replace(/['"]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
  return `${number}-${slug || 'scene'}`;
}

function isNonEmptyString(value) {
  return typeof value === 'string' && value.trim().length > 0;
}
