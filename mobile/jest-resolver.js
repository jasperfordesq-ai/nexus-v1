// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
//
// Custom Jest resolver that intercepts heroui-native's internal animation-settings
// context modules and returns stubs, preventing crashes when components are rendered
// without a HeroUINativeProvider in the test tree.
//
// Background: heroui-native uses React.createContext with strict:false. When no provider
// is present, useGlobalAnimationSettings() returns undefined and destructuring it crashes.

'use strict';

const path = require('path');

// Build sets of suffixes to match (with and without .js / /index.js)
const STUB_PATTERNS = [
  {
    suffixes: [
      'providers/animation-settings',
      'providers/animation-settings.js',
      'providers/animation-settings/index',
      'providers/animation-settings/index.js',
    ],
    stub: path.resolve(__dirname, '__mocks__/heroui-native-animation-settings.js'),
  },
  {
    suffixes: [
      'contexts/animation-settings-context',
      'contexts/animation-settings-context.js',
    ],
    stub: path.resolve(__dirname, '__mocks__/heroui-native-animation-settings-context.js'),
  },
  {
    suffixes: [
      'providers/text-component',
      'providers/text-component.js',
      'providers/text-component/index',
      'providers/text-component/index.js',
      'text-component/provider',
      'text-component/provider.js',
    ],
    stub: path.resolve(__dirname, '__mocks__/heroui-native-text-component.js'),
  },
];

module.exports = (moduleName, options) => {
  // Check if the request matches one of the stubs (suffix match on normalized slashes)
  const normalized = moduleName.replace(/\\/g, '/');
  for (const { suffixes, stub } of STUB_PATTERNS) {
    for (const suffix of suffixes) {
      if (normalized === suffix || normalized.endsWith('/' + suffix)) {
        return stub;
      }
    }
  }
  return options.defaultResolver(moduleName, options);
};
