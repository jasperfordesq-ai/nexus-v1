#!/usr/bin/env node
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = process.cwd();
const issues = [];

function read(relativePath) {
  return fs.readFileSync(path.join(root, relativePath), 'utf8');
}

function readJson(relativePath) {
  return JSON.parse(read(relativePath));
}

function addIssue(message) {
  issues.push(message);
}

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function assertContains(relativePath, pattern, description) {
  const text = read(relativePath);
  const matches = pattern instanceof RegExp ? pattern.test(text) : text.includes(pattern);

  if (!matches) {
    addIssue(`${relativePath}: missing ${description}`);
  }
}

function walk(dir) {
  if (!fs.existsSync(dir)) return [];

  const entries = fs.readdirSync(dir, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      files.push(...walk(fullPath));
    } else if (entry.isFile()) {
      files.push(fullPath);
    }
  }

  return files;
}

function toPosix(relativePath) {
  return relativePath.split(path.sep).join('/');
}

const version = read('VERSION').trim();

if (!/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/.test(version)) {
  addIssue(`VERSION: "${version}" is not a semantic version`);
}

const composerVersion = readJson('composer.json').version;
if (composerVersion !== version) {
  addIssue(`composer.json: version is ${composerVersion}, expected ${version}`);
}

const frontendVersion = readJson('react-frontend/package.json').version;
if (frontendVersion !== version) {
  addIssue(`react-frontend/package.json: version is ${frontendVersion}, expected ${version}`);
}

const versionEscaped = escapeRegExp(version);

assertContains('config/app.php', `'version' => env('APP_VERSION', '${version}')`, 'config app.version fallback');
assertContains('README.md', new RegExp(`Version ${versionEscaped}\\b`), 'current platform version in README headline');
assertContains('README.md', new RegExp(`Project NEXUS V${versionEscaped}\\b`), 'current Project NEXUS version in README headline');
assertContains('README.md', new RegExp(`version ${versionEscaped}\\b`, 'i'), 'current platform version in README status');
assertContains('CHANGELOG.md', new RegExp(`^## \\[${versionEscaped}\\] - `, 'm'), 'current version changelog section');
assertContains('CHANGELOG.md', `[Unreleased]: https://github.com/jasperfordesq-ai/nexus-v1/compare/v${version}...HEAD`, 'Unreleased compare link');
assertContains('react-frontend/src/config/releaseStatus.ts', `Generally Available (v${version})`, 'release status label');

const changelog = read('CHANGELOG.md');
const bundledChangelogPath = path.join(root, 'react-frontend/public/changelog.md');
if (fs.existsSync(bundledChangelogPath)) {
  const bundledChangelog = read('react-frontend/public/changelog.md');
  if (changelog !== bundledChangelog) {
    addIssue('react-frontend/public/changelog.md: does not match root CHANGELOG.md; run npm --prefix react-frontend run copy-changelog');
  }
}

const publicCollateralFiles = [
  'README.md',
  ...walk(path.join(root, 'docs-public'))
    .filter((file) => ['.md', '.html'].includes(path.extname(file).toLowerCase()))
    .map((file) => toPosix(path.relative(root, file))),
];

const staleCurrentVersionPatterns = [
  /\bProject NEXUS V(\d+\.\d+\.\d+)\b/g,
  /\bWhat's Inside V(\d+\.\d+\.\d+)\b/g,
  /\bV(\d+\.\d+\.\d+) ships\b/g,
  /\bavailable in V(\d+\.\d+\.\d+)\b/g,
  /\bv(\d+\.\d+\.\d+)\. For the most current implementation\b/g,
  /\*\*Version:\*\* (\d+\.\d+\.\d+)\b/g,
  /\bV(\d+\.\d+\.\d+) today\b/g,
];

for (const relativePath of publicCollateralFiles) {
  if (!fs.existsSync(path.join(root, relativePath))) continue;
  const text = read(relativePath);

  for (const pattern of staleCurrentVersionPatterns) {
    let match;
    while ((match = pattern.exec(text)) !== null) {
      if (match[1] !== version) {
        addIssue(`${relativePath}: current public version token is ${match[1]}, expected ${version}`);
      }
    }
  }
}

if (issues.length > 0) {
  console.error('Version consistency check failed:');
  for (const issue of issues.sort()) {
    console.error(`- ${issue}`);
  }
  console.error('');
  console.error('Update VERSION, composer.json, react-frontend/package.json, README.md, CHANGELOG.md, releaseStatus.ts, and current public collateral together.');
  console.error('Native mobile app versions, legal document versions, API versions, and deployment build IDs are intentionally separate.');
  process.exit(1);
}

console.log(`Version consistency OK (${version}).`);
