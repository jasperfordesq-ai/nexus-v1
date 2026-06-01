// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import fs from 'fs';
import path from 'path';

describe('global HeroUI Native token aliases', () => {
  it('maps the project muted foreground class to HeroUI Native muted text', () => {
    const css = fs.readFileSync(path.join(__dirname, '..', '..', 'global.css'), 'utf8');

    expect(css).toContain('--color-muted-foreground: var(--muted);');
  });
});
