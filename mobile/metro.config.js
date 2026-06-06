// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

// eslint-disable-next-line @typescript-eslint/no-require-imports
const { getDefaultConfig } = require('expo/metro-config');
// eslint-disable-next-line @typescript-eslint/no-require-imports
const { withUniwindConfig } = require('uniwind/metro');

const config = getDefaultConfig(__dirname);

// Exclude test files from the Metro bundle (testing-library cannot be bundled).
config.resolver.blockList = [
  /.*[/\\].*\.test\.[jt]sx?$/,
  /.*[/\\].*\.spec\.[jt]sx?$/,
  /.*[/\\]jest-setup\.[jt]s$/,
];

// Enable package.json "exports" field resolution. Required for heroui-native
// which uses conditional exports to serve the correct ESM/CJS entry point.
config.resolver.unstable_enablePackageExports = true;

// heroui-native ships as ESM source (JSX/TSX inside node_modules) and must be
// transformed by Babel — same allowlist as jest.config.js transformIgnorePatterns.
// tailwind-variants and react-native-worklets are also ESM-only packages.
config.transformer.transformIgnorePatterns = [
  'node_modules/(?!(' +
    'react-native|' +
    '@react-native(-community)?|' +
    'expo|' +
    '@expo|' +
    '@expo-google-fonts|' +
    'react-navigation|' +
    '@react-navigation|' +
    '@unimodules|' +
    'sentry-expo|' +
    '@sentry/react-native|' +
    'react-native-svg|' +
    'react-native-reanimated|' +
    'react-native-worklets|' +
    'react-native-gesture-handler|' +
    '@gorhom|' +
    'heroui-native|' +
    'tailwind-variants|' +
    'nativewind|' +
    'uniwind' +
  '))',
];

// The mobile bundle is route-heavy. Inline requires keep non-initial route
// modules from being evaluated during cold start, which shortens the blank
// pre-render window in release builds.
config.transformer.getTransformOptions = async () => ({
  transform: {
    experimentalImportSupport: false,
    inlineRequires: true,
  },
});

module.exports = withUniwindConfig(config, {
  cssEntryFile: './global.css',
  dtsFile: './uniwind-types.d.ts',
});
