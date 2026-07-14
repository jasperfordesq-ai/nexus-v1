// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { execFileSync } from 'child_process';
import path from 'path';

const PHP_ARRAY_COMMAND = 'echo json_encode(require $argv[1], JSON_UNESCAPED_UNICODE);';

function evaluatePhpArray(executable, args) {
  const output = execFileSync(executable, args, {
    encoding: 'utf8',
    maxBuffer: 64 * 1024 * 1024,
  });

  return JSON.parse(output);
}

export function loadPhpArray(file, { root = process.cwd() } = {}) {
  const phpArgs = [
    '-d',
    'display_errors=stderr',
    '-r',
    PHP_ARRAY_COMMAND,
    file,
  ];

  try {
    return evaluatePhpArray('php', phpArgs);
  } catch (error) {
    if (!error || typeof error !== 'object' || error.code !== 'ENOENT') {
      throw error;
    }
  }

  const relativeFile = path.relative(path.resolve(root), path.resolve(file));
  if (relativeFile.startsWith('..') || path.isAbsolute(relativeFile)) {
    throw new Error(`Cannot map PHP translation file outside the project root: ${file}`);
  }

  const container = process.env.NEXUS_PHP_CONTAINER || 'nexus-php-app';
  const containerRoot = (process.env.NEXUS_PHP_CONTAINER_ROOT || '/var/www/html').replace(/\/$/u, '');
  const containerFile = `${containerRoot}/${relativeFile.replace(/\\/gu, '/')}`;

  return evaluatePhpArray('docker', [
    'exec',
    container,
    'php',
    '-d',
    'display_errors=stderr',
    '-r',
    PHP_ARRAY_COMMAND,
    containerFile,
  ]);
}
