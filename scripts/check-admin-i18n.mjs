// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * check-admin-i18n.mjs — Verify that admin React translation keys exist.
 *
 * Scans react-frontend/src/admin for useTranslation(...) usage and validates
 * string-literal t(...) keys against the matching English locale namespace.
 * Also expands a small set of high-risk dynamic admin key families that have
 * regressed repeatedly (tenant features/modules and module registry labels).
 *
 * Exit code 0 = no missing admin keys detected.
 * Exit code 1 = one or more required admin keys are missing.
 *
 * This script intentionally uses only built-in Node modules so it can run in CI
 * without installing dependencies.
 */

import { existsSync, readdirSync, readFileSync, statSync } from 'fs';
import { join, relative, resolve } from 'path';

const PROJECT_ROOT = process.cwd();
const ADMIN_SRC_DIR = join(PROJECT_ROOT, 'react-frontend', 'src', 'admin');
const LOCALES_DIR = join(PROJECT_ROOT, 'react-frontend', 'public', 'locales', 'en');
const MODULE_REGISTRY_FILE = join(
  PROJECT_ROOT,
  'react-frontend',
  'src',
  'admin',
  'modules',
  'config',
  'moduleRegistry.ts',
);

const MODULE_IDS = readModuleIds(MODULE_REGISTRY_FILE);
const LOCALE_KEYSETS = loadLocaleKeysets(LOCALES_DIR);
const SOURCE_FILES = walkFiles(ADMIN_SRC_DIR, /\.(ts|tsx)$/);

let missingCount = 0;
let warningCount = 0;

console.log('============================================================');
console.log('  Admin i18n Coverage Check');
console.log('============================================================');
console.log(`  Source files: ${SOURCE_FILES.length}`);
console.log(`  Locale namespaces: ${[...LOCALE_KEYSETS.keys()].sort().join(', ')}`);
console.log('');

for (const filePath of SOURCE_FILES) {
  const relPath = normalizeSlashes(relative(PROJECT_ROOT, filePath));
  const source = readFileSync(filePath, 'utf8');
  const aliasNamespaces = extractTranslationAliases(source);
  if (aliasNamespaces.size === 0) {
    continue;
  }

  const callsites = [];
  for (const [alias, namespace] of aliasNamespaces.entries()) {
    callsites.push(...extractTranslationCalls(source, alias, namespace));
  }

  for (const call of callsites) {
    const targetNamespace = call.overrideNamespace ?? call.namespace;
    if (!targetNamespace || !LOCALE_KEYSETS.has(targetNamespace)) {
      continue;
    }

    if (call.kind === 'literal') {
      if (!shouldAuditKey(relPath, targetNamespace, call.key)) {
        continue;
      }
      reportMissingIfNeeded(targetNamespace, call.key, relPath, call.line);
      continue;
    }

    if (!shouldAuditKey(relPath, targetNamespace, call.rawTemplate.split('${')[0])) {
      continue;
    }

    const expandedKeys = expandDynamicKey(call.rawTemplate, relPath, source);
    if (expandedKeys === null) {
      warningCount++;
      console.log(
        `[WARN] ${relPath}:${call.line} — unsupported dynamic key pattern in namespace "${targetNamespace}": ${call.rawTemplate}`,
      );
      continue;
    }

    for (const key of expandedKeys) {
      reportMissingIfNeeded(targetNamespace, key, relPath, call.line);
    }
  }
}

console.log('');
console.log('============================================================');
console.log(`  Missing keys: ${missingCount}`);
console.log(`  Warnings:     ${warningCount}`);
console.log('============================================================');

if (missingCount > 0) {
  console.log('');
  console.log('FAIL: Admin translation coverage gaps detected.');
  console.log('  Fix: Add the missing keys to react-frontend/public/locales/en/*.json');
  console.log('  Guardrail: This check blocks repeated admin raw-key regressions.');
  process.exit(1);
}

if (warningCount > 0) {
  console.log('');
  console.log('PASS WITH WARNINGS: All required admin keys were found, but some dynamic patterns');
  console.log('still need explicit audit support if you want 100% coverage.');
  process.exit(0);
}

console.log('');
console.log('PASS: All checked admin translation keys exist.');
process.exit(0);

function reportMissingIfNeeded(namespace, key, relPath, line) {
  const keyset = LOCALE_KEYSETS.get(namespace);
  if (!keyset || keyset.has(key)) {
    return;
  }
  missingCount++;
  console.log(`[MISSING] ${namespace}:${key}`);
  console.log(`  at ${relPath}:${line}`);
}

function shouldAuditKey(relPath, namespace, key) {
  const normalized = normalizeSlashes(relPath);
  const rules = [
    {
      file: '/src/admin/components/AdminSidebar.tsx',
      namespace: 'admin_nav',
      prefixes: [''],
    },
    {
      file: '/src/admin/components/BulkActionToolbar.tsx',
      namespace: 'admin',
      prefixes: ['bulk.'],
    },
    {
      file: '/src/admin/modules/content/LandingPageBuilder.tsx',
      namespace: 'admin',
      prefixes: ['content.'],
    },
    {
      file: '/src/admin/modules/config/TranslationConfig.tsx',
      namespace: 'admin',
      prefixes: ['config.translation_'],
    },
    {
      file: '/src/admin/modules/system/AdminSettings.tsx',
      namespace: 'admin',
      prefixes: ['system.'],
    },
    {
      file: '/src/admin/modules/system/CronJobLogs.tsx',
      namespace: 'admin',
      prefixes: ['system.'],
    },
    {
      file: '/src/admin/modules/system/CronJobSetup.tsx',
      namespace: 'admin',
      prefixes: ['system.', 'cron_setup.'],
    },
    {
      file: '/src/admin/modules/config/TenantFeatures.tsx',
      namespace: 'admin',
      prefixes: ['tenant_features.', 'config.tenant_features_', 'config.feature_', 'config.module_', 'config.failed_', 'config.cache_', 'config.job_', 'config.language_', 'config.enabled', 'config.disabled', 'config.label_default_language'],
    },
    {
      file: '/src/admin/modules/config/ModuleConfiguration.tsx',
      namespace: 'admin',
      prefixes: [
        'config.module_configuration_',
        'config.module_config_load_failed',
        'config.module_update_failed',
        'config.module_enabled',
        'config.module_disabled',
        'config.beta',
        'config.refresh',
        'config.search_modules',
        'config.filter_',
        'config.core_modules',
        'config.optional_features',
        'config.no_modules_match',
        'config.clear_filters',
      ],
    },
    {
      file: '/src/admin/modules/config/ModuleCard.tsx',
      namespace: 'admin',
      prefixes: [
        'config.option_count',
        'config.planned_count',
        'config.no_options',
        'config.core',
        'config.configure',
        'config.toggle_module',
      ],
    },
    {
      file: '/src/admin/modules/jobs/JobsAdmin.tsx',
      namespace: 'admin',
      prefixes: ['jobs.'],
    },
    {
      file: '/src/admin/modules/marketplace/MarketplaceAdmin.tsx',
      namespace: 'admin',
      prefixes: ['marketplace.'],
    },
    {
      file: '/src/admin/modules/marketplace/MarketplaceModerationPage.tsx',
      namespace: 'admin',
      prefixes: ['marketplace.'],
    },
    {
      file: '/src/admin/modules/marketplace/MarketplaceSellerAdmin.tsx',
      namespace: 'admin',
      prefixes: ['marketplace.'],
    },
  ];

  for (const rule of rules) {
    if (namespace !== rule.namespace) continue;
    if (!normalized.endsWith(rule.file)) continue;
    if (rule.prefixes.includes('')) return true;
    if (rule.prefixes.some((prefix) => key.startsWith(prefix))) {
      return true;
    }
  }

  return false;
}

function readModuleIds(filePath) {
  if (!existsSync(filePath)) {
    return [];
  }
  const source = readFileSync(filePath, 'utf8');
  const ids = new Set();
  const regex = /\bid:\s*'([^']+)'/g;
  let match;
  while ((match = regex.exec(source)) !== null) {
    ids.add(match[1]);
  }
  return [...ids].sort();
}

function loadLocaleKeysets(localeDir) {
  if (!existsSync(localeDir)) {
    throw new Error(`Locale directory not found: ${localeDir}`);
  }

  const keysets = new Map();
  for (const file of readdirSync(localeDir)) {
    if (!file.endsWith('.json')) continue;
    const namespace = file.replace(/\.json$/, '');
    const json = readJsonWithFallbacks(join(localeDir, file));
    keysets.set(namespace, new Set(flattenKeys(json)));
  }
  return keysets;
}

function readJsonWithFallbacks(filePath) {
  const buffer = readFileSync(filePath);
  const attempts = [buffer.toString('utf8'), buffer.toString('latin1')];
  for (const text of attempts) {
    try {
      return JSON.parse(text);
    } catch {
      // try next encoding
    }
  }
  throw new Error(`Failed to parse JSON locale file: ${filePath}`);
}

function flattenKeys(obj, prefix = '') {
  const keys = [];
  for (const [key, value] of Object.entries(obj)) {
    const fullKey = prefix ? `${prefix}.${key}` : key;
    if (value && typeof value === 'object' && !Array.isArray(value)) {
      keys.push(...flattenKeys(value, fullKey));
    } else {
      keys.push(fullKey);
    }
  }
  return keys;
}

function walkFiles(dir, pattern) {
  const files = [];
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const fullPath = join(dir, entry.name);
    if (entry.isDirectory()) {
      files.push(...walkFiles(fullPath, pattern));
      continue;
    }
    if (pattern.test(entry.name)) {
      files.push(fullPath);
    }
  }
  return files;
}

function extractTranslationAliases(source) {
  const aliases = new Map();
  const regex =
    /const\s*\{\s*t(?:\s*:\s*([A-Za-z_$][\w$]*))?\s*\}\s*=\s*useTranslation\(\s*(['"`])([^'"`]+)\2\s*\)/g;
  let match;
  while ((match = regex.exec(source)) !== null) {
    const alias = match[1] || 't';
    const namespace = match[3];
    aliases.set(alias, namespace);
  }
  return aliases;
}

function extractTranslationCalls(source, alias, namespace) {
  const calls = [];
  const needle = `${alias}(`;

  let index = 0;
  while (index < source.length) {
    const foundAt = source.indexOf(needle, index);
    if (foundAt === -1) break;

    const prevChar = foundAt > 0 ? source[foundAt - 1] : '';
    if (/[A-Za-z0-9_$\.]/.test(prevChar)) {
      index = foundAt + needle.length;
      continue;
    }

    const openParen = foundAt + alias.length;
    const closeParen = findMatchingBracket(source, openParen, '(', ')');
    if (closeParen === -1) break;

    const inner = source.slice(openParen + 1, closeParen);
    const args = splitTopLevelArgs(inner);
    const firstArg = args[0]?.trim();
    if (!firstArg) {
      index = closeParen + 1;
      continue;
    }

    const line = 1 + countNewlines(source.slice(0, foundAt));
    const overrideNamespace = extractNamespaceOverride(args[1] ?? '');
    const parsed = parseFirstArg(firstArg);
    if (parsed) {
      calls.push({ namespace, overrideNamespace, line, ...parsed });
    }

    index = closeParen + 1;
  }

  return dedupeCallsites(calls);
}

function dedupeCallsites(calls) {
  const seen = new Set();
  return calls.filter((call) => {
    const id =
      `${call.namespace}|${call.overrideNamespace || ''}|${call.line}|` +
      (call.kind === 'literal' ? call.key : call.rawTemplate);
    if (seen.has(id)) return false;
    seen.add(id);
    return true;
  });
}

function parseFirstArg(arg) {
  if (
    (arg.startsWith("'") && arg.endsWith("'")) ||
    (arg.startsWith('"') && arg.endsWith('"'))
  ) {
    return { kind: 'literal', key: unquote(arg) };
  }

  if (arg.startsWith('`') && arg.endsWith('`')) {
    const rawTemplate = arg.slice(1, -1);
    if (!rawTemplate.includes('${')) {
      return { kind: 'literal', key: rawTemplate };
    }
    return { kind: 'template', rawTemplate };
  }

  return null;
}

function unquote(value) {
  const quote = value[0];
  const inner = value.slice(1, -1);
  return inner.replaceAll(`\\${quote}`, quote);
}

function extractNamespaceOverride(arg) {
  const match = /\bns\s*:\s*(['"`])([^'"`]+)\1/.exec(arg);
  return match ? match[2] : null;
}

function expandDynamicKey(rawTemplate, relPath, source) {
  const providers = [
    {
      regex: /^config\.module_(name|desc)_\$\{module\.id\}$/,
      values: () => MODULE_IDS,
      build: (prefix, ids) => ids.map((id) => `${prefix}${id}`),
    },
    {
      regex: /^tenant_features\.(label|desc)_\$\{(?:feature|module|key)\}$/,
      values: () => MODULE_IDS,
      build: (prefix, ids) => ids.map((id) => `${prefix}${id}`),
    },
    {
      regex: /^jobs\.status_\$\{key\}$/,
      values: () => extractConstStringArray(source, 'STATUS_TAB_KEYS'),
      build: (prefix, values) => values.map((value) => `${prefix}${value}`),
    },
    {
      regex: /^jobs\.stage_\$\{stage\}$/,
      values: () => extractConstStringArray(source, 'APPLICATION_STAGE_KEYS'),
      build: (prefix, values) => values.map((value) => `${prefix}${value}`),
    },
    {
      regex: /^jobs\.pipeline_status_\$\{s\}$/,
      values: () =>
        unique([
          ...extractConstStringArray(source, 'INTERVIEW_STATUSES'),
          ...extractConstStringArray(source, 'OFFER_STATUSES'),
        ]).filter((value) => value !== 'all'),
      build: (prefix, values) => values.map((value) => `${prefix}${value}`),
    },
    {
      regex: /^system\.lang_\$\{code\}$/,
      values: () => extractConstStringArray(source, 'SUPPORTED_LOCALE_CODES'),
      build: (prefix, values) => values.map((value) => `${prefix}${value}`),
    },
    {
      regex: /^federation\.scope_\$\{key\.replace\(':', '_'\)\}$/,
      values: () => extractConstStringArray(source, 'SCOPE_KEYS'),
      build: (prefix, values) =>
        values.map((value) => `${prefix}${value.replaceAll(':', '_')}`),
    },
    {
      regex: /^federation\.webhook_\$\{key\.replace\('\.', '_'\)\}$/,
      values: () => extractConstStringArray(source, 'ALL_EVENT_KEYS'),
      build: (prefix, values) =>
        values.map((value) => `${prefix}${value.replaceAll('.', '_')}`),
    },
    {
      regex: /^safeguarding\.trigger_\$\{TRIGGER_I18N_KEY\[key\]\}_label$/,
      values: () => extractObjectValues(source, 'TRIGGER_I18N_KEY'),
      build: (prefix, values) => values.map((value) => `${prefix}${value}_label`),
    },
    {
      regex: /^safeguarding\.trigger_\$\{TRIGGER_I18N_KEY\[key\]\}_desc$/,
      values: () => extractObjectValues(source, 'TRIGGER_I18N_KEY'),
      build: (prefix, values) => values.map((value) => `${prefix}${value}_desc`),
    },
  ];

  for (const provider of providers) {
    const match = provider.regex.exec(rawTemplate);
    if (!match) continue;

    const values = provider.values();
    if (!values || values.length === 0) {
      return [];
    }

    const prefix = rawTemplate.split('${')[0];
    return provider.build(prefix, values);
  }

  if (rawTemplate === "visibility_rules.roles.${key || 'any'}") {
    return expandWithFallback(
      'visibility_rules.roles.',
      extractConstStringArray(source, 'ROLE_KEYS'),
      'any',
    );
  }

  if (rawTemplate === "visibility_rules.features.${key || 'none'}") {
    return expandWithFallback(
      'visibility_rules.features.',
      extractConstStringArray(source, 'FEATURE_KEYS'),
      'none',
    );
  }

  if (relPath.endsWith('TenantFeatures.tsx')) {
    if (rawTemplate.startsWith('tenant_features.label_')) {
      return MODULE_IDS.map((id) => `tenant_features.label_${id}`);
    }
    if (rawTemplate.startsWith('tenant_features.desc_')) {
      return MODULE_IDS.map((id) => `tenant_features.desc_${id}`);
    }
  }

  return null;
}

function expandWithFallback(prefix, values, fallback) {
  const result = new Set(values.map((value) => `${prefix}${value}`));
  result.add(`${prefix}${fallback}`);
  return [...result].sort();
}

function unique(values) {
  return [...new Set(values)];
}

function extractConstStringArray(source, constName) {
  const initializer = extractConstInitializer(source, constName);
  if (!initializer || !initializer.startsWith('[')) {
    return [];
  }

  const values = [];
  const regex = /'([^']+)'|"([^"]+)"/g;
  let match;
  while ((match = regex.exec(initializer)) !== null) {
    values.push(match[1] ?? match[2]);
  }
  return [...new Set(values)];
}

function extractObjectValues(source, constName) {
  const initializer = extractConstInitializer(source, constName);
  if (!initializer || !initializer.startsWith('{')) {
    return [];
  }

  const values = [];
  const regex = /:\s*'([^']+)'|:\s*"([^"]+)"/g;
  let match;
  while ((match = regex.exec(initializer)) !== null) {
    values.push(match[1] ?? match[2]);
  }
  return [...new Set(values)];
}

function extractConstInitializer(source, constName) {
  const patterns = [
    `const ${constName} =`,
    `const ${constName}:`,
    `export const ${constName} =`,
    `export const ${constName}:`,
  ];

  for (const pattern of patterns) {
    const start = source.indexOf(pattern);
    if (start === -1) continue;

    const equalsIndex = source.indexOf('=', start);
    if (equalsIndex === -1) continue;

    const openIndex = source.slice(equalsIndex + 1).search(/[\[{]/);
    if (openIndex === -1) continue;

    const absoluteOpenIndex = equalsIndex + 1 + openIndex;
    const opener = source[absoluteOpenIndex];
    const closer = opener === '[' ? ']' : '}';
    const closeIndex = findMatchingBracket(source, absoluteOpenIndex, opener, closer);
    if (closeIndex === -1) continue;

    return source.slice(absoluteOpenIndex, closeIndex + 1);
  }

  return null;
}

function splitTopLevelArgs(text) {
  const args = [];
  let current = '';
  let depthParen = 0;
  let depthBracket = 0;
  let depthBrace = 0;
  let quote = null;
  let templateDepth = 0;
  let escaped = false;

  for (let i = 0; i < text.length; i++) {
    const char = text[i];
    const next = text[i + 1];
    current += char;

    if (quote) {
      if (escaped) {
        escaped = false;
        continue;
      }
      if (char === '\\') {
        escaped = true;
        continue;
      }
      if (quote === '`' && char === '$' && next === '{') {
        templateDepth++;
        current += next;
        i++;
        continue;
      }
      if (quote === '`' && char === '}' && templateDepth > 0) {
        templateDepth--;
        continue;
      }
      if (char === quote && templateDepth === 0) {
        quote = null;
      }
      continue;
    }

    if (char === "'" || char === '"' || char === '`') {
      quote = char;
      continue;
    }
    if (char === '(') depthParen++;
    else if (char === ')') depthParen--;
    else if (char === '[') depthBracket++;
    else if (char === ']') depthBracket--;
    else if (char === '{') depthBrace++;
    else if (char === '}') depthBrace--;
    else if (
      char === ',' &&
      depthParen === 0 &&
      depthBracket === 0 &&
      depthBrace === 0
    ) {
      args.push(current.slice(0, -1));
      current = '';
    }
  }

  if (current.trim()) {
    args.push(current);
  }
  return args;
}

function findMatchingBracket(text, openIndex, opener, closer) {
  let depth = 0;
  let quote = null;
  let templateDepth = 0;
  let escaped = false;

  for (let i = openIndex; i < text.length; i++) {
    const char = text[i];
    const next = text[i + 1];

    if (quote) {
      if (escaped) {
        escaped = false;
        continue;
      }
      if (char === '\\') {
        escaped = true;
        continue;
      }
      if (quote === '`' && char === '$' && next === '{') {
        templateDepth++;
        i++;
        continue;
      }
      if (quote === '`' && char === '}' && templateDepth > 0) {
        templateDepth--;
        continue;
      }
      if (char === quote && templateDepth === 0) {
        quote = null;
      }
      continue;
    }

    if (char === "'" || char === '"' || char === '`') {
      quote = char;
      continue;
    }

    if (char === opener) {
      depth++;
    } else if (char === closer) {
      depth--;
      if (depth === 0) {
        return i;
      }
    }
  }

  return -1;
}

function countNewlines(text) {
  let count = 0;
  for (const char of text) {
    if (char === '\n') count++;
  }
  return count;
}

function normalizeSlashes(filePath) {
  return filePath.replaceAll('\\', '/');
}
