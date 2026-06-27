// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createHash } from 'node:crypto';

import { loadContent, sceneAudioName, TOOL_ROOT } from './lib/content.mjs';
import { ffprobeDurationSec, runCommand, assertCommand } from './lib/process.mjs';
import { singleCueSrt } from './lib/srt.mjs';

const DEFAULT_TRAILING_SILENCE_SEC = 0.5;
const DEFAULT_TTS_RETRIES = 3;

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  await main();
}

export async function main(argv = process.argv.slice(2)) {
  const options = parseArgs(argv);
  const content = loadContent(options.contentPath);
  const outputRoot = path.join(TOOL_ROOT, 'output');
  const audioDir = path.join(outputRoot, 'audio');
  const captionDir = path.join(outputRoot, 'captions');
  fs.mkdirSync(audioDir, { recursive: true });
  fs.mkdirSync(captionDir, { recursive: true });

  await assertCommand('ffprobe', ['-version'], 'ffprobe is required. Install FFmpeg, then ensure ffprobe is on PATH.');
  await assertCommand('ffmpeg', ['-version'], 'ffmpeg is required. Install FFmpeg, then ensure ffmpeg is on PATH.');
  if (options.engine === 'edge') {
    await assertCommand('python', ['-m', 'edge_tts', '--help'], 'edge-tts is required. Install it with: python -m pip install edge-tts');
  }

  const scenes = [];
  for (const [index, scene] of content.scenes.entries()) {
    const baseName = sceneAudioName(index + 1, scene.id);
    const rawAudioPath = path.join(audioDir, `${baseName}.raw.mp3`);
    const audioPath = path.join(audioDir, `${baseName}.mp3`);
    const rawSrtPath = path.join(captionDir, `${baseName}.raw.srt`);
    const srtPath = path.join(captionDir, `${baseName}.srt`);
    const cachePath = path.join(audioDir, `${baseName}.meta.json`);
    const cacheKey = sceneCacheKey(scene, options);

    const cached = await readCachedScene(scene, audioPath, srtPath, cachePath, cacheKey, options);
    if (cached) {
      scenes.push(cached);
      continue;
    }

    if (options.engine === 'edge') {
      await synthesizeWithEdgeTts(scene.narration, rawAudioPath, rawSrtPath, options);
    } else {
      await synthesizeWithOpenAi(scene.narration, rawAudioPath, options);
      fs.writeFileSync(rawSrtPath, singleCueSrt(scene.narration, 1000), 'utf8');
    }

    await addTrailingSilence(rawAudioPath, audioPath, options.trailingSilenceSec);
    const durationSec = await ffprobeDurationSec(audioPath);

    if (options.engine === 'openai') {
      fs.writeFileSync(srtPath, singleCueSrt(scene.narration, Math.round(durationSec * 1000)), 'utf8');
    } else {
      fs.copyFileSync(rawSrtPath, srtPath);
    }
    writeSceneCache(cachePath, cacheKey);

    scenes.push({
      sceneId: scene.id,
      title: scene.title,
      narration: scene.narration,
      audioPath: toToolRelative(audioPath),
      srtPath: toToolRelative(srtPath),
      durationSec: roundDuration(durationSec),
    });
    console.log(`[voiceover] ${scene.id}: ${roundDuration(durationSec)}s`);
  }

  const manifest = {
    videoId: content.id,
    title: content.title,
    engine: options.engine,
    voice: options.engine === 'edge' ? options.voice : options.openaiVoice,
    rate: options.rate,
    trailingSilenceSec: options.trailingSilenceSec,
    generatedAt: new Date().toISOString(),
    scenes,
  };

  const manifestPath = path.join(outputRoot, `${content.id}.voiceover-manifest.json`);
  fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2) + '\n', 'utf8');
  console.log(`[voiceover] wrote ${toToolRelative(manifestPath)}`);
  return manifestPath;
}

async function synthesizeWithEdgeTts(text, mediaPath, srtPath, options) {
  for (let attempt = 1; attempt <= options.ttsRetries; attempt += 1) {
    try {
      await runCommand('python', [
        '-m', 'edge_tts',
        '-v', options.voice,
        '--rate', options.rate,
        '--write-media', mediaPath,
        '--write-subtitles', srtPath,
        '-t', text,
      ], { stdio: 'pipe' });
      return;
    } catch (error) {
      fs.rmSync(mediaPath, { force: true });
      fs.rmSync(srtPath, { force: true });
      if (attempt >= options.ttsRetries) throw error;
      const delayMs = attempt * 2000;
      console.warn(`[voiceover] edge-tts failed on attempt ${attempt}/${options.ttsRetries}; retrying in ${delayMs}ms`);
      await sleep(delayMs);
    }
  }
}

async function synthesizeWithOpenAi(text, mediaPath, options) {
  const apiKey = process.env.OPENAI_API_KEY;
  if (!apiKey) {
    throw new Error('OPENAI_API_KEY is required when --engine openai is selected. This bills the OpenAI API.');
  }

  const response = await fetch('https://api.openai.com/v1/audio/speech', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${apiKey}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      model: options.openaiModel,
      voice: options.openaiVoice,
      input: text,
      instructions: options.openaiInstructions,
      response_format: 'mp3',
    }),
  });

  if (!response.ok) {
    throw new Error(`OpenAI TTS failed (${response.status}): ${await response.text()}`);
  }

  fs.writeFileSync(mediaPath, Buffer.from(await response.arrayBuffer()));
}

async function addTrailingSilence(inputPath, outputPath, trailingSilenceSec) {
  await runCommand('ffmpeg', [
    '-y',
    '-i', inputPath,
    '-af', `apad=pad_dur=${trailingSilenceSec}`,
    '-c:a', 'libmp3lame',
    '-q:a', '2',
    outputPath,
  ]);
}

function parseArgs(argv) {
  const options = {
    contentPath: 'content/video-01-getting-started.json',
    engine: process.env.NEXUS_VIDEO_TTS_ENGINE || 'edge',
    voice: process.env.NEXUS_VIDEO_EDGE_VOICE || 'en-GB-SoniaNeural',
    rate: process.env.NEXUS_VIDEO_EDGE_RATE || '-8%',
    trailingSilenceSec: Number(process.env.NEXUS_VIDEO_TRAILING_SILENCE_SEC || DEFAULT_TRAILING_SILENCE_SEC),
    ttsRetries: Number(process.env.NEXUS_VIDEO_TTS_RETRIES || DEFAULT_TTS_RETRIES),
    reuseExisting: process.env.NEXUS_VIDEO_REUSE_AUDIO !== '0',
    openaiModel: process.env.NEXUS_VIDEO_OPENAI_TTS_MODEL || 'gpt-4o-mini-tts',
    openaiVoice: process.env.NEXUS_VIDEO_OPENAI_TTS_VOICE || 'fable',
    openaiInstructions: process.env.NEXUS_VIDEO_OPENAI_TTS_INSTRUCTIONS || 'Warm, friendly southern-English British accent, calm and clear, relaxed pace.',
  };

  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    const next = argv[i + 1];
    if (arg === '--engine') { options.engine = next; i += 1; }
    else if (arg === '--voice') { options.voice = next; i += 1; }
    else if (arg === '--rate') { options.rate = next; i += 1; }
    else if (arg === '--openai-model') { options.openaiModel = next; i += 1; }
    else if (arg === '--openai-voice') { options.openaiVoice = next; i += 1; }
    else if (arg === '--openai-instructions') { options.openaiInstructions = next; i += 1; }
    else if (arg === '--trailing-silence-sec') { options.trailingSilenceSec = Number(next); i += 1; }
    else if (arg === '--tts-retries') { options.ttsRetries = Number(next); i += 1; }
    else if (arg === '--no-reuse') options.reuseExisting = false;
    else if (!arg.startsWith('--')) options.contentPath = arg;
    else throw new Error(`Unknown voiceover option: ${arg}`);
  }

  if (!['edge', 'openai'].includes(options.engine)) {
    throw new Error('--engine must be "edge" or "openai".');
  }
  if (!Number.isFinite(options.trailingSilenceSec) || options.trailingSilenceSec < 0) {
    throw new Error('--trailing-silence-sec must be a non-negative number.');
  }
  if (!Number.isInteger(options.ttsRetries) || options.ttsRetries < 1) {
    throw new Error('--tts-retries must be a positive integer.');
  }
  return options;
}

function toToolRelative(filePath) {
  return path.relative(TOOL_ROOT, filePath).replace(/\\/g, '/');
}

function roundDuration(durationSec) {
  return Math.round(durationSec * 1000) / 1000;
}

async function readCachedScene(scene, audioPath, srtPath, cachePath, cacheKey, options) {
  if (!options.reuseExisting || !fs.existsSync(audioPath) || !fs.existsSync(srtPath)) return null;
  if (fs.existsSync(cachePath)) {
    const cache = JSON.parse(fs.readFileSync(cachePath, 'utf8'));
    if (cache.cacheKey !== cacheKey) return null;
  }

  try {
    const durationSec = await ffprobeDurationSec(audioPath);
    if (!fs.existsSync(cachePath)) writeSceneCache(cachePath, cacheKey);
    console.log(`[voiceover] ${scene.id}: ${roundDuration(durationSec)}s (cached)`);
    return {
      sceneId: scene.id,
      title: scene.title,
      narration: scene.narration,
      audioPath: toToolRelative(audioPath),
      srtPath: toToolRelative(srtPath),
      durationSec: roundDuration(durationSec),
    };
  } catch {
    return null;
  }
}

function writeSceneCache(cachePath, cacheKey) {
  fs.writeFileSync(cachePath, JSON.stringify({ cacheKey, generatedAt: new Date().toISOString() }, null, 2) + '\n', 'utf8');
}

function sceneCacheKey(scene, options) {
  const input = JSON.stringify({
    narration: scene.narration,
    engine: options.engine,
    voice: options.engine === 'edge' ? options.voice : options.openaiVoice,
    rate: options.engine === 'edge' ? options.rate : undefined,
    openaiModel: options.engine === 'openai' ? options.openaiModel : undefined,
    openaiInstructions: options.engine === 'openai' ? options.openaiInstructions : undefined,
    trailingSilenceSec: options.trailingSilenceSec,
  });
  return createHash('sha256').update(input).digest('hex');
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}
