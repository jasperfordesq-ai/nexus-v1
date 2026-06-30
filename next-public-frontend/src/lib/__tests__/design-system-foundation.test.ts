// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

import packageJson from '../../../package.json';

const root = process.cwd();

describe('HeroUI public design-system foundation', () => {
  it('pins the shadow Next app to the same HeroUI and Tailwind major setup as the React app', () => {
    expect(packageJson.dependencies['@heroui/react']).toBe('3.1.0');
    expect(packageJson.dependencies['@heroui/styles']).toBe('3.1.0');
    expect(packageJson.devDependencies.tailwindcss).toBe('4.3.0');
    expect(packageJson.devDependencies['@tailwindcss/postcss']).toBe('4.3.0');
    expect(packageJson.devDependencies.postcss).toBe('8.5.16');
  });

  it('loads Tailwind, HeroUI styles, and the mirrored NEXUS token layers in the correct order', () => {
    const globalsCss = readFileSync(join(root, 'app', 'globals.css'), 'utf8');

    expect(globalsCss.indexOf('@import "tailwindcss"')).toBeGreaterThanOrEqual(0);
    expect(globalsCss.indexOf('@import "@heroui/styles"')).toBeGreaterThan(globalsCss.indexOf('@import "tailwindcss"'));
    expect(globalsCss).toContain('@custom-variant dark (&:is(.dark *))');
    expect(globalsCss).toContain('@import "./styles/tokens.css"');
    expect(globalsCss).toContain('@import "./styles/glass.css"');
    expect(globalsCss).toContain('@import "./styles/public.css"');
  });

  it('keeps the Next app on mirrored token files instead of bespoke global selectors', () => {
    expect(existsSync(join(root, 'app', 'styles', 'tokens.css'))).toBe(true);
    expect(existsSync(join(root, 'app', 'styles', 'glass.css'))).toBe(true);
    expect(existsSync(join(root, 'app', 'styles', 'public.css'))).toBe(true);

    const globalsCss = readFileSync(join(root, 'app', 'globals.css'), 'utf8');

    expect(globalsCss).not.toContain('.site-header');
    expect(globalsCss).not.toContain('.listing-card');
    expect(globalsCss).not.toContain('.public-panel');
  });

  it('uses Next image rendering with intrinsic dimensions for public media', () => {
    const publicPageSource = readFileSync(join(root, 'src', 'ui', 'PublicPage.tsx'), 'utf8');

    expect(publicPageSource).toContain("from 'next/image'");
    expect(publicPageSource).not.toContain('<img');
    expect(publicPageSource).toContain('unoptimized');
    expect(publicPageSource).toContain('height={');
    expect(publicPageSource).toContain('width={');
  });
});
