// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

const fs = require('fs');
const path = require('path');

const root = __dirname;

function read(relativePath) {
  return fs.readFileSync(path.join(root, relativePath), 'utf8');
}

function readJson(relativePath) {
  return JSON.parse(read(relativePath));
}

function listSourceFiles(relativeDir) {
  const dir = path.join(root, relativeDir);
  if (!fs.existsSync(dir)) return [];

  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const fullPath = path.join(dir, entry.name);
    const relativePath = path.relative(root, fullPath);
    if (entry.isDirectory()) {
      return listSourceFiles(relativePath);
    }
    return /\.(jsx?|tsx?)$/.test(entry.name) ? [relativePath] : [];
  });
}

describe('native app configuration', () => {
  it('uses HeroUI Native and native gesture/animation packages, not web HeroUI', () => {
    const pkg = readJson('package.json');
    const deps = { ...(pkg.dependencies ?? {}), ...(pkg.devDependencies ?? {}) };

    expect(deps['heroui-native']).toBeTruthy();
    expect(deps['uniwind']).toBeTruthy();
    expect(deps['react-native-reanimated']).toBeTruthy();
    expect(deps['react-native-gesture-handler']).toBeTruthy();
    expect(deps['react-native-worklets']).toBeTruthy();
    expect(deps['expo-font']).toBeTruthy();
    expect(deps['expo-system-ui']).toBeTruthy();
    expect(deps['@heroui/react']).toBeUndefined();
    expect(deps['@nextui-org/react']).toBeUndefined();
  });

  it('has no web HeroUI imports in mobile source files', () => {
    const sourceFiles = [
      ...listSourceFiles('app'),
      ...listSourceFiles('components'),
      ...listSourceFiles('lib'),
    ];
    const offenders = sourceFiles.filter((relativePath) => {
      const source = read(relativePath);
      return /@heroui\/(?!native)|@nextui|@nextui-org\/react|@heroui\/react/.test(source);
    });

    expect(offenders).toEqual([]);
  });

  it('keeps react-native-reanimated as the last Babel plugin', () => {
    const factory = require('./babel.config.js');
    const config = factory({ cache: jest.fn() });

    expect(config.presets).toContain('babel-preset-expo');
    expect(config.plugins.at(-1)).toBe('react-native-reanimated/plugin');
  });

  it('wraps Metro with Uniwind and transforms native ESM packages', () => {
    const metroConfig = read('metro.config.js');

    expect(metroConfig).toContain('withUniwindConfig(config');
    expect(metroConfig).toContain("cssEntryFile: './global.css'");
    expect(metroConfig).toContain("dtsFile: './uniwind-types.d.ts'");
    expect(metroConfig).toContain('heroui-native');
    expect(metroConfig).toContain('react-native-reanimated');
    expect(metroConfig).toContain('react-native-gesture-handler');
    expect(metroConfig).toContain('react-native-worklets');
    expect(metroConfig).toContain('uniwind');
  });

  it('loads Tailwind v4, Uniwind, and HeroUI Native styles from global CSS', () => {
    const globalCss = read('global.css');

    expect(globalCss).toContain("@import 'tailwindcss'");
    expect(globalCss).toContain("@import 'uniwind'");
    expect(globalCss).toContain("@import 'heroui-native/styles'");
    expect(globalCss).toContain("@source './node_modules/heroui-native/lib'");
  });

  it('keeps common native navigation labels translated', () => {
    const common = readJson('locales/en/common.json');

    expect(common.back).toBe('Back');
    expect(common.buttons.back).toBe('Back');
  });

  it('places native providers and system UI at the root', () => {
    const layout = read('app/_layout.tsx');
    const gestureIndex = layout.indexOf('<GestureHandlerRootView');
    const heroIndex = layout.indexOf('<HeroUINativeProvider>');
    const safeAreaIndex = layout.indexOf('<SafeAreaProvider>');
    const statusIndex = layout.indexOf('<StatusBar style="light"');

    expect(gestureIndex).toBeGreaterThan(-1);
    expect(heroIndex).toBeGreaterThan(gestureIndex);
    expect(safeAreaIndex).toBeGreaterThan(heroIndex);
    expect(statusIndex).toBeGreaterThan(safeAreaIndex);
  });

  it('bundles native fonts and assets into Android release builds', () => {
    const app = readJson('app.json').expo;

    expect(app.plugins).toEqual(expect.arrayContaining(['expo-font']));
    expect(app.assetBundlePatterns).toEqual(expect.arrayContaining(['assets/**/*']));
    expect(app.icon).toBe('./assets/icon.png');
    expect(app.splash.image).toBe('./assets/splash.png');
    expect(app.android.adaptiveIcon.foregroundImage).toBe('./assets/adaptive-icon.png');
  });
});
