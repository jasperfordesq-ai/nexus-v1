// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * check-licenses.mjs — dependency-licence audit + copyleft gate + inventory.
 *
 * WHY: Project NEXUS is AGPL-3.0 (public), but the owner may also grant a
 * separate proprietary/closed-source licence (dual-licensing his own code). A
 * proprietary sale is only clean if NO dependency in the distributed tree is
 * STRONG copyleft (GPL/AGPL/SSPL/OSL) that we don't own. This script proves
 * that, documents where every dependency lives, and fails CI if a NEW strong-
 * copyleft dependency appears.
 *
 * WHAT IT CHECKS:
 *  - PHP: composer.lock `packages` (production) — deterministic, offline.
 *  - JS:  react-frontend production tree via `license-checker` IF node_modules
 *         is present (best-effort; skipped with a warning otherwise).
 *
 * CLASSIFICATION (per licence option; packages with an OR-list pass if ANY
 * option is acceptable — you elect the permissive one):
 *  - permissive          → no obligation (MIT/BSD/Apache/ISC/0BSD/Unlicense/…)
 *  - weak/file-copyleft   → usable in proprietary with conditions (MPL/LGPL/EPL/CDDL)
 *  - use-restricted       → non-copyleft but field-of-use terms (Hippocratic)
 *  - strong-copyleft      → BLOCKS proprietary closed-source (GPL/AGPL/SSPL/OSL/EUPL/CeCILL)
 *
 * A package is a HARD BLOCKER only when EVERY licence option is strong-copyleft
 * and it is not in KNOWN_EXCEPTIONS. Weak/use-restricted are reported as WARN.
 *
 * USAGE:
 *   node scripts/check-licenses.mjs            # audit + gate (exit 1 on blocker)
 *   node scripts/check-licenses.mjs --write    # also (re)generate THIRD_PARTY_LICENSES.md
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { execSync } from 'child_process';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const WRITE = process.argv.includes('--write');

// Match complete SPDX-like licence tokens. In particular, do not treat the
// metadata sentinel `UNLICENSED` as the permissive `Unlicense` licence.
const PERMISSIVE = /^(?:MIT(?:\*|-0)?|BSD(?:-\d-Clause)?|Apache-\d+(?:\.\d+)?|ISC|0BSD|Unlicense|WTFPL|Zlib|CC0-\d+(?:\.\d+)?|Python-\d+(?:\.\d+)?|Unicode(?:-[\w.-]+)?|BlueOak-\d+(?:\.\d+)*)(?:$|\s+AND\b)/i;
const WEAK = /^(MPL|LGPL|EPL|CDDL|Ms-PL)/i;
const USE_RESTRICTED = /Hippocratic|SSPL-restricted/i;
const STRONG = /^(GPL|AGPL|SSPL|OSL|EUPL|CeCILL|GNU)/i;

// Strong-copyleft deps we knowingly ship today, with tracking notes. Anything
// NOT listed here that is a hard blocker fails the gate. Currently EMPTY — the
// production tree is fully permissive after removing rubix/ml (an optional,
// class_exists-guarded accelerator) which transitively pulled
// wamania/php-stemmer → joomla/string (GPL-2.0). Keep it empty so any new
// strong-copyleft dependency fails CI.
const KNOWN_EXCEPTIONS = {};

// Non-permissive dependencies whose exact licence expression has been
// reviewed and documented in THIRD_PARTY_NOTICES.md. These are reported
// separately from unresolved warnings; a licence metadata change stops
// matching and returns the package to the warning/blocker path.
const REVIEWED_NON_PERMISSIVE = {
  'james-heinrich/getid3': {
    ecosystem: 'composer',
    license: 'GPL-1.0-or-later OR LGPL-3.0-only OR MPL-2.0',
    election: 'MPL-2.0; retain notices and provide source for covered files on distribution',
  },
};

function classifyOption(opt) {
  // Strip SPDX-expression parens/brackets so "(MIT AND Zlib)" classifies as MIT.
  const o = opt.trim().replace(/^[([]+|[)\]]+$/g, '').trim();
  if (STRONG.test(o)) return 'strong';
  if (WEAK.test(o)) return 'weak';
  if (USE_RESTRICTED.test(o)) return 'restricted';
  if (PERMISSIVE.test(o)) return 'permissive';
  return 'unknown';
}

/** Best acceptable classification across an OR-list (you elect the best option). */
function classifyLicense(license) {
  const opts = String(license).split(/\s+OR\s+|\s*\/\s*/i).filter(Boolean);
  if (!opts.length) return 'unknown';
  const kinds = opts.map(classifyOption);
  if (kinds.includes('permissive')) return 'permissive';
  if (kinds.includes('weak')) return 'weak';
  if (kinds.includes('restricted')) return 'restricted';
  if (kinds.includes('unknown')) return 'unknown';
  return 'strong';
}

function readComposerProd() {
  const lockPath = path.join(ROOT, 'composer.lock');
  if (!fs.existsSync(lockPath)) return [];
  const lock = JSON.parse(fs.readFileSync(lockPath, 'utf8'));
  return (lock.packages || []).map((p) => ({
    ecosystem: 'composer',
    name: p.name,
    version: p.version,
    license: (p.license || []).join(' OR ') || 'UNSPECIFIED',
    url: (p.source && p.source.url) || p.homepage || '',
  }));
}

function readNpmProd() {
  const nm = path.join(ROOT, 'react-frontend', 'node_modules');
  if (!fs.existsSync(nm)) {
    console.warn('⚠ react-frontend/node_modules absent — skipping npm licence check (run `npm ci` first).');
    return null;
  }
  try {
    const out = execSync('npx --yes license-checker --production --json', {
      cwd: path.join(ROOT, 'react-frontend'),
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'ignore'],
      maxBuffer: 64 * 1024 * 1024,
    });
    const data = JSON.parse(out);
    const frontendPackage = JSON.parse(fs.readFileSync(path.join(ROOT, 'react-frontend', 'package.json'), 'utf8'));
    const frontendPackageId = `${frontendPackage.name}@${frontendPackage.version}`;
    return Object.entries(data).filter(([id]) => id !== frontendPackageId).map(([id, v]) => ({
      ecosystem: 'npm',
      name: id,
      version: '',
      license: String(v.licenses || 'UNSPECIFIED'),
      url: v.repository || '',
    }));
  } catch (e) {
    console.warn('⚠ license-checker failed — skipping npm licence check:', e.message.split('\n')[0]);
    return null;
  }
}

const composer = readComposerProd();
const npm = readNpmProd();
const all = [...composer, ...(npm || [])];

const blockers = [];
const warnings = [];
const reviewed = [];
for (const p of all) {
  const kind = classifyLicense(p.license);
  if (kind === 'strong') {
    if (KNOWN_EXCEPTIONS[p.name]) warnings.push(`(known) ${p.name} — ${p.license} — ${KNOWN_EXCEPTIONS[p.name]}`);
    else blockers.push(`${p.name}@${p.version} — ${p.license} (${p.ecosystem})`);
  } else if (kind === 'weak' || kind === 'restricted' || kind === 'unknown') {
    const review = REVIEWED_NON_PERMISSIVE[p.name];
    if (review && review.ecosystem === p.ecosystem && review.license === p.license) {
      reviewed.push(`${p.name} — ${review.election}`);
    } else {
      warnings.push(`${kind}: ${p.name} — ${p.license} (${p.ecosystem})`);
    }
  }
}

console.log(`\nDependency licence audit — composer prod: ${composer.length}, npm prod: ${npm ? npm.length : 'skipped'}`);
console.log(`  blockers: ${blockers.length}, warnings: ${warnings.length}, reviewed non-permissive: ${reviewed.length}`);
if (reviewed.length) {
  console.log('\nREVIEWED (exact licence expression pinned; obligations documented):');
  reviewed.forEach((entry) => console.log('  - ' + entry));
}
if (warnings.length) {
  console.log('\nWARN (weak/use-restricted/known-exception — review for a proprietary sale):');
  warnings.forEach((w) => console.log('  - ' + w));
}
if (blockers.length) {
  console.log('\n🔴 BLOCKERS (strong copyleft, not in KNOWN_EXCEPTIONS — block a proprietary closed-source sale):');
  blockers.forEach((b) => console.log('  - ' + b));
}

if (WRITE) {
  const rows = all
    .slice()
    .sort((a, b) => a.ecosystem.localeCompare(b.ecosystem) || a.name.localeCompare(b.name))
    .map((p) => `| ${p.name} | ${p.version || '—'} | ${p.license} | ${p.ecosystem} | ${p.url} |`)
    .join('\n');
  const md = `<!-- GENERATED by scripts/check-licenses.mjs --write — do not hand-edit. -->\n`
    + `# Third-Party Licence Inventory (production dependencies)\n\n`
    + `Regenerate with \`node scripts/check-licenses.mjs --write\`. Summary and human-facing\n`
    + `attribution live in [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).\n\n`
    + `composer prod: ${composer.length} · npm prod: ${npm ? npm.length : 'not captured (no node_modules)'}\n\n`
    + `| Package | Version | Licence | Ecosystem | Source |\n|---|---|---|---|---|\n${rows}\n`;
  fs.writeFileSync(path.join(ROOT, 'THIRD_PARTY_LICENSES.md'), md);
  console.log('\n✍ wrote THIRD_PARTY_LICENSES.md');
}

process.exit(blockers.length ? 1 : 0);
