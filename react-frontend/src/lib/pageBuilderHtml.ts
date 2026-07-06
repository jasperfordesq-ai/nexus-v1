// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import postcss, { type AtRule, type Container, type Declaration, type Rule } from 'postcss';

const BUILDER_SCOPE = '.nexus-custom-page-builder';

const UNSAFE_CSS_PATTERNS = [
  /@import\b/gi,
  /expression\s*\(/gi,
  /javascript\s*:/gi,
  /vbscript\s*:/gi,
  /behavior\s*:/gi,
  /-moz-binding\s*:/gi,
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

function serializeSafeDeclarations(rule: Rule): string {
  const declarations: string[] = [];

  rule.nodes?.forEach((node) => {
    if (node.type !== 'decl') return;
    const declaration = node as Declaration;
    if (!isSafeCssDeclaration(declaration.prop, declaration.value)) return;
    declarations.push(`${declaration.prop}:${declaration.value}${declaration.important ? ' !important' : ''}`);
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
  return `${scopedCss ? `<style>${scopedCss}</style>` : ''}<div class="nexus-custom-page-builder">${body}</div>`;
}

export function exportScopedPageBuilderHtml(bodyHtml: string, css: string): string {
  return scopePageBuilderHtml(`<style>${css}</style>${bodyHtml}`);
}

export const __pageBuilderHtmlTesting = { BUILDER_SCOPE, isSafeCssDeclaration, scopeSelector };
