// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

// eslint-disable-next-line @typescript-eslint/no-require-imports
const { getDefaultConfig } = require('expo/metro-config');
// eslint-disable-next-line @typescript-eslint/no-require-imports
const { withNativeWind } = require('nativewind/metro');

const config = getDefaultConfig(__dirname);

// Exclude test files from the Metro bundle (testing-library cannot be bundled).
config.resolver.blockList = [
  /.*[/\\].*\.test\.[jt]sx?$/,
  /.*[/\\].*\.spec\.[jt]sx?$/,
  /.*[/\\]jest-setup\.[jt]s$/,
];

module.exports = withNativeWind(config, { input: './global.css' });
