// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Convention test: mounted-guard refs must re-arm on mount.
 *
 * Under React StrictMode (enabled in main.tsx), every component mounts,
 * runs effect cleanups once on a simulated unmount, then remounts WITHOUT
 * re-running useRef initializers. A guard written as
 *
 *   const mountedRef = useRef(true);
 *   useEffect(() => () => { mountedRef.current = false; }, []);
 *
 * is therefore permanently false in development: the cleanup runs on the
 * simulated unmount and nothing ever sets it back. Every handler gated on
 * `if (!mountedRef.current) return;` silently dead-ends after its API call.
 * This broke the onboarding wizard's profile step in dev (the Next button
 * saved the bio but never advanced). The fix is to set `.current = true`
 * inside the effect body before returning the cleanup.
 *
 * This test scans the source tree for the broken idiom so it can never
 * come back.
 */

import { describe, it, expect } from 'vitest';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

function walk(dir: string, out: string[] = []): string[] {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) {
      if (entry === 'node_modules') continue;
      walk(full, out);
    } else if (/\.(ts|tsx)$/.test(entry) && !/\.(test|spec)\./.test(entry)) {
      out.push(full);
    }
  }
  return out;
}

describe('mounted-guard convention', () => {
  it('every mounted-style guard ref initialised to true re-arms itself in an effect body', () => {
    const offenders: string[] = [];

    for (const file of walk('src')) {
      const text = readFileSync(file, 'utf8');
      // Declarations like: const mountedRef = useRef(true) / useRef<boolean>(true)
      const declRe = /(?:const|let)\s+(\w*[Mm]ounted\w*)\s*=\s*(?:React\.)?useRef(?:<[^>]*>)?\(\s*true\s*\)/g;
      let m: RegExpExecArray | null;
      while ((m = declRe.exec(text)) !== null) {
        const name = m[1];
        const setsFalse = new RegExp(`${name}\\.current\\s*=\\s*false`).test(text);
        const rearmsTrue = new RegExp(`${name}\\.current\\s*=\\s*true`).test(text);
        if (setsFalse && !rearmsTrue) {
          offenders.push(`${file}: "${name}" is set to false in a cleanup but never re-armed to true on mount`);
        }
      }
    }

    expect(offenders, 'Cleanup-only mount guards are permanently false under StrictMode — set `.current = true` in the effect body (see OnboardingPage.tsx)').toEqual([]);
  });
});
