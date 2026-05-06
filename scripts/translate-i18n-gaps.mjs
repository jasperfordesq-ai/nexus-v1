// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * translate-i18n-gaps.mjs
 *
 * Translates missing / English-fallback strings in all non-EN locale files.
 * Supports DeepL (recommended) or OpenAI as translation backend.
 *
 * Usage — DeepL (recommended, free tier 500k chars/month):
 *   DEEPL_API_KEY=your_key node scripts/translate-i18n-gaps.mjs
 *   Get a free key at: https://www.deepl.com/pro-api
 *
 * Usage — OpenAI (uses OPENAI_API_KEY from .env, GPT-4o-mini):
 *   OPENAI_API_KEY=your_key node scripts/translate-i18n-gaps.mjs
 *   (or set USE_OPENAI=1 if both keys are present to prefer OpenAI)
 *
 * Usage — Dry run / audit:
 *   node scripts/translate-i18n-gaps.mjs --summary
 *   DEEPL_API_KEY=x node scripts/translate-i18n-gaps.mjs --dry-run
 *
 * Flags:
 *   --dry-run          Print what would be translated without making changes
 *   --namespace <ns>   Only process files matching <ns> (e.g. --namespace emails)
 *   --lang <code>      Only process one language (e.g. --lang de)
 *   --missing-only     Only translate absent keys, not existing English fallback values
 *   --force            Re-translate strings even if they already differ from EN
 *   --include-simple   Translate short/simple strings too (useful for missing UI labels)
 *   --google           Use Google's public translate endpoint when no API key is available
 *   --summary          Print a summary of gaps before translating
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const LOCALES_DIR = path.resolve(__dirname, '../react-frontend/public/locales');
const ENV_PATH = path.resolve(__dirname, '../.env');

if (fs.existsSync(ENV_PATH)) {
  const envLines = fs.readFileSync(ENV_PATH, 'utf8').split(/\r?\n/);
  for (const line of envLines) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;

    const equalsIndex = trimmed.indexOf('=');
    if (equalsIndex === -1) continue;

    const key = trimmed.slice(0, equalsIndex).trim();
    const rawValue = trimmed.slice(equalsIndex + 1).trim();
    if (!key || process.env[key] !== undefined) continue;

    const quoted =
      (rawValue.startsWith('"') && rawValue.endsWith('"')) ||
      (rawValue.startsWith("'") && rawValue.endsWith("'"));
    process.env[key] = quoted ? rawValue.slice(1, -1) : rawValue;
  }
}

// ── Config ───────────────────────────────────────────────────────────────────

const DEEPL_KEY = process.env.DEEPL_API_KEY;
const OPENAI_KEY = process.env.OPENAI_API_KEY;
const USE_OPENAI = process.env.USE_OPENAI === '1' || (!DEEPL_KEY && !!OPENAI_KEY);

// DeepL: free tier uses api-free.deepl.com; paid uses api.deepl.com
const DEEPL_URL = DEEPL_KEY?.endsWith(':fx')
  ? 'https://api-free.deepl.com/v2/translate'
  : 'https://api.deepl.com/v2/translate';

// DeepL language codes for our supported languages.
// Irish (ga) falls back to OpenAI because DeepL does not support it.
const LANG_MAP = {
  de: 'DE',
  fr: 'FR',
  it: 'IT',
  pt: 'PT',
  es: 'ES',
  nl: 'NL',
  pl: 'PL',
  ja: 'JA',
  ar: 'AR',
};

const SUPPORTED_LANGUAGES = ['de', 'fr', 'it', 'pt', 'es', 'nl', 'pl', 'ja', 'ar', 'ga'];

// Admin-only namespaces — intentionally English-only, skip translation
const SKIP_NAMESPACES = new Set([
  'admin.json',
  'admin_dashboard.json',
  'admin_nav.json',
  'admin.php',
  'admin_dashboard.php',
  'admin_nav.php',
  'api.php',
  'api_controllers_1.json',
  'api_controllers_2.json',
  'api_controllers_3.json',
  'super_admin.json',
]);

// Strings matching these patterns are NOT translated (URLs, placeholders, etc.)
const NO_TRANSLATE_PATTERNS = [
  /^https?:\/\//,           // URLs
  /^\{\{.*\}\}$/,           // pure Handlebars templates
  /^[a-zA-Z0-9_]+$/,       // single words / identifiers (likely code values)
  /^\d+$/,                  // pure numbers
  /^[A-Z_]+$/,              // ALL_CAPS constants
];

// ── CLI args ─────────────────────────────────────────────────────────────────

const args = process.argv.slice(2);
const DRY_RUN = args.includes('--dry-run');
const MISSING_ONLY = args.includes('--missing-only');
const FORCE = args.includes('--force');
const INCLUDE_SIMPLE = args.includes('--include-simple');
const USE_GOOGLE = args.includes('--google');
const nsFilter = args.includes('--namespace') ? args[args.indexOf('--namespace') + 1] : null;
const langFilter = args.includes('--lang') ? args[args.indexOf('--lang') + 1] : null;
const SUMMARY_ONLY = args.includes('--summary');

// ── Helpers ──────────────────────────────────────────────────────────────────

function flattenKeys(obj, prefix = '') {
  const result = {};
  for (const [k, v] of Object.entries(obj)) {
    const key = prefix ? `${prefix}.${k}` : k;
    if (typeof v === 'object' && v !== null && !Array.isArray(v)) {
      Object.assign(result, flattenKeys(v, key));
    } else {
      result[key] = v;
    }
  }
  return result;
}

function setNestedKey(obj, keyPath, value) {
  const parts = keyPath.split('.');
  let cur = obj;
  for (let i = 0; i < parts.length - 1; i++) {
    if (typeof cur[parts[i]] !== 'object' || cur[parts[i]] === null || Array.isArray(cur[parts[i]])) {
      cur[parts[i]] = {};
    }
    cur = cur[parts[i]];
  }
  cur[parts[parts.length - 1]] = value;
}

function shouldSkipValue(val) {
  if (typeof val !== 'string') return true;
  if (!val.trim()) return true;
  if (INCLUDE_SIMPLE) {
    return NO_TRANSLATE_PATTERNS
      .filter(p => p.source !== '^[a-zA-Z0-9_]+$')
      .some(p => p.test(val.trim()));
  }
  if (NO_TRANSLATE_PATTERNS.some(p => p.test(val.trim()))) return true;
  return false;
}

// DeepL supports interpolation variables like {{name}} — preserve them.
// We replace {{var}} with XML-like tags that DeepL ignores, then restore.
function escapeVars(str) {
  const vars = [];
  const escaped = str.replace(/\{\{[^}]+\}\}/g, match => {
    vars.push(match);
    return `<nexus${vars.length - 1}/>`;
  });
  return { escaped, vars };
}

function restoreVars(str, vars) {
  return str.replace(/<nexus(\d+)\/>/g, (_, i) => vars[parseInt(i)] || '');
}

// ── DeepL API ────────────────────────────────────────────────────────────────

async function translateBatchDeepL(texts, targetLang) {
  const BATCH_SIZE = 50;
  const results = [];

  for (let i = 0; i < texts.length; i += BATCH_SIZE) {
    const batch = texts.slice(i, i + BATCH_SIZE);
    const escapedBatch = batch.map(escapeVars);

    const body = new URLSearchParams();
    body.append('auth_key', DEEPL_KEY);
    body.append('source_lang', 'EN');
    body.append('target_lang', targetLang);
    body.append('tag_handling', 'xml');
    body.append('ignore_tags', 'nexus0,nexus1,nexus2,nexus3,nexus4,nexus5,nexus6,nexus7,nexus8,nexus9');
    escapedBatch.forEach(({ escaped }) => body.append('text', escaped));

    const res = await fetch(DEEPL_URL, { method: 'POST', body });
    if (!res.ok) {
      const err = await res.text();
      throw new Error(`DeepL API error ${res.status}: ${err}`);
    }
    const data = await res.json();
    data.translations.forEach((t, idx) => {
      results.push(restoreVars(t.text, escapedBatch[idx].vars));
    });

    if (i + BATCH_SIZE < texts.length) await new Promise(r => setTimeout(r, 200));
  }
  return results;
}

// ── OpenAI API ───────────────────────────────────────────────────────────────
// Uses GPT-4o-mini — very fast, cheap (~$0.003 per 1k tokens), high quality.
// Batches up to 30 strings per request as a JSON array to minimise API calls.

const LANG_NAMES = {
  de: 'German', fr: 'French', it: 'Italian', pt: 'Portuguese', es: 'Spanish',
  nl: 'Dutch', pl: 'Polish', ja: 'Japanese', ar: 'Arabic', ga: 'Irish',
};

async function translateBatchOpenAI(texts, targetLangCode, targetLangDeepL) {
  const langName = LANG_NAMES[targetLangCode] || targetLangCode;
  const BATCH_SIZE = 30;
  const results = [];

  for (let i = 0; i < texts.length; i += BATCH_SIZE) {
    const batch = texts.slice(i, i + BATCH_SIZE);
    const escapedBatch = batch.map(escapeVars);
    const toTranslate = escapedBatch.map(b => b.escaped);

    const prompt = `You are a professional translator for a community timebanking platform called Project NEXUS.
Translate the following UI strings from English to ${langName}.
Rules:
- Keep {{variable}} placeholders EXACTLY as-is (do not translate them)
- Keep HTML tags exactly as-is
- Use natural, friendly tone appropriate for a community platform
- Return ONLY a JSON array of translated strings, same order and count as input
- Do not add any explanation or markdown

Input JSON array:
${JSON.stringify(toTranslate, null, 2)}`;

    const res = await fetch('https://api.openai.com/v1/chat/completions', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${OPENAI_KEY}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        model: 'gpt-4o-mini',
        messages: [{ role: 'user', content: prompt }],
        temperature: 0.1,
        response_format: { type: 'json_object' },
      }),
    });

    if (!res.ok) {
      const err = await res.text();
      throw new Error(`OpenAI API error ${res.status}: ${err}`);
    }

    const data = await res.json();
    const content = data.choices[0].message.content;

    let parsed;
    try {
      // Model may return {"translations": [...]} or {"1": "a", "2": "b"} or just [...]
      const obj = JSON.parse(content);
      if (Array.isArray(obj)) {
        parsed = obj;
      } else if (Array.isArray(obj.translations)) {
        parsed = obj.translations;
      } else if (Array.isArray(obj.results)) {
        parsed = obj.results;
      } else {
        // Object.values can return [["a","b","c"]] if one key holds the whole array — flatten one level
        const vals = Object.values(obj);
        parsed = (vals.length === 1 && Array.isArray(vals[0])) ? vals[0] : vals;
      }
    } catch {
      throw new Error(`OpenAI returned non-JSON: ${content.substring(0, 200)}`);
    }

    if (parsed.length !== batch.length) {
      throw new Error(`OpenAI returned ${parsed.length} translations for ${batch.length} inputs`);
    }

    parsed.forEach((t, idx) => {
      results.push(restoreVars(String(t), escapedBatch[idx].vars));
    });

    if (i + BATCH_SIZE < texts.length) await new Promise(r => setTimeout(r, 300));
  }
  return results;
}

async function translateBatchGoogle(texts, targetLangCode) {
  const results = [];

  for (const text of texts) {
    const { escaped, vars } = escapeVars(text);
    const url = new URL('https://translate.googleapis.com/translate_a/single');
    url.searchParams.set('client', 'gtx');
    url.searchParams.set('sl', 'en');
    url.searchParams.set('tl', targetLangCode);
    url.searchParams.set('dt', 't');
    url.searchParams.set('q', escaped);

    const res = await fetch(url);
    if (!res.ok) {
      const err = await res.text();
      throw new Error(`Google Translate error ${res.status}: ${err}`);
    }

    const data = await res.json();
    const translated = Array.isArray(data?.[0])
      ? data[0].map((part) => part?.[0] ?? '').join('')
      : '';
    results.push(restoreVars(translated, vars));

    await new Promise(r => setTimeout(r, 80));
  }

  return results;
}

async function translateBatch(texts, targetLangCode, targetLangDeepL) {
  if (USE_GOOGLE) {
    return translateBatchGoogle(texts, targetLangCode);
  }

  if (targetLangCode === 'ga') {
    if (!OPENAI_KEY) {
      throw new Error('Irish translation requires OPENAI_API_KEY because DeepL does not support ga.');
    }
    return translateBatchOpenAI(texts, targetLangCode, targetLangDeepL);
  }

  if (USE_OPENAI) {
    return translateBatchOpenAI(texts, targetLangCode, targetLangDeepL);
  }

  if (!targetLangDeepL) {
    throw new Error(`No translation backend is configured for ${targetLangCode}.`);
  }

  return translateBatchDeepL(texts, targetLangDeepL);
}

// ── Main ─────────────────────────────────────────────────────────────────────

async function main() {
  if (!DEEPL_KEY && !OPENAI_KEY && !USE_GOOGLE && !DRY_RUN && !SUMMARY_ONLY) {
    console.error('❌  No translation API key found.');
    console.error('   Option A (recommended): get a FREE DeepL key at https://www.deepl.com/pro-api');
    console.error('   Then run: DEEPL_API_KEY=your_key node scripts/translate-i18n-gaps.mjs');
    console.error('   Option B: set OPENAI_API_KEY in your environment (uses GPT-4o-mini)');
    console.error('   Option C: pass --google to use Google Translate without storing an API key');
    process.exit(1);
  }

  const backend = USE_GOOGLE ? 'Google Translate public endpoint' : (USE_OPENAI ? 'OpenAI GPT-4o-mini' : 'DeepL');
  if (!DRY_RUN && !SUMMARY_ONLY) {
    console.log(`🔑 Using translation backend: ${backend}`);
  }

  const enDir = path.join(LOCALES_DIR, 'en');
  const enFiles = fs.readdirSync(enDir).filter(f => f.endsWith('.json'));
  const languages = SUPPORTED_LANGUAGES;

  let totalGaps = 0;
  let totalTranslated = 0;
  let totalSkipped = 0;

  const langs = langFilter ? [langFilter] : languages;

  for (const lang of langs) {
    if (langFilter && lang !== langFilter) continue;
    const deeplCode = LANG_MAP[lang];
    if (lang === 'ga' && !OPENAI_KEY && !USE_GOOGLE && !SUMMARY_ONLY && !DRY_RUN) {
      console.log('⚠️  ga: DeepL does not support Irish and OPENAI_API_KEY is not set — skipping');
      continue;
    }
    if (!deeplCode && !OPENAI_KEY && !USE_GOOGLE && !SUMMARY_ONLY && !DRY_RUN) {
      console.log(`⚠️  ${lang}: No translation backend available — skipping`);
      continue;
    }

    const langDir = path.join(LOCALES_DIR, lang);
    if (!fs.existsSync(langDir)) fs.mkdirSync(langDir, { recursive: true });

    for (const file of enFiles) {
      if (SKIP_NAMESPACES.has(file)) continue;
      if (nsFilter && !file.includes(nsFilter)) continue;

      const enPath = path.join(enDir, file);
      const langPath = path.join(langDir, file);

      const enData = JSON.parse(fs.readFileSync(enPath, 'utf8'));
      const langData = fs.existsSync(langPath)
        ? JSON.parse(fs.readFileSync(langPath, 'utf8'))
        : {};

      const enFlat = flattenKeys(enData);
      const langFlat = flattenKeys(langData);

      // Find keys that need translation:
      // - Missing entirely (key absent from language file)
      // - Value is identical to English (gap-fill fallback — not yet translated)
      // - --force: re-translate even strings that already differ from English
      const keysToTranslate = [];
      for (const [key, enVal] of Object.entries(enFlat)) {
        if (typeof enVal !== 'string') continue;
        if (shouldSkipValue(enVal)) continue;

        const langVal = langFlat[key];
        const needsTranslation =
          langVal === undefined ||   // missing
          (!MISSING_ONLY && langVal === enVal) ||       // English fallback (identical to source)
          FORCE;                     // --force re-translates everything

        if (needsTranslation) {
          keysToTranslate.push({ key, enVal });
        }
      }

      if (keysToTranslate.length === 0) continue;

      totalGaps += keysToTranslate.length;

      if (SUMMARY_ONLY) {
        console.log(`  ${lang}/${file}: ${keysToTranslate.length} gaps`);
        continue;
      }

      console.log(`\n🌍 Translating ${lang}/${file} (${keysToTranslate.length} strings → ${deeplCode})...`);

      if (DRY_RUN) {
        console.log(`   [DRY RUN] Would translate ${keysToTranslate.length} strings`);
        keysToTranslate.slice(0, 3).forEach(({ key, enVal }) => {
          console.log(`   "${key}": "${enVal.substring(0, 60)}${enVal.length > 60 ? '...' : ''}"`);
        });
        if (keysToTranslate.length > 3) console.log(`   ... and ${keysToTranslate.length - 3} more`);
        totalSkipped += keysToTranslate.length;
        continue;
      }

      try {
        const translated = await translateBatch(
          keysToTranslate.map(k => k.enVal),
          lang,
          deeplCode
        );

        // Merge translations back into langData
        const updated = JSON.parse(JSON.stringify(langData)); // deep clone
        keysToTranslate.forEach(({ key }, idx) => {
          setNestedKey(updated, key, translated[idx]);
        });

        // Preserve key order from EN file
        const ordered = {};
        function orderLike(enObj, targetObj, result) {
          for (const key of Object.keys(enObj)) {
            if (key in targetObj) {
              if (typeof enObj[key] === 'object' && enObj[key] !== null && !Array.isArray(enObj[key])) {
                result[key] = {};
                orderLike(enObj[key], targetObj[key] || {}, result[key]);
              } else {
                result[key] = targetObj[key];
              }
            }
          }
          // Keep any extra keys in target not in EN
          for (const key of Object.keys(targetObj)) {
            if (!(key in result)) result[key] = targetObj[key];
          }
        }
        orderLike(enData, updated, ordered);

        if (!DRY_RUN) {
          fs.writeFileSync(langPath, JSON.stringify(ordered, null, 2) + '\n', 'utf8');
        }

        totalTranslated += keysToTranslate.length;
        console.log(`   ✅ ${keysToTranslate.length} strings translated`);
      } catch (err) {
        console.error(`   ❌ Failed: ${err.message}`);
        totalSkipped += keysToTranslate.length;
      }
    }
  }

  console.log('\n' + '─'.repeat(60));
  if (SUMMARY_ONLY) {
    console.log(`📊 Total gaps found: ${totalGaps}`);
    console.log(`   Affected languages: ${langs.filter(l => SUPPORTED_LANGUAGES.includes(l)).join(', ')}`);
    console.log(`   Irish (ga) uses OpenAI when OPENAI_API_KEY is available`);
  } else if (DRY_RUN) {
    console.log(`📊 Dry run complete: ${totalGaps} strings would be translated`);
  } else {
    console.log(`📊 Done: ${totalTranslated} strings translated, ${totalSkipped} skipped`);
    if (totalTranslated > 0) {
      console.log('\nNext steps:');
      console.log('  1. Review a sample of translations: git diff react-frontend/public/locales/');
      console.log('  2. Run the drift check: node scripts/check-i18n-drift.mjs --summary');
      console.log('  3. Commit: git add react-frontend/public/locales/ && git commit -m "i18n: machine-translate user-facing strings via DeepL"');
    }
  }
}

main().catch(err => {
  console.error('Fatal:', err.message);
  process.exit(1);
});
