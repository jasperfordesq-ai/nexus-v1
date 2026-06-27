// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { loadContent, TOOL_ROOT } from './lib/content.mjs';
import { buildAudioConcatList, validateVoiceoverManifest } from './lib/manifest.mjs';
import { runCommand, assertCommand } from './lib/process.mjs';
import { mergeSceneSrts } from './lib/srt.mjs';

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  await main();
}

export async function main(argv = process.argv.slice(2)) {
  const options = parseArgs(argv);
  const content = loadContent(options.contentPath);
  const outputRoot = path.join(TOOL_ROOT, 'output');

  await assertCommand('ffmpeg', ['-version'], 'ffmpeg is required. Install FFmpeg, then ensure ffmpeg is on PATH.');
  const voiceover = validateVoiceoverManifest(readJson(path.join(outputRoot, `${content.id}.voiceover-manifest.json`)));
  const recording = readJson(path.join(outputRoot, `${content.id}.recording-manifest.json`));
  const screenVideoPath = resolveToolPath(recording.screenVideoPath);
  const sceneDurations = targetSceneDurations(voiceover.scenes, recording.scenes ?? []);

  const concatPath = path.join(outputRoot, `${content.id}.audio-concat.txt`);
  const audioPaths = await buildPaddedAudioPaths(outputRoot, content.id, voiceover.scenes, sceneDurations);
  fs.writeFileSync(concatPath, buildAudioConcatList(audioPaths), 'utf8');

  const audioPath = path.join(outputRoot, `${content.id}.audio.m4a`);
  await runCommand('ffmpeg', [
    '-y',
    '-f', 'concat',
    '-safe', '0',
    '-i', concatPath,
    '-af', 'loudnorm=I=-16:LRA=11:TP=-1.5',
    '-ar', '48000',
    '-c:a', 'aac',
    '-b:a', '192k',
    audioPath,
  ]);

  const starts = cumulativeStartsFromDurations(sceneDurations);
  const srt = mergeSceneSrts(voiceover.scenes.map((scene, index) => ({
    srt: fs.readFileSync(resolveToolPath(scene.srtPath), 'utf8'),
    startsAtMs: starts[index],
  })));
  const srtPath = path.join(outputRoot, `${content.id}.srt`);
  fs.writeFileSync(srtPath, srt, 'utf8');

  const cleanMp4Path = path.join(outputRoot, `${content.id}.mp4`);
  await runCommand('ffmpeg', [
    '-y',
    '-i', screenVideoPath,
    '-i', audioPath,
    '-map', '0:v:0',
    '-map', '1:a:0',
    '-c:v', 'copy',
    '-c:a', 'aac',
    '-b:a', '192k',
    '-shortest',
    '-movflags', '+faststart',
    cleanMp4Path,
  ]);

  const captionedMp4Path = path.join(outputRoot, `${content.id}.captioned.mp4`);
  await runCommand('ffmpeg', [
    '-y',
    '-i', path.basename(cleanMp4Path),
    '-vf', `subtitles=${path.basename(srtPath)}:force_style='FontName=Arial,FontSize=9,PrimaryColour=&H00FFFFFF,OutlineColour=&H80000000,BorderStyle=3,Outline=1,Shadow=0,MarginV=28'`,
    '-c:v', 'libx264',
    '-preset', 'slow',
    '-crf', '18',
    '-c:a', 'copy',
    path.basename(captionedMp4Path),
  ], { cwd: outputRoot });

  const assemblyManifestPath = path.join(outputRoot, `${content.id}.assembly-manifest.json`);
  fs.writeFileSync(assemblyManifestPath, JSON.stringify({
    videoId: content.id,
    audioPath: toToolRelative(audioPath),
    srtPath: toToolRelative(srtPath),
    mp4Path: toToolRelative(cleanMp4Path),
    captionedMp4Path: toToolRelative(captionedMp4Path),
    sceneDurations,
    assembledAt: new Date().toISOString(),
  }, null, 2) + '\n', 'utf8');

  console.log(`[assemble] wrote ${toToolRelative(cleanMp4Path)}`);
  console.log(`[assemble] wrote ${toToolRelative(captionedMp4Path)}`);
  console.log(`[assemble] wrote ${toToolRelative(srtPath)}`);
  return cleanMp4Path;
}

function parseArgs(argv) {
  const options = { contentPath: 'content/video-01-getting-started.json' };
  for (const arg of argv) {
    if (!arg.startsWith('--')) options.contentPath = arg;
    else throw new Error(`Unknown assembly option: ${arg}`);
  }
  return options;
}

function readJson(filePath) {
  if (!fs.existsSync(filePath)) throw new Error(`Missing required manifest: ${filePath}`);
  return JSON.parse(fs.readFileSync(filePath, 'utf8'));
}

async function buildPaddedAudioPaths(outputRoot, videoId, scenes, sceneDurations) {
  const audioDir = path.join(outputRoot, 'audio');
  fs.mkdirSync(audioDir, { recursive: true });
  const audioPaths = [];

  for (const [index, scene] of scenes.entries()) {
    audioPaths.push(resolveToolPath(scene.audioPath));
    const paddingSec = Math.max(0, sceneDurations[index] - scene.durationSec);
    if (paddingSec > 0.08) {
      const silencePath = path.join(audioDir, `${videoId}-${String(index + 1).padStart(2, '0')}-pad.mp3`);
      await runCommand('ffmpeg', [
        '-y',
        '-f', 'lavfi',
        '-i', 'anullsrc=r=48000:cl=mono',
        '-t', paddingSec.toFixed(3),
        '-c:a', 'libmp3lame',
        '-q:a', '9',
        silencePath,
      ]);
      audioPaths.push(silencePath);
    }
  }

  return audioPaths;
}

function targetSceneDurations(voiceoverScenes, recordingScenes) {
  const recordingById = new Map(recordingScenes.map((scene) => [scene.sceneId, scene]));
  return voiceoverScenes.map((scene) => {
    const recordedDuration = Number(recordingById.get(scene.sceneId)?.durationSec);
    const target = Number.isFinite(recordedDuration)
      ? Math.max(scene.durationSec, recordedDuration)
      : scene.durationSec;
    return Math.round(target * 1000) / 1000;
  });
}

function cumulativeStartsFromDurations(sceneDurations) {
  const starts = [];
  let cursorMs = 0;
  for (const durationSec of sceneDurations) {
    starts.push(cursorMs);
    cursorMs += Math.round(durationSec * 1000);
  }
  return starts;
}

function resolveToolPath(filePath) {
  return path.isAbsolute(filePath) ? filePath : path.join(TOOL_ROOT, filePath);
}

function toToolRelative(filePath) {
  return path.relative(TOOL_ROOT, filePath).replace(/\\/g, '/');
}
