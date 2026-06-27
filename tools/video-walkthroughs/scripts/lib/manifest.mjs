// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

export function validateVoiceoverManifest(manifest) {
  if (!manifest || typeof manifest !== 'object') {
    throw new Error('Voiceover manifest must be a JSON object.');
  }
  if (typeof manifest.videoId !== 'string' || manifest.videoId.trim() === '') {
    throw new Error('Voiceover manifest requires videoId.');
  }
  if (!Array.isArray(manifest.scenes) || manifest.scenes.length === 0) {
    throw new Error('Voiceover manifest requires scenes.');
  }

  for (const scene of manifest.scenes) {
    if (typeof scene.sceneId !== 'string' || scene.sceneId.trim() === '') {
      throw new Error('Manifest scene requires sceneId.');
    }
    if (typeof scene.narration !== 'string' || scene.narration.trim() === '') {
      throw new Error(`Manifest scene "${scene.sceneId}" requires narration.`);
    }
    if (typeof scene.audioPath !== 'string' || scene.audioPath.trim() === '') {
      throw new Error(`Manifest scene "${scene.sceneId}" requires audioPath.`);
    }
    if (typeof scene.srtPath !== 'string' || scene.srtPath.trim() === '') {
      throw new Error(`Manifest scene "${scene.sceneId}" requires srtPath.`);
    }
    if (!Number.isFinite(scene.durationSec) || scene.durationSec <= 0) {
      throw new Error(`Manifest scene "${scene.sceneId}" requires a positive durationSec.`);
    }
  }

  return manifest;
}

export function cumulativeSceneStarts(scenes) {
  let startsAtMs = 0;
  return scenes.map((scene) => {
    const current = { sceneId: scene.sceneId, startsAtMs };
    startsAtMs += Math.round(scene.durationSec * 1000);
    return current;
  });
}

export function buildAudioConcatList(audioPaths) {
  return audioPaths.map((audioPath) => `file '${escapeConcatPath(audioPath)}'\n`).join('');
}

function escapeConcatPath(audioPath) {
  return String(audioPath).replace(/\\/g, '/').replace(/'/g, "'\\''");
}
