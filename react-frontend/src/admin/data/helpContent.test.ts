// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect } from 'vitest';
import englishHelp from '../../../public/locales/en/admin_help.json';
import type { HelpArticle, HelpStep } from './helpContent';

const HELP_CONTENT = englishHelp.articles as Record<string, HelpArticle>;

// ---------------------------------------------------------------------------
// Registry-level invariants
// ---------------------------------------------------------------------------

describe('HELP_CONTENT registry', () => {
  const entries = Object.entries(HELP_CONTENT);

  it('is non-empty', () => {
    expect(entries.length).toBeGreaterThan(0);
  });

  it('exports more than 30 articles (sanity check — source has ~45)', () => {
    expect(entries.length).toBeGreaterThan(30);
  });

  it('all keys start with a leading slash (they are path segments)', () => {
    for (const [path] of entries) {
      expect(path.startsWith('/'), `key "${path}" must start with /`).toBe(true);
    }
  });

  it('all keys are unique (no duplicate paths in the Record)', () => {
    const keys = entries.map(([k]) => k);
    const unique = new Set(keys);
    expect(unique.size).toBe(keys.length);
  });

  it('no key contains whitespace', () => {
    for (const [path] of entries) {
      expect(/\s/.test(path), `key "${path}" must not contain whitespace`).toBe(false);
    }
  });
});

// ---------------------------------------------------------------------------
// Per-article structural invariants
// ---------------------------------------------------------------------------

describe('every HelpArticle', () => {
  const entries = Object.entries(HELP_CONTENT);

  it('has a non-empty title string', () => {
    for (const [path, article] of entries) {
      expect(
        typeof article.title === 'string' && article.title.trim().length > 0,
        `"${path}".title must be a non-empty string`,
      ).toBe(true);
    }
  });

  it('has a non-empty summary string', () => {
    for (const [path, article] of entries) {
      expect(
        typeof article.summary === 'string' && article.summary.trim().length > 0,
        `"${path}".summary must be a non-empty string`,
      ).toBe(true);
    }
  });

  it('title is distinct from summary (not copy-pasted)', () => {
    for (const [path, article] of entries) {
      expect(
        article.title !== article.summary,
        `"${path}".title and .summary must not be identical`,
      ).toBe(true);
    }
  });
});

// ---------------------------------------------------------------------------
// Optional fields — when present they must be well-formed
// ---------------------------------------------------------------------------

describe('HelpArticle.steps (when present)', () => {
  const entries = Object.entries(HELP_CONTENT).filter(
    ([, a]) => a.steps !== undefined,
  );

  it('at least one article has steps', () => {
    expect(entries.length).toBeGreaterThan(0);
  });

  it('every steps array is non-empty', () => {
    for (const [path, article] of entries) {
      expect(
        (article.steps as HelpStep[]).length > 0,
        `"${path}".steps must not be an empty array`,
      ).toBe(true);
    }
  });

  it('every step has a non-empty label', () => {
    for (const [path, article] of entries) {
      for (const step of article.steps as HelpStep[]) {
        expect(
          typeof step.label === 'string' && step.label.trim().length > 0,
          `A step in "${path}" has an empty label`,
        ).toBe(true);
      }
    }
  });

  it('step.detail, when present, is a non-empty string', () => {
    for (const [path, article] of entries) {
      for (const step of article.steps as HelpStep[]) {
        if (step.detail !== undefined) {
          expect(
            typeof step.detail === 'string' && step.detail.trim().length > 0,
            `A step in "${path}" has a blank detail field`,
          ).toBe(true);
        }
      }
    }
  });
});

describe('HelpArticle.tips (when present)', () => {
  const entries = Object.entries(HELP_CONTENT).filter(
    ([, a]) => a.tips !== undefined,
  );

  it('every tips array is non-empty', () => {
    for (const [path, article] of entries) {
      expect(
        (article.tips as string[]).length > 0,
        `"${path}".tips must not be an empty array`,
      ).toBe(true);
    }
  });

  it('every tip is a non-empty string', () => {
    for (const [path, article] of entries) {
      for (const tip of article.tips as string[]) {
        expect(
          typeof tip === 'string' && tip.trim().length > 0,
          `A tip in "${path}" is blank`,
        ).toBe(true);
      }
    }
  });
});

describe('HelpArticle.caution (when present)', () => {
  const entries = Object.entries(HELP_CONTENT).filter(
    ([, a]) => a.caution !== undefined,
  );

  it('caution is a non-empty string when present', () => {
    for (const [path, article] of entries) {
      expect(
        typeof article.caution === 'string' && (article.caution as string).trim().length > 0,
        `"${path}".caution must be a non-empty string when provided`,
      ).toBe(true);
    }
  });
});

describe('HelpArticle.relatedPaths (when present)', () => {
  const entries = Object.entries(HELP_CONTENT).filter(
    ([, a]) => a.relatedPaths !== undefined,
  );

  it('every relatedPaths entry has a non-empty label', () => {
    for (const [path, article] of entries) {
      for (const rel of article.relatedPaths!) {
        expect(
          typeof rel.label === 'string' && rel.label.trim().length > 0,
          `A relatedPaths entry in "${path}" has an empty label`,
        ).toBe(true);
      }
    }
  });

  it('every relatedPaths entry has a path starting with /', () => {
    for (const [path, article] of entries) {
      for (const rel of article.relatedPaths!) {
        expect(
          typeof rel.path === 'string' && rel.path.startsWith('/'),
          `A relatedPaths entry in "${path}" has an invalid path: "${rel.path}"`,
        ).toBe(true);
      }
    }
  });

  it('no relatedPaths entry points to its own parent key (self-referential loop)', () => {
    for (const [path, article] of entries) {
      for (const rel of article.relatedPaths!) {
        expect(
          rel.path !== path,
          `"${path}" has a relatedPaths entry pointing to itself`,
        ).toBe(true);
      }
    }
  });
});

// ---------------------------------------------------------------------------
// Spot-checks for known articles (guards against silent deletions/renames)
// ---------------------------------------------------------------------------

describe('HELP_CONTENT spot-checks for known paths', () => {
  it('contains the /caring entry', () => {
    expect(HELP_CONTENT['/caring']).toBeDefined();
    expect(HELP_CONTENT['/caring'].title).toContain('Caring Community');
  });

  it('contains the /caring/safeguarding entry', () => {
    const article = HELP_CONTENT['/caring/safeguarding'];
    expect(article).toBeDefined();
    // Must include a caution because safeguarding has compliance implications.
    expect(article.caution).toBeDefined();
    expect((article.caution as string).length).toBeGreaterThan(0);
  });

  it('contains the /super-admin/national/kiss entry', () => {
    expect(HELP_CONTENT['/super-admin/national/kiss']).toBeDefined();
  });

  it('contains the /admin/ki-agents entry', () => {
    expect(HELP_CONTENT['/admin/ki-agents']).toBeDefined();
    // The KI-Agenten article should have a caution about financial actions.
    expect(HELP_CONTENT['/admin/ki-agents'].caution).toBeDefined();
  });

  it('contains the /admin/pilot-inquiries entry', () => {
    expect(HELP_CONTENT['/admin/pilot-inquiries']).toBeDefined();
  });

  it('/caring/warmth-pass has both a caution and relatedPaths', () => {
    const article = HELP_CONTENT['/caring/warmth-pass'];
    expect(article.caution).toBeDefined();
    expect(article.relatedPaths).toBeDefined();
    expect(article.relatedPaths!.length).toBeGreaterThan(0);
  });
});

// ---------------------------------------------------------------------------
// Type-compatibility checks (TypeScript types via runtime shape checks)
// ---------------------------------------------------------------------------

describe('HELP_CONTENT type compatibility', () => {
  it('each value satisfies the HelpArticle interface at runtime', () => {
    for (const [, article] of Object.entries(HELP_CONTENT)) {
      const a = article as HelpArticle;
      expect(typeof a.title).toBe('string');
      expect(typeof a.summary).toBe('string');
      // Optional arrays must be arrays when present
      if (a.steps !== undefined) expect(Array.isArray(a.steps)).toBe(true);
      if (a.tips !== undefined) expect(Array.isArray(a.tips)).toBe(true);
      if (a.relatedPaths !== undefined) expect(Array.isArray(a.relatedPaths)).toBe(true);
    }
  });
});

// ---------------------------------------------------------------------------
// Aggregate counts (regression guards)
// ---------------------------------------------------------------------------

describe('HELP_CONTENT aggregate counts', () => {
  it('has at least 20 articles under the /caring prefix', () => {
    const caringPaths = Object.keys(HELP_CONTENT).filter((k) =>
      k.startsWith('/caring'),
    );
    expect(caringPaths.length).toBeGreaterThanOrEqual(20);
  });

  it('has at least one super-admin article', () => {
    const superAdminPaths = Object.keys(HELP_CONTENT).filter((k) =>
      k.startsWith('/super-admin'),
    );
    expect(superAdminPaths.length).toBeGreaterThanOrEqual(1);
  });

  it('has at least two /admin/ articles', () => {
    const adminPaths = Object.keys(HELP_CONTENT).filter((k) =>
      k.startsWith('/admin/'),
    );
    expect(adminPaths.length).toBeGreaterThanOrEqual(2);
  });

  it('total step count across all articles is at least 100', () => {
    const total = Object.values(HELP_CONTENT).reduce(
      (sum, a) => sum + (a.steps?.length ?? 0),
      0,
    );
    expect(total).toBeGreaterThanOrEqual(100);
  });
});
