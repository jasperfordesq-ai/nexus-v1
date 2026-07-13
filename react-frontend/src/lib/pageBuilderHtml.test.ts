// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import {
  sanitizePageBuilderCssForStorage,
  stripUnsafePageBuilderHtml,
} from './pageBuilderHtml';

describe('page builder storage sanitizers', () => {
  it('uses a parser-backed allow-list for malformed and nested hostile markup', () => {
    const result = stripUnsafePageBuilderHtml(`
      <section class="hero" onclick="alert(1)">
        <script><script>alert(1)</script></script>
        <joomla-module><img src="javascript:alert(2)" onerror="alert(3)"></joomla-module>
        <a href="java\tscript:alert(4)">Unsafe link</a>
        <p>Safe content</p>
      </section>
    `);

    expect(result).toContain('<section class="hero">');
    expect(result).toContain('<p>Safe content</p>');
    expect(result).not.toMatch(/<script|onerror|onclick|javascript:/i);
    expect(result).not.toContain('joomla-module');
  });

  it('keeps safe builder markup while applying URL and inline-style policy', () => {
    const result = stripUnsafePageBuilderHtml(`
      <form action="/contact" method="post">
        <a href="https://example.test/about">About</a>
        <img src="/uploads/page.jpg" alt="Page">
        <div style="color:green;position:fixed;z-index:99;background:url(javascript:bad)">Text</div>
      </form>
    `);

    expect(result).toContain('action="/contact"');
    expect(result).toContain('href="https://example.test/about"');
    expect(result).toContain('src="/uploads/page.jpg"');
    expect(result).toContain('style="color:green"');
    expect(result).not.toContain('position:fixed');
    expect(result).not.toContain('z-index');
    expect(result).not.toContain('javascript:');
  });

  it('serializes safe unscoped CSS and drops global, escaped, and active declarations', () => {
    const result = sanitizePageBuilderCssForStorage(`
      body { display:none }
      .hero { color: red; position: fixed; z-index: 100; }
      .escaped { p\\6fsition:fixed; background:u\\72l(javascript:alert(1)); }
      @media (max-width: 600px) { .hero { color: blue } }
    `);

    expect(result).toContain('.hero{color:red}');
    expect(result).toContain('@media (max-width: 600px){.hero{color:blue}}');
    expect(result).not.toContain('body');
    expect(result).not.toContain('position');
    expect(result).not.toContain('z-index');
    expect(result).not.toContain('javascript');
    expect(result).not.toContain('\\');
  });

  it('cannot break out of the stored style element', () => {
    const result = sanitizePageBuilderCssForStorage(
      '.hero{color:red}</style><img src=x onerror=alert(1)><style>.other{color:blue}',
    );

    expect(result).toBe('');
  });
});
