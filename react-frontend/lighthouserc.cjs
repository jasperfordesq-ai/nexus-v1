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
      // No preset — presets include hidden error-level individual audit
      // assertions (cache TTL, byte weight, unused JS, etc.) that can fail
      // for a development build. We assert only on category scores.
      assertions: {
        'categories:performance':    ['warn',  { minScore: 0.70 }],
        'categories:accessibility':  ['warn',  { minScore: 0.80 }],
        'categories:best-practices': ['warn',  { minScore: 0.80 }],
        'categories:seo':            ['warn',  { minScore: 0.75 }],
      },
    },
    upload: {
      target: 'temporary-public-storage',
    },
  },
}
