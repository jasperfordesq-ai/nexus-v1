// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { spawn } from 'node:child_process';

export function runCommand(command, args = [], options = {}) {
  const {
    cwd,
    env,
    input,
    stdio = 'pipe',
    allowFailure = false,
  } = options;

  return new Promise((resolve, reject) => {
    const child = spawn(command, args, {
      cwd,
      env: { ...process.env, ...env },
      stdio,
      windowsHide: true,
    });

    let stdout = '';
    let stderr = '';

    if (child.stdout) child.stdout.on('data', (chunk) => { stdout += chunk.toString(); });
    if (child.stderr) child.stderr.on('data', (chunk) => { stderr += chunk.toString(); });
    if (input && child.stdin) {
      child.stdin.write(input);
      child.stdin.end();
    }

    child.on('error', reject);
    child.on('close', (exitCode) => {
      const result = { command, args, exitCode, stdout, stderr };
      if (exitCode === 0 || allowFailure) {
        resolve(result);
        return;
      }
      const rendered = [command, ...args].join(' ');
      reject(new Error(`Command failed (${exitCode}): ${rendered}\n${stderr || stdout}`));
    });
  });
}

export async function assertCommand(command, args, helpText) {
  try {
    await runCommand(command, args, { allowFailure: false });
  } catch (error) {
    throw new Error(`${helpText}\n\nOriginal error:\n${error.message}`);
  }
}

export async function ffprobeDurationSec(filePath) {
  const result = await runCommand('ffprobe', [
    '-v', 'error',
    '-show_entries', 'format=duration',
    '-of', 'default=noprint_wrappers=1:nokey=1',
    filePath,
  ]);
  const duration = Number.parseFloat(result.stdout.trim());
  if (!Number.isFinite(duration) || duration <= 0) {
    throw new Error(`ffprobe could not read a positive duration for ${filePath}`);
  }
  return duration;
}
