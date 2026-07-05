// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Local guardrail check for safe dual-backend preparation.
 *
 * This intentionally checks policy, not ASP.NET readiness:
 * - ordinary dev/build scripts must remain Laravel-safe;
 * - backendTarget must default invalid/missing values to Laravel;
 * - pages/components must not branch on ASP.NET backend flags.
 */

import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const defaultRoot = path.resolve(__dirname, '..');
const forbiddenFrontendPatterns = [
  /\bisDotnetBackend\b/,
  /\bbackendTarget\b/,
  /\bVITE_BACKEND_TARGET\b/,
  /\bdotnet\b/i,
  /\bASP\.NET\b/i,
];

function parseArgs(argv) {
  const options = {
    root: defaultRoot,
  };

  for (let index = 0; index < argv.length; index += 1) {
    const arg = argv[index];
    if (arg === '--root') {
      const value = argv[index + 1];
      if (!value) throw new Error('--root requires a directory');
      options.root = path.resolve(process.cwd(), value);
      index += 1;
    } else if (arg === '--help' || arg === '-h') {
      console.log('Usage: node scripts/check-backend-guardrails.mjs [--root <react-frontend-dir>]');
      process.exit(0);
    } else {
      throw new Error(`Unknown argument: ${arg}`);
    }
  }

  return options;
}

function toPosix(relativePath) {
  return relativePath.split(path.sep).join('/');
}

function readText(filePath) {
  return fs.readFileSync(filePath, 'utf8');
}

function checkPackageScripts(root, issues) {
  const packagePath = path.join(root, 'package.json');
  if (!fs.existsSync(packagePath)) {
    issues.push('package.json is missing');
    return;
  }

  const pkg = JSON.parse(readText(packagePath));
  const scripts = pkg.scripts ?? {};

  for (const scriptName of ['dev', 'build']) {
    const value = scripts[scriptName];
    if (!value) {
      issues.push(`package.json script "${scriptName}" is missing`);
      continue;
    }

    if (/VITE_BACKEND_TARGET\s*=\s*dotnet/i.test(value) || /--mode\s+dotnet/i.test(value)) {
      issues.push(`package.json script "${scriptName}" must remain Laravel-safe`);
    }
  }

  for (const scriptName of ['dev:dotnet', 'build:dotnet']) {
    const value = scripts[scriptName];
    if (value && !/VITE_BACKEND_TARGET\s*=\s*dotnet/i.test(value)) {
      issues.push(`package.json script "${scriptName}" must be explicit about VITE_BACKEND_TARGET=dotnet`);
    }
  }
}

function checkBackendTargetDefault(root, issues) {
  const targetPath = path.join(root, 'src', 'config', 'backendTarget.ts');
  if (!fs.existsSync(targetPath)) {
    issues.push('src/config/backendTarget.ts is missing');
    return;
  }

  const text = readText(targetPath);
  if (!/value\s*===\s*['"]dotnet['"]\s*\|\|\s*value\s*===\s*['"]laravel['"]/.test(text)) {
    issues.push('backendTarget normalizer must only accept dotnet or laravel explicitly');
  }

  if (!/\?\s*value\s*:\s*['"]laravel['"]/.test(text)) {
    issues.push('backendTarget normalizer must default invalid or missing values to laravel');
  }
}

function walk(dir) {
  if (!fs.existsSync(dir)) return [];
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (['__tests__', '__mocks__'].includes(entry.name)) continue;
      files.push(...walk(fullPath));
    } else if (entry.isFile() && /\.(ts|tsx)$/.test(entry.name) && !/\.(test|spec)\./.test(entry.name)) {
      files.push(fullPath);
    }
  }

  return files;
}

function checkFrontendConditionals(root, issues) {
  const scanRoots = [
    path.join(root, 'src', 'pages'),
    path.join(root, 'src', 'components'),
  ];
  const matches = [];

  for (const scanRoot of scanRoots) {
    for (const filePath of walk(scanRoot)) {
      const text = readText(filePath);
      for (const pattern of forbiddenFrontendPatterns) {
        if (pattern.test(text)) {
          matches.push(toPosix(path.relative(root, filePath)));
          break;
        }
      }
    }
  }

  if (matches.length > 0) {
    issues.push(`backend-specific frontend conditionals found in pages/components: ${matches.sort().join(', ')}`);
  }
}

function main() {
  const options = parseArgs(process.argv.slice(2));
  const issues = [];

  checkPackageScripts(options.root, issues);
  checkBackendTargetDefault(options.root, issues);
  checkFrontendConditionals(options.root, issues);

  if (issues.length > 0) {
    console.error('Backend guardrails failed:');
    for (const issue of issues) console.error(`- ${issue}`);
    process.exit(1);
  }

  console.log('Backend guardrails OK: Laravel remains default and pages/components are backend-neutral.');
}

main();
