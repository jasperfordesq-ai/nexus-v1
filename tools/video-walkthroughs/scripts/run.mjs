// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { runCommand, assertCommand } from './lib/process.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  await main();
}

export async function main(argv = process.argv.slice(2)) {
  const { contentPath, voiceoverArgs, recordingArgs } = parseArgs(argv);
  const node = process.execPath;

  await assertCommand('ffmpeg', ['-version'], 'ffmpeg is required. Install FFmpeg, then ensure ffmpeg is on PATH.');
  await assertCommand('ffprobe', ['-version'], 'ffprobe is required. Install FFmpeg, then ensure ffprobe is on PATH.');

  const stages = [
    ['01-generate-voiceover.mjs', [contentPath, ...voiceoverArgs]],
    ['02-record-walkthrough.mjs', [contentPath, ...recordingArgs]],
    ['03-assemble.mjs', [contentPath]],
  ];

  for (const [script, args] of stages) {
    console.log(`\n[run] ${script}`);
    await runCommand(node, [path.join(__dirname, script), ...args], { stdio: 'inherit' });
  }
}

function parseArgs(argv) {
  const valueOptions = new Map([
    ['--engine', 'voiceover'],
    ['--voice', 'voiceover'],
    ['--rate', 'voiceover'],
    ['--openai-model', 'voiceover'],
    ['--openai-voice', 'voiceover'],
    ['--openai-instructions', 'voiceover'],
    ['--trailing-silence-sec', 'voiceover'],
    ['--tts-retries', 'voiceover'],
    ['--base-url', 'recording'],
    ['--api-url', 'recording'],
  ]);
  const flagOptions = new Map([
    ['--no-reuse', 'voiceover'],
    ['--headed', 'recording'],
    ['--headless', 'recording'],
  ]);
  const parsed = {
    contentPath: 'content/video-01-getting-started.json',
    voiceoverArgs: [],
    recordingArgs: [],
  };

  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    if (!arg.startsWith('--')) {
      parsed.contentPath = arg;
      continue;
    }

    const valueTarget = valueOptions.get(arg);
    if (valueTarget) {
      const value = argv[i + 1];
      if (value === undefined) throw new Error(`${arg} requires a value.`);
      targetArgs(parsed, valueTarget).push(arg, value);
      i += 1;
      continue;
    }

    const flagTarget = flagOptions.get(arg);
    if (flagTarget) {
      targetArgs(parsed, flagTarget).push(arg);
      continue;
    }

    throw new Error(`Unknown run option: ${arg}`);
  }

  return parsed;
}

function targetArgs(parsed, target) {
  return target === 'voiceover' ? parsed.voiceoverArgs : parsed.recordingArgs;
}
