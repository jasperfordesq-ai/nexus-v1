// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

const config = require('./tailwind.config.js');

describe('tailwind.config', () => {
  it('scans native app, component, shared library, and HeroUI Native sources', () => {
    expect(config.content).toEqual(expect.arrayContaining([
      './app/**/*.{js,jsx,ts,tsx}',
      './components/**/*.{js,jsx,ts,tsx}',
      './lib/**/*.{js,jsx,ts,tsx}',
      './node_modules/heroui-native/lib/**/*.{js,jsx,ts,tsx}',
    ]));
  });
});
