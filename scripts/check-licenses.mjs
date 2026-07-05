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

const PERMISSIVE = /^(MIT|BSD|Apache|ISC|0BSD|Unlicense|WTFPL|Zlib|CC0|Python|Unicode|BlueOak|MIT-0)/i;
const WEAK = /^(MPL|LGPL|EPL|CDDL|Ms-PL)/i;
const USE_RESTRICTED = /Hippocratic|SSPL-restricted/i;
const STRONG = /^(GPL|AGPL|SSPL|OSL|EUPL|CeCILL|GNU)/i;

// Strong-copyleft deps we knowingly ship today, with tracking notes. Anything
// NOT listed here that is a hard blocker fails the gate.
const KNOWN_EXCEPTIONS = {
  'joomla/string':
    'GPL-2.0-or-later, pulled ONLY by wamania/php-stemmer (search stemming). ' +
    'Blocks a fully-proprietary closed-source sale until removed — remediation: ' +
    'upgrade/replace wamania/php-stemmer (newer majors drop joomla/string). ' +
    'See .local-docs-archive licensing finding.',
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
    return Object.entries(data).map(([id, v]) => ({
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
for (const p of all) {
  const kind = classifyLicense(p.license);
  if (kind === 'strong') {
    if (KNOWN_EXCEPTIONS[p.name]) warnings.push(`(known) ${p.name} — ${p.license} — ${KNOWN_EXCEPTIONS[p.name]}`);
    else blockers.push(`${p.name}@${p.version} — ${p.license} (${p.ecosystem})`);
  } else if (kind === 'weak' || kind === 'restricted' || kind === 'unknown') {
    warnings.push(`${kind}: ${p.name} — ${p.license} (${p.ecosystem})`);
  }
}

console.log(`\nDependency licence audit — composer prod: ${composer.length}, npm prod: ${npm ? npm.length : 'skipped'}`);
console.log(`  blockers: ${blockers.length}, warnings: ${warnings.length}`);
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
