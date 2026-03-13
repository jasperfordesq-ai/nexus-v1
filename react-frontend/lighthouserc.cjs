// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

module.exports = {
  ci: {
    collect: {
      staticDistDir: './dist',
      // Lighthouse CI serves staticDistDir on a local port automatically.
      // Hash-based routes removed � Lighthouse can't render client-side SPA routes.
      url: [
        'http://localhost/index.html',
      ],
      numberOfRuns: 1,
    },
    assert: {
      preset: 'lighthouse:no-pwa',
      assertions: {
        'categories:performance': ['warn', { minScore: 0.75 }],
        'categories:accessibility': ['error', { minScore: 0.85 }],
        'categories:best-practices': ['warn', { minScore: 0.85 }],
        'categories:seo': ['warn', { minScore: 0.80 }],
      },
    },
    upload: {
      target: 'temporary-public-storage',
    },
  },
}
