// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Produce an exact, machine-readable view of i18next-cli's locale status.
 *
 * The CLI deliberately exits non-zero for incomplete locales, and its public API
 * does not expose the generated report. This wrapper runs the installed CLI for
 * each configured secondary locale and parses only its stable detailed-report
 * markers. It can also materialise missing CLDR plural variants from an existing,
 * non-empty translation in the same target locale. It never translates genuine
 * non-plural gaps and never copies a primary-language value into another locale.
 *
 * Usage:
 *   node scripts/audit-i18n-status.mjs
 *   node scripts/audit-i18n-status.mjs --locales de,fr
 *   node scripts/audit-i18n-status.mjs --apply-reuse
 *   node scripts/audit-i18n-status.mjs --from-report ../.local-docs-archive/report.json --apply-reuse
 *   node scripts/audit-i18n-status.mjs --from-report ../.local-docs-archive/report.json --materialize-primary-from de --apply-reuse
 *   node scripts/audit-i18n-status.mjs --from-report ../.local-docs-archive/report.json --materialize-from de --target-locale ja --apply-reuse
 *   node scripts/audit-i18n-status.mjs --output ../.local-docs-archive/report.json
 *   node scripts/audit-i18n-status.mjs --revert-applied-report ../.local-docs-archive/report.json
 */

import { execFile } from 'node:child_process';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';
import { createJiti } from 'jiti';
import { findKeys } from 'i18next-cli';

const execFileAsync = promisify(execFile);
const scriptDirectory = dirname(fileURLToPath(import.meta.url));
const frontendRoot = resolve(scriptDirectory, '..');
const repositoryRoot = resolve(frontendRoot, '..');
const cliPath = resolve(frontendRoot, 'node_modules/i18next-cli/dist/esm/cli.js');
const configPath = resolve(frontendRoot, 'i18next.config.ts');
const pluralCategories = ['zero', 'one', 'two', 'few', 'many', 'other'];

function parseArguments(argv) {
  const options = {
    applyReuse: false,
    output: resolve(repositoryRoot, '.local-docs-archive/2026-07-10-react-i18n-status-detail.json'),
    localeFilter: null,
    fromReport: null,
    materializePrimaryFrom: null,
    materializeFrom: null,
    targetLocale: null,
    revertAppliedReport: null,
  };

  for (let index = 0; index < argv.length; index += 1) {
    const argument = argv[index];
    if (argument === '--apply-reuse' || argument === '--apply-plurals') {
      options.applyReuse = true;
    } else if (argument === '--output') {
      options.output = resolve(frontendRoot, argv[index + 1]);
      index += 1;
    } else if (argument === '--locales') {
      options.localeFilter = new Set(argv[index + 1].split(',').map((locale) => locale.trim()).filter(Boolean));
      index += 1;
    } else if (argument === '--from-report') {
      options.fromReport = resolve(frontendRoot, argv[index + 1]);
      index += 1;
    } else if (argument === '--materialize-primary-from') {
      options.materializePrimaryFrom = argv[index + 1];
      index += 1;
    } else if (argument === '--materialize-from') {
      options.materializeFrom = argv[index + 1];
      index += 1;
    } else if (argument === '--target-locale') {
      options.targetLocale = argv[index + 1];
      index += 1;
    } else if (argument === '--revert-applied-report') {
      options.revertAppliedReport = resolve(frontendRoot, argv[index + 1]);
      index += 1;
    } else {
      throw new Error(`Unknown argument: ${argument}`);
    }
  }

  return options;
}

function stripAnsi(value) {
  // eslint-disable-next-line no-control-regex
  return value.replace(/\u001B\[[0-?]*[ -/]*[@-~]/g, '');
}

async function runDetailedStatus(locale) {
  try {
    const { stdout } = await execFileAsync(
      process.execPath,
      [cliPath, 'status', locale, '--hide-translated'],
      {
        cwd: frontendRoot,
        encoding: 'utf8',
        env: { ...process.env, FORCE_COLOR: '0', NO_COLOR: '1' },
        maxBuffer: 64 * 1024 * 1024,
      },
    );
    return stdout;
  } catch (error) {
    // Incomplete status is the expected reason for exit code 1. Preserve stdout;
    // fail only when the CLI could not produce a detailed report at all.
    if (typeof error.stdout === 'string' && error.stdout.includes(`Key Status for "${locale}"`)) {
      return error.stdout;
    }
    throw error;
  }
}

function parseDetailedStatus(locale, rawOutput) {
  const output = stripAnsi(rawOutput);
  const records = [];
  let namespace = null;

  for (const rawLine of output.split(/\r?\n/u)) {
    const line = rawLine.trimEnd();
    const namespaceMatch = line.match(/^Namespace: (.+)$/u);
    if (namespaceMatch) {
      namespace = namespaceMatch[1];
      continue;
    }

    const keyMatch = line.match(/^\s*([✗~])\s+(.+?)\s+\((absent|untranslated)\)\s*$/u);
    if (keyMatch && namespace) {
      records.push({
        locale,
        namespace,
        key: keyMatch[2],
        state: keyMatch[3] === 'absent' ? 'absent' : 'empty',
      });
    }
  }

  const summaryPrefix = 'Summary: Found ';
  const summaryMarker = ` incomplete translations for "${locale}"`;
  const summaryLine = output
    .split(/\r?\n/u)
    .find((line) => line.startsWith(summaryPrefix) && line.includes(summaryMarker));
  const markerIndex = summaryLine?.indexOf(summaryMarker, summaryPrefix.length) ?? -1;
  const summaryCount = markerIndex > summaryPrefix.length
    ? summaryLine.slice(summaryPrefix.length, markerIndex)
    : null;
  const expectedCount = summaryCount && /^\d+$/u.test(summaryCount) ? Number(summaryCount) : 0;
  if (records.length !== expectedCount) {
    throw new Error(
      `Detailed status parse mismatch for ${locale}: parsed ${records.length}, CLI reported ${expectedCount}.`,
    );
  }

  return records;
}

function getNestedValue(object, dottedKey) {
  return dottedKey.split('.').reduce((value, segment) => (
    value && typeof value === 'object' ? value[segment] : undefined
  ), object);
}

function setNestedValue(object, dottedKey, value) {
  const segments = dottedKey.split('.');
  const finalSegment = segments.pop();
  let cursor = object;
  for (const segment of segments) {
    if (cursor[segment] !== undefined && (
      cursor[segment] === null
      || typeof cursor[segment] !== 'object'
      || Array.isArray(cursor[segment])
    )) {
      throw new Error(`Cannot materialise "${dottedKey}": scalar ancestor "${segment}" would be overwritten.`);
    }
    if (!cursor[segment]) {
      cursor[segment] = {};
    }
    cursor = cursor[segment];
  }
  cursor[finalSegment] = value;
}

function deleteNestedValue(object, dottedKey) {
  const segments = dottedKey.split('.');
  const ancestors = [];
  let cursor = object;
  for (const segment of segments.slice(0, -1)) {
    if (!cursor || typeof cursor !== 'object' || !(segment in cursor)) return false;
    ancestors.push([cursor, segment]);
    cursor = cursor[segment];
  }
  const finalSegment = segments.at(-1);
  if (!cursor || typeof cursor !== 'object' || !(finalSegment in cursor)) return false;
  delete cursor[finalSegment];
  for (const [parent, segment] of ancestors.reverse()) {
    const value = parent[segment];
    if (value && typeof value === 'object' && !Array.isArray(value) && Object.keys(value).length === 0) {
      delete parent[segment];
    } else {
      break;
    }
  }
  return true;
}

function classifyPluralKey(key) {
  const ordinalMatch = key.match(/^(.*)_ordinal_(zero|one|two|few|many|other)$/u);
  if (ordinalMatch) {
    return { baseKey: ordinalMatch[1], category: ordinalMatch[2], ordinal: true };
  }

  const cardinalMatch = key.match(/^(.*)_(zero|one|two|few|many|other)$/u);
  if (cardinalMatch) {
    return { baseKey: cardinalMatch[1], category: cardinalMatch[2], ordinal: false };
  }

  return null;
}

function nonEmptyString(value) {
  return typeof value === 'string' && value.trim().length > 0;
}

function findTargetLocaleSource(translations, plural) {
  const baseValue = getNestedValue(translations, plural.baseKey);
  if (nonEmptyString(baseValue)) {
    return { sourceKey: plural.baseKey, sourceValue: baseValue, sourceStrategy: 'target-locale-base' };
  }

  const prefix = plural.ordinal ? `${plural.baseKey}_ordinal_` : `${plural.baseKey}_`;
  const preferredCategories = [plural.category, 'other', 'many', 'few', 'two', 'one', 'zero'];
  for (const category of preferredCategories) {
    const candidateKey = `${prefix}${category}`;
    const candidateValue = getNestedValue(translations, candidateKey);
    if (nonEmptyString(candidateValue)) {
      return {
        sourceKey: candidateKey,
        sourceValue: candidateValue,
        sourceStrategy: category === plural.category ? 'target-locale-same-category' : 'target-locale-sibling',
      };
    }
  }

  return null;
}

async function loadNamespace(locale, namespace, cache) {
  const cacheKey = `${locale}:${namespace}`;
  if (!cache.has(cacheKey)) {
    const path = resolve(frontendRoot, `public/locales/${locale}/${namespace}.json`);
    const raw = await readFile(path, 'utf8');
    cache.set(cacheKey, { path, translations: JSON.parse(raw), dirty: false });
  }
  return cache.get(cacheKey);
}

async function loadAllNamespaces(locale, cache) {
  const localeDirectory = resolve(frontendRoot, `public/locales/${locale}`);
  const { readdir } = await import('node:fs/promises');
  const files = (await readdir(localeDirectory)).filter((file) => file.endsWith('.json')).sort();
  return Promise.all(files.map(async (file) => {
    const namespace = file.slice(0, -'.json'.length);
    return [namespace, await loadNamespace(locale, namespace, cache)];
  }));
}

function uniqueTranslation(candidates) {
  const byValue = new Map();
  for (const candidate of candidates) {
    if (!byValue.has(candidate.sourceValue)) {
      byValue.set(candidate.sourceValue, candidate);
    }
  }
  return byValue.size === 1 ? byValue.values().next().value : null;
}

function findCrossNamespaceExactSource(allNamespaces, targetNamespace, key) {
  const candidates = [];
  for (const [namespace, namespaceFile] of allNamespaces) {
    if (namespace === targetNamespace) continue;
    const sourceValue = getNestedValue(namespaceFile.translations, key);
    if (nonEmptyString(sourceValue)) {
      candidates.push({ sourceNamespace: namespace, sourceKey: key, sourceValue });
    }
  }
  return uniqueTranslation(candidates);
}

function findCrossNamespacePluralSource(allNamespaces, targetNamespace, plural) {
  const prefix = plural.ordinal ? `${plural.baseKey}_ordinal_` : `${plural.baseKey}_`;
  const priorities = [
    `${prefix}${plural.category}`,
    plural.baseKey,
    `${prefix}other`,
    `${prefix}many`,
    `${prefix}few`,
    `${prefix}two`,
    `${prefix}one`,
    `${prefix}zero`,
  ];

  for (const sourceKey of priorities) {
    const candidates = [];
    for (const [namespace, namespaceFile] of allNamespaces) {
      if (namespace === targetNamespace) continue;
      const sourceValue = getNestedValue(namespaceFile.translations, sourceKey);
      if (nonEmptyString(sourceValue)) {
        candidates.push({ sourceNamespace: namespace, sourceKey, sourceValue });
      }
    }
    const source = uniqueTranslation(candidates);
    if (source) return source;
  }
  return null;
}

async function classifyRecords(records) {
  const cache = new Map();
  const allNamespacesByLocale = new Map();
  const classified = [];

  for (const record of records) {
    if (!allNamespacesByLocale.has(record.locale)) {
      allNamespacesByLocale.set(record.locale, await loadAllNamespaces(record.locale, cache));
    }
    const allNamespaces = allNamespacesByLocale.get(record.locale);
    const plural = classifyPluralKey(record.key);
    if (!plural) {
      const crossNamespaceSource = findCrossNamespaceExactSource(allNamespaces, record.namespace, record.key);
      if (crossNamespaceSource) {
        classified.push({
          ...record,
          classification: 'cross-namespace-target',
          sourceNamespace: crossNamespaceSource.sourceNamespace,
          sourceKey: crossNamespaceSource.sourceKey,
        });
      } else {
        classified.push({ ...record, classification: 'nonplural' });
      }
      continue;
    }

    const namespaceFile = await loadNamespace(record.locale, record.namespace, cache);
    const source = findTargetLocaleSource(namespaceFile.translations, plural);
    if (!source) {
      const crossNamespaceSource = findCrossNamespacePluralSource(allNamespaces, record.namespace, plural);
      if (crossNamespaceSource) {
        classified.push({
          ...record,
          classification: 'cross-namespace-plural-variant',
          plural,
          sourceNamespace: crossNamespaceSource.sourceNamespace,
          sourceKey: crossNamespaceSource.sourceKey,
        });
      } else {
        classified.push({
          ...record,
          classification: 'untranslated-plural-family',
          plural,
        });
      }
      continue;
    }

    classified.push({
      ...record,
      classification: 'cldr-plural-variant',
      plural,
      sourceKey: source.sourceKey,
      sourceStrategy: source.sourceStrategy,
    });
  }

  return { classified, cache };
}

async function applyReusableTranslations(classified, cache) {
  const applied = [];

  for (const record of classified) {
    if (![
      'cldr-plural-variant',
      'cross-namespace-target',
      'cross-namespace-plural-variant',
    ].includes(record.classification)) {
      continue;
    }

    const namespaceFile = await loadNamespace(record.locale, record.namespace, cache);
    const existingTargetValue = getNestedValue(namespaceFile.translations, record.key);
    if (nonEmptyString(existingTargetValue)) {
      continue;
    }
    let source;
    if (record.classification === 'cldr-plural-variant') {
      source = findTargetLocaleSource(namespaceFile.translations, record.plural);
    } else {
      const sourceNamespaceFile = await loadNamespace(record.locale, record.sourceNamespace, cache);
      const sourceValue = getNestedValue(sourceNamespaceFile.translations, record.sourceKey);
      source = nonEmptyString(sourceValue)
        ? {
            sourceKey: record.sourceKey,
            sourceValue,
            sourceStrategy: record.classification,
          }
        : null;
    }
    if (!source) {
      throw new Error(`Target-locale source disappeared for ${record.locale}:${record.namespace}:${record.key}`);
    }
    setNestedValue(namespaceFile.translations, record.key, source.sourceValue);
    namespaceFile.dirty = true;
    applied.push({ ...record, sourceKey: source.sourceKey, sourceStrategy: source.sourceStrategy });
  }

  for (const namespaceFile of cache.values()) {
    if (namespaceFile.dirty) {
      await writeFile(namespaceFile.path, `${JSON.stringify(namespaceFile.translations, null, 2)}\n`, 'utf8');
    }
  }

  return applied;
}

function countBy(records, property) {
  return records.reduce((counts, record) => {
    const key = record[property];
    counts[key] = (counts[key] ?? 0) + 1;
    return counts;
  }, {});
}

async function main() {
  const options = parseArguments(process.argv.slice(2));
  if (options.revertAppliedReport) {
    const priorReport = JSON.parse(await readFile(options.revertAppliedReport, 'utf8'));
    const cache = new Map();
    let removed = 0;
    for (const record of priorReport.applied ?? []) {
      const namespaceFile = await loadNamespace(record.locale, record.namespace, cache);
      if (deleteNestedValue(namespaceFile.translations, record.key)) {
        namespaceFile.dirty = true;
        removed += 1;
      }
    }
    for (const namespaceFile of cache.values()) {
      if (namespaceFile.dirty) {
        await writeFile(namespaceFile.path, `${JSON.stringify(namespaceFile.translations, null, 2)}\n`, 'utf8');
      }
    }
    console.log(`Removed ${removed} entries recorded in ${options.revertAppliedReport}`);
    return;
  }
  const jiti = createJiti(frontendRoot, { interopDefault: false });
  const config = await jiti.import(configPath, { default: true });
  const primaryLanguage = config.extract?.primaryLanguage ?? config.locales[0];
  let locales = config.extract?.secondaryLanguages ?? config.locales.filter((locale) => locale !== primaryLanguage);
  if (options.materializePrimaryFrom) {
    options.materializeFrom = options.materializePrimaryFrom;
    options.targetLocale = primaryLanguage;
  }
  if (options.materializeFrom) {
    if (!options.fromReport) {
      throw new Error('Materialization requires --from-report.');
    }
    if (!options.targetLocale) {
      throw new Error('--materialize-from requires --target-locale.');
    }
    locales = [options.targetLocale];
  }
  if (options.localeFilter) {
    locales = locales.filter((locale) => options.localeFilter.has(locale));
  }

  let allRecords = [];
  if (options.fromReport) {
    const priorReport = JSON.parse(await readFile(options.fromReport, 'utf8'));
    if (options.materializeFrom) {
      const { allKeys } = await findKeys(config);
      const currentExtractedKeys = new Set(
        [...allKeys.values()].map((record) => `${record.ns ?? config.extract?.defaultNS ?? 'common'}:${record.key}`),
      );
      allRecords = priorReport.records
        .filter((record) => record.locale === options.materializeFrom)
        .filter((record) => currentExtractedKeys.has(`${record.namespace}:${record.key}`))
        .map(({ namespace, key, state }) => ({ locale: options.targetLocale, namespace, key, state }));
    } else {
      allRecords = priorReport.records
        .filter((record) => locales.includes(record.locale))
        .map(({ locale, namespace, key, state }) => ({ locale, namespace, key, state }));
    }
    console.log(`Loaded ${allRecords.length} incomplete records from ${options.fromReport}`);
  } else {
    for (const locale of locales) {
      process.stdout.write(`Auditing ${locale}... `);
      const rawOutput = await runDetailedStatus(locale);
      const records = parseDetailedStatus(locale, rawOutput);
      allRecords.push(...records);
      process.stdout.write(`${records.length} incomplete\n`);
    }
  }

  const { classified, cache } = await classifyRecords(allRecords);
  const applied = options.applyReuse ? await applyReusableTranslations(classified, cache) : [];
  const report = {
    generatedAt: new Date().toISOString(),
    source: 'installed i18next-cli detailed status output',
    primaryLanguage,
    locales,
    totals: {
      incomplete: classified.length,
      byState: countBy(classified, 'state'),
      byClassification: countBy(classified, 'classification'),
      appliedPluralVariants: applied.length,
    },
    localeSummaries: Object.fromEntries(locales.map((locale) => {
      const localeRecords = classified.filter((record) => record.locale === locale);
      return [locale, {
        incomplete: localeRecords.length,
        byState: countBy(localeRecords, 'state'),
        byClassification: countBy(localeRecords, 'classification'),
        namespaces: countBy(localeRecords, 'namespace'),
      }];
    })),
    records: classified,
    applied,
  };

  await mkdir(dirname(options.output), { recursive: true });
  await writeFile(options.output, `${JSON.stringify(report, null, 2)}\n`, 'utf8');
  console.log(`Wrote ${options.output}`);
  console.log(JSON.stringify(report.totals, null, 2));
}

await main();
