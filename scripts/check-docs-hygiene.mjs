#!/usr/bin/env node
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import process from 'node:process';

const root = process.cwd();
const docsDir = path.join(root, 'docs');
const docsPublicDir = path.join(root, 'docs-public');
const docsIndex = path.join(docsDir, 'README.md');
const docsPublicIndex = path.join(docsPublicDir, 'README.md');
const maxDocBytes = 80 * 1024;

const allowedExtensions = new Set(['.md']);
const blockedDirectories = new Set([
  'archive',
  'audits',
  'council-pilot',
  'handoffs',
  'legal',
  'parity-briefs',
  'plans',
  'pricing',
  'prompts',
  'reports',
  'superpowers',
]);

const blockedNamePattern = /(^|[-_.])(allnighter|audit|draft|handoff|morning|overnight|plan|prompt|report|scratch|tmp|tracker)([-_.]|$)/i;
const blockedContentPatterns = [
  { pattern: /C:\\ssh/i, reason: 'machine-local SSH key path' },
  { pattern: /C:\\Users\\/i, reason: 'machine-local Windows user path' },
  { pattern: /\b20\.224\.\d{1,3}\.\d{1,3}\b/, reason: 'production IP address' },
  { pattern: /funding@hour-timebank\.ie/i, reason: 'private contact email' },
  { pattern: /jasper@hour-timebank\.ie/i, reason: 'private contact email' },
  { pattern: /\bDB_PASS=\$\(/i, reason: 'production credential extraction snippet' },
  { pattern: /docs\/?\s+is\s+gitignored/i, reason: 'stale docs gitignore statement' },
  { pattern: /private,\s*dev machine/i, reason: 'private-dev-machine label in public docs' },
  { pattern: /PRODUCTION-READINESS/i, reason: 'reference to archived production-readiness notes' },
  { pattern: /docs\/(ROADMAP|LOCAL_DEV_SETUP|PHP_CONVENTIONS|API_REFERENCE|REGRESSION_PREVENTION|QA_AUDIT_AND_TEST_PLAN)\.md/i, reason: 'reference to retired docs' },
  { pattern: /\bNexus\\/i, reason: 'retired Nexus PHP namespace' },
  { pattern: /\bsrc\/(Services|Core|Controllers)\//i, reason: 'retired top-level PHP src path' },
  { pattern: /\bReact 18\b/i, reason: 'stale React version' },
  { pattern: /\bFramer Motion\b/i, reason: 'removed animation dependency' },
  { pattern: /\bHeroUI v2\b/i, reason: 'stale HeroUI version' },
  { pattern: /\bnginx\b/i, reason: 'stale web-server claim; Project NEXUS production uses Apache' },
  { pattern: /confidential and intended for/i, reason: 'confidentiality notice in public documentation' },
];

const secretPatterns = [
  { pattern: /\b[A-Z0-9_]*(?:PASSWORD|API_KEY|SECRET|TOKEN)[A-Z0-9_]*\s*=\s*(?!<|\$\{|your-|example|secret\b)[^\s'"]{8,}/i, reason: 'secret-like assignment' },
  { pattern: /-----BEGIN [A-Z ]+PRIVATE KEY-----/, reason: 'private key material' },
  { pattern: /\bAKIA[0-9A-Z]{16}\b/, reason: 'AWS access key shape' },
  { pattern: /\bghp_[A-Za-z0-9_]{20,}\b/, reason: 'GitHub token shape' },
  { pattern: /\bsk-[A-Za-z0-9]{20,}\b/, reason: 'API key shape' },
  { pattern: /\bxox[baprs]-[A-Za-z0-9-]{20,}\b/, reason: 'Slack token shape' },
];

const publicMarkdownFiles = [
  'README.md',
  'CONTRIBUTING.md',
  'CONTRIBUTOR_TERMS.md',
  'CONTRIBUTORS.md',
  'CODE_OF_CONDUCT.md',
  'SECURITY.md',
  'AGENTS.md',
  'LARAVEL_MIGRATION_PLAN.md',
  'accessible-frontend/README.md',
  'accessible-frontend/COMPONENTS.md',
  'mobile/README.md',
  'mobile/.maestro/README.md',
  'mobile/docs/DISTRIBUTION.md',
  'mobile/docs/NATIVE_UI_CONTRACT.md',
  'mobile/docs/SECURITY.md',
  'tests/README.md',
  'tests/Core/README.md',
  'e2e/README.md',
];

const issues = [];

function trackedFiles() {
  try {
    return new Set(execFileSync('git', ['ls-files'], { cwd: root, encoding: 'utf8' })
      .split(/\r?\n/)
      .filter(Boolean)
      .map((file) => toPosix(file)));
  } catch {
    return new Set();
  }
}

function toPosix(relativePath) {
  return relativePath.split(path.sep).join('/');
}

function addIssue(filePath, message) {
  issues.push(`${toPosix(path.relative(root, filePath))}: ${message}`);
}

function walk(dir) {
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

function checkDocsTree(files) {
  const indexText = fs.readFileSync(docsIndex, 'utf8');

  for (const filePath of files) {
    const relativeToDocs = toPosix(path.relative(docsDir, filePath));
    const extension = path.extname(filePath).toLowerCase();
    const parts = relativeToDocs.split('/');
    const stats = fs.statSync(filePath);

    if (!allowedExtensions.has(extension)) {
      addIssue(filePath, `only Markdown docs are allowed in docs/; found ${extension || 'no extension'}`);
    }

    for (const part of parts.slice(0, -1)) {
      if (blockedDirectories.has(part.toLowerCase())) {
        addIssue(filePath, `directory "${part}" is reserved for local/archive material, not public docs`);
      }
    }

    if (blockedNamePattern.test(path.basename(filePath))) {
      addIssue(filePath, 'filename looks like task output; put prompts, plans, handoffs, audits, reports, and trackers in .local-docs-archive/');
    }

    if (stats.size > maxDocBytes) {
      addIssue(filePath, `file is ${stats.size} bytes; public docs should stay under ${maxDocBytes} bytes or be split/curated`);
    }

    if (relativeToDocs !== 'README.md' && !indexText.includes(relativeToDocs)) {
      addIssue(filePath, 'not linked from docs/README.md; every public doc must be indexed');
    }

    const text = fs.readFileSync(filePath, 'utf8');
    for (const { pattern, reason } of [...blockedContentPatterns, ...secretPatterns]) {
      if (pattern.test(text)) {
        addIssue(filePath, `contains ${reason}`);
      }
    }
  }
}

function checkDocsPublicIndex(files) {
  if (!fs.existsSync(docsPublicIndex)) {
    addIssue(docsPublicIndex, 'docs-public/README.md is missing');
    return;
  }

  const indexText = fs.readFileSync(docsPublicIndex, 'utf8');
  for (const filePath of files) {
    const relativeToDocsPublic = toPosix(path.relative(docsPublicDir, filePath));
    const extension = path.extname(filePath).toLowerCase();

    if (relativeToDocsPublic === 'README.md') continue;
    if (!['.md', '.html', '.json', '.yml', '.yaml'].includes(extension)) continue;

    if (!indexText.includes(relativeToDocsPublic)) {
      addIssue(filePath, 'not linked from docs-public/README.md; public collateral must be indexed');
    }
  }
}

function extractMarkdownLinks(text) {
  const links = [];
  const regex = /\[[^\]]+\]\(([^)]+)\)/g;
  let match;

  while ((match = regex.exec(text)) !== null) {
    links.push(match[1].trim());
  }

  return links;
}

function isExternalOrAnchor(target) {
  return /^(https?:|mailto:|tel:|#)/i.test(target);
}

function checkLocalLinks(files) {
  for (const filePath of files) {
    if (!fs.existsSync(filePath)) continue;

    const text = fs.readFileSync(filePath, 'utf8');
    for (let target of extractMarkdownLinks(text)) {
      if (target.startsWith('<') && target.endsWith('>')) {
        target = target.slice(1, -1);
      }

      if (isExternalOrAnchor(target)) continue;

      const targetWithoutAnchor = target.split('#')[0];
      if (!targetWithoutAnchor || path.isAbsolute(targetWithoutAnchor)) continue;

      const resolved = path.resolve(path.dirname(filePath), targetWithoutAnchor);
      if (!resolved.startsWith(root + path.sep)) {
        addIssue(filePath, `link points outside repository: ${target}`);
        continue;
      }

      if (!fs.existsSync(resolved)) {
        addIssue(filePath, `broken local link: ${target}`);
      }
    }
  }
}

function checkTextPatterns(files) {
  for (const filePath of files) {
    const text = fs.readFileSync(filePath, 'utf8');
    for (const { pattern, reason } of [...blockedContentPatterns, ...secretPatterns]) {
      if (pattern.test(text)) {
        addIssue(filePath, `contains ${reason}`);
      }
    }
  }
}

function checkTrackedRootArtifacts(tracked) {
  const allowedRootMarkdown = new Set([
    'AGENTS.md',
    'CHANGELOG.md',
    'CLAUDE.md',
    'CODE_OF_CONDUCT.md',
    'CONTRIBUTING.md',
    'CONTRIBUTORS.md',
    'CONTRIBUTOR_TERMS.md',
    'GOVERNANCE.md',
    'LARAVEL_MIGRATION_PLAN.md',
    'README.md',
    'RELEASES.md',
    'SECURITY.md',
    'SUPPORT.md',
  ]);

  for (const relativePath of tracked) {
    if (!fs.existsSync(path.join(root, relativePath))) continue;
    if (relativePath.includes('/')) continue;
    const extension = path.extname(relativePath).toLowerCase();
    const basename = path.basename(relativePath);

    if (extension === '.md' && !allowedRootMarkdown.has(basename)) {
      addIssue(path.join(root, relativePath), 'root Markdown is not a maintained public entrypoint; move task output to docs/ or .local-docs-archive/');
    }

    if (extension === '.txt' && !['VERSION'].includes(basename)) {
      addIssue(path.join(root, relativePath), 'root text artifact is not maintained documentation; remove generated output from the public repo');
    }

    if (/(^|[-_.])(audit|handoff|plan|prompt|report|scratch|tmp|tracker)([-_.]|$)/i.test(basename)
      && !allowedRootMarkdown.has(basename)) {
      addIssue(path.join(root, relativePath), 'tracked root task artifact should not be public documentation');
    }
  }
}

if (!fs.existsSync(docsDir)) {
  console.error('docs/ directory is missing.');
  process.exit(1);
}

if (!fs.existsSync(docsIndex)) {
  console.error('docs/README.md is missing.');
  process.exit(1);
}

const docsFiles = walk(docsDir);
const publicCollateralTextFiles = fs.existsSync(docsPublicDir)
  ? walk(docsPublicDir).filter((file) => ['.md', '.html', '.json', '.yml', '.yaml'].includes(path.extname(file).toLowerCase()))
  : [];
const tracked = trackedFiles();
const publicMarkdownPaths = publicMarkdownFiles
  .map((file) => path.join(root, file))
  .filter((file) => fs.existsSync(file));
const linkCheckFiles = [
  ...publicMarkdownPaths,
  ...docsFiles.filter((file) => path.extname(file).toLowerCase() === '.md'),
  ...publicCollateralTextFiles.filter((file) => path.extname(file).toLowerCase() === '.md'),
];

checkDocsTree(docsFiles);
checkDocsPublicIndex(publicCollateralTextFiles);
checkTextPatterns(publicCollateralTextFiles);
checkTextPatterns([
  path.join(root, 'NOTICE'),
  path.join(root, 'CONTRIBUTING.md'),
  path.join(root, 'CONTRIBUTOR_TERMS.md'),
  ...publicMarkdownPaths.filter((file) => !file.endsWith(`${path.sep}AGENTS.md`) && !file.endsWith(`${path.sep}CLAUDE.md`)),
]);
checkLocalLinks(linkCheckFiles);
checkTrackedRootArtifacts(tracked);

if (issues.length > 0) {
  console.error('Documentation hygiene check failed:');
  for (const issue of issues.sort()) {
    console.error(`- ${issue}`);
  }
  console.error('');
  console.error('Routine prompts, scratch plans, handoffs, audits, generated reports, PDFs, and private notes belong in .local-docs-archive/, not docs/.');
  process.exit(1);
}

console.log(`Documentation hygiene OK (${docsFiles.length} docs files, ${publicCollateralTextFiles.length} public collateral files checked).`);
