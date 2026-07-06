// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import postcss, { type AtRule, type Container, type Declaration, type Rule } from 'postcss';

const BUILDER_SCOPE = '.nexus-custom-page-builder';

const PAGE_BUILDER_BASELINE_CSS = `
.nexus-custom-page-builder{background:var(--background,#ffffff);color:var(--foreground,#111827);color-scheme:inherit}
.nexus-custom-page-builder a{color:var(--accent-color,var(--color-accent,#0891b2))}
.nexus-custom-page-builder img{max-width:100%;height:auto}
`.trim();

const PAGE_BUILDER_THEME_OVERRIDE_CSS = `
.nexus-custom-page-builder .nexus-page-section{background:var(--background,#ffffff);color:var(--foreground,#111827)}
.nexus-custom-page-builder .nexus-page-section:nth-child(even){background:var(--surface-elevated,rgba(255,255,255,.9))}
.nexus-custom-page-builder .nexus-page-hero{background:linear-gradient(135deg,var(--surface-elevated,rgba(255,255,255,.9)) 0%,color-mix(in srgb,var(--accent-color,var(--color-accent,#0891b2)) 12%,var(--background,#ffffff)) 55%,color-mix(in srgb,var(--color-warning,#d97706) 10%,var(--background,#ffffff)) 100%)}
.nexus-custom-page-builder .nexus-page-kicker{color:var(--accent-color,var(--color-accent,#0891b2))}
.nexus-custom-page-builder .nexus-page-hero h1,.nexus-custom-page-builder .nexus-page-card h2{color:var(--foreground,#111827)}
.nexus-custom-page-builder .nexus-page-lede,.nexus-custom-page-builder .nexus-page-card p{color:var(--foreground-muted,var(--foreground,#4b5563))}
.nexus-custom-page-builder .nexus-page-card{background:var(--surface-elevated,rgba(255,255,255,.9));border-color:var(--border-default,rgba(17,24,39,.12));color:var(--foreground,#111827)}
.nexus-custom-page-builder .nexus-page-button{background:var(--accent-color,var(--color-accent,#0891b2));color:var(--accent-foreground,#ffffff)}
`.trim();

const LEGACY_NEXUS_PAGE_COLOR_VALUES = new Map<string, string>([
  ['#ffffff', 'var(--background,#ffffff)'],
  ['#f7faf8', 'var(--surface-elevated,rgba(255,255,255,.9))'],
  ['#f6fbf8', 'var(--surface-elevated,rgba(255,255,255,.9))'],
  ['#eef6ff', 'color-mix(in srgb,var(--accent-color,var(--color-accent,#0891b2)) 12%,var(--background,#ffffff))'],
  ['#fff7ed', 'color-mix(in srgb,var(--color-warning,#d97706) 10%,var(--background,#ffffff))'],
  ['#111827', 'var(--foreground,#111827)'],
  ['#10201a', 'var(--foreground,#111827)'],
  ['#40524a', 'var(--foreground-muted,var(--foreground,#4b5563))'],
  ['#4b5563', 'var(--foreground-muted,var(--foreground,#4b5563))'],
  ['#047857', 'var(--accent-color,var(--color-accent,#0891b2))'],
]);

const UNSAFE_CSS_PATTERNS = [
  /@import\b/i,
  /expression\s*\(/i,
  /javascript\s*:/i,
  /vbscript\s*:/i,
  /data\s*:/i,
  /file\s*:/i,
  /behavior\s*:/i,
  /-moz-binding\s*:/i,
];

const GLOBAL_SELECTOR_PARTS = [
  ':root',
  'html',
  'body',
  'head',
  'script',
  'style',
  '#root',
  '#app',
];

function isUnsafeCss(css: string): boolean {
  return UNSAFE_CSS_PATTERNS.some((pattern) => pattern.test(css));
}

function splitSelectorList(selectors: string): string[] {
  const parts: string[] = [];
  let current = '';
  let depth = 0;
  let quote: string | null = null;

  for (const char of selectors) {
    if (quote) {
      current += char;
      if (char === quote) quote = null;
      continue;
    }
    if (char === '"' || char === "'") {
      quote = char;
      current += char;
      continue;
    }
    if (char === '(' || char === '[') depth += 1;
    if (char === ')' || char === ']') depth = Math.max(0, depth - 1);
    if (char === ',' && depth === 0) {
      if (current.trim()) parts.push(current.trim());
      current = '';
      continue;
    }
    current += char;
  }

  if (current.trim()) parts.push(current.trim());
  return parts;
}

function isGlobalSelector(selector: string): boolean {
  const lowered = selector.trim().toLowerCase();
  return GLOBAL_SELECTOR_PARTS.some((part) => lowered === part || lowered.startsWith(`${part}.`) || lowered.startsWith(`${part} `));
}

function scopeSelector(selector: string): string | null {
  const trimmed = selector.trim();
  if (!trimmed || trimmed.startsWith('@') || isGlobalSelector(trimmed)) return null;
  if (trimmed.startsWith(BUILDER_SCOPE)) return trimmed;
  return `${BUILDER_SCOPE} ${trimmed}`;
}

function normalizeCssValue(value: string): string {
  return value
    .toLowerCase()
    .replace(/\s*!important\s*$/i, '')
    .trim();
}

function isZeroCssValue(value: string): boolean {
  const normalized = normalizeCssValue(value).toLowerCase();
  return normalized.split(/\s+/).every((part) => ['0', '0px', '0rem', '0em', '0%'].includes(part));
}

function isSafeCssDeclaration(property: string, value: string): boolean {
  const normalizedProperty = property.trim().toLowerCase();
  const normalizedValue = normalizeCssValue(value);

  if (!normalizedProperty || !normalizedValue) return false;
  if (isUnsafeCss(`${normalizedProperty}:${normalizedValue}`)) return false;
  if (normalizedProperty === 'position' && ['fixed', 'sticky'].includes(normalizedValue)) return false;
  if (normalizedProperty === 'z-index') return false;
  if (normalizedProperty === 'inset' && isZeroCssValue(value)) return false;
  return true;
}

function shouldTokenizeLegacyNexusRule(rule: Rule): boolean {
  return splitSelectorList(rule.selector).some((selector) => selector.includes('nexus-page-'));
}

function tokenizeLegacyNexusPageValue(property: string, value: string, shouldTokenize: boolean): string {
  if (!shouldTokenize) return value;

  const normalizedProperty = property.trim().toLowerCase();
  if (!['background', 'background-color', 'border-color', 'box-shadow', 'color'].includes(normalizedProperty)) {
    return value;
  }

  let next = value;
  if (normalizedProperty === 'color') {
    next = next.replace(/#fff(?:fff)?\b/gi, 'var(--accent-foreground,#fff)');
  } else {
    next = next.replace(/#fff(?:fff)?\b/gi, 'var(--background,#ffffff)');
  }
  for (const [legacyValue, tokenValue] of LEGACY_NEXUS_PAGE_COLOR_VALUES) {
    next = next.replace(new RegExp(legacyValue.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi'), tokenValue);
  }
  return next;
}

function serializeSafeDeclarations(rule: Rule): string {
  const declarations: string[] = [];
  const shouldTokenize = shouldTokenizeLegacyNexusRule(rule);

  rule.nodes?.forEach((node) => {
    if (node.type !== 'decl') return;
    const declaration = node as Declaration;
    if (!isSafeCssDeclaration(declaration.prop, declaration.value)) return;
    const value = tokenizeLegacyNexusPageValue(declaration.prop, declaration.value, shouldTokenize);
    declarations.push(`${declaration.prop}:${value}${declaration.important ? ' !important' : ''}`);
  });

  return declarations.join(';');
}

function scopeCssContainer(container: Container): string {
  const output: string[] = [];

  container.nodes?.forEach((node) => {
    if (node.type === 'rule') {
      const rule = node as Rule;
      const scopedSelectors = splitSelectorList(rule.selector)
        .map(scopeSelector)
        .filter((selector): selector is string => Boolean(selector));
      const safeBody = serializeSafeDeclarations(rule);

      if (scopedSelectors.length > 0 && safeBody) {
        output.push(`${scopedSelectors.join(',')}{${safeBody}}`);
      }
      return;
    }

    if (node.type === 'atrule') {
      const atRule = node as AtRule;
      const atRuleName = atRule.name.toLowerCase();
      if (!['media', 'supports'].includes(atRuleName)) return;

      const nested = scopeCssContainer(atRule);
      if (nested) output.push(`@${atRuleName} ${atRule.params}{${nested}}`);
    }
  });

  return output.join('\n');
}

export function scopePageBuilderCss(css: string | null | undefined): string {
  if (!css) return '';
  const withoutComments = css.replace(/\/\*[\s\S]*?\*\//g, '');
  if (isUnsafeCss(withoutComments)) return '';
  try {
    return scopeCssContainer(postcss.parse(withoutComments)).trim();
  } catch {
    return '';
  }
}

export function sanitizePageBuilderInlineStyle(style: string | null | undefined): string {
  if (!style) return '';
  const withoutComments = style.replace(/\/\*[\s\S]*?\*\//g, '');
  try {
    const root = postcss.parse(`.x{${withoutComments}}`);
    const firstRule = root.nodes?.find((node): node is Rule => node.type === 'rule');
    if (!firstRule) return '';
    return serializeSafeDeclarations(firstRule);
  } catch {
    return '';
  }
}

export function stripUnsafePageBuilderHtml(html: string): string {
  return html
    .replace(/<script\b[\s\S]*?<\/script>/gi, '')
    .replace(/\son[a-z]+\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+)/gi, '')
    .replace(/<[^>]*\bjoomla\b[^>]*>/gi, '');
}

export function scopePageBuilderHtml(html: string | null | undefined): string {
  if (!html) return '';
  const doc = new DOMParser().parseFromString(stripUnsafePageBuilderHtml(html), 'text/html');
  const scopedCss = Array.from(doc.querySelectorAll('style'))
    .map((style) => scopePageBuilderCss(style.textContent || ''))
    .filter(Boolean)
    .join('\n');

  doc.querySelectorAll('style, script').forEach((node) => node.remove());
  const body = doc.body.innerHTML.trim();
  const css = [PAGE_BUILDER_BASELINE_CSS, scopedCss, PAGE_BUILDER_THEME_OVERRIDE_CSS].filter(Boolean).join('\n');
  return `${css ? `<style>${css}</style>` : ''}<div class="nexus-custom-page-builder">${body}</div>`;
}

export function exportScopedPageBuilderHtml(bodyHtml: string, css: string): string {
  return scopePageBuilderHtml(`<style>${css}</style>${bodyHtml}`);
}

export const __pageBuilderHtmlTesting = {
  BUILDER_SCOPE,
  PAGE_BUILDER_BASELINE_CSS,
  PAGE_BUILDER_THEME_OVERRIDE_CSS,
  isSafeCssDeclaration,
  scopeSelector,
  tokenizeLegacyNexusPageValue,
};
