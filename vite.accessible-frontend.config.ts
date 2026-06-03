// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { defineConfig } from 'vite';
import path from 'path';

const laravelOrigin = (process.env.ACCESSIBLE_FRONTEND_LARAVEL_ORIGIN || process.env.APP_URL || 'http://127.0.0.1:8088')
  .replace(/\/+$/, '');

const pageRoutePattern = /^\/([a-zA-Z0-9_-]+)(\/alpha(?:\/.*)?|\/?)$/;

export default defineConfig({
  build: {
    outDir: 'httpdocs/build/accessible-frontend',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        app: path.resolve(__dirname, 'accessible-frontend/src/app.ts'),
      },
    },
  },
  plugins: [
    {
      name: 'nexus-accessible-frontend-dev-redirects',
      apply: 'serve',
      configureServer(server) {
        server.middlewares.use((req, res, next) => {
          if (!req.url || !['GET', 'HEAD'].includes(req.method ?? 'GET')) {
            next();
            return;
          }

          const requestUrl = new URL(req.url, 'http://127.0.0.1');
          if (requestUrl.pathname === '/') {
            res.statusCode = 302;
            res.setHeader('Location', `${laravelOrigin}/`);
            res.end();
            return;
          }

          const match = requestUrl.pathname.match(pageRoutePattern);
          if (!match) {
            next();
            return;
          }

          const tenantSlug = match[1];
          const alphaPath = match[2].startsWith('/alpha') ? match[2] : '/alpha';
          res.statusCode = 302;
          res.setHeader('Location', `${laravelOrigin}/${tenantSlug}${alphaPath}${requestUrl.search}`);
          res.end();
        });
      },
    },
  ],
  css: {
    preprocessorOptions: {
      scss: {
        quietDeps: true,
        silenceDeprecations: ['import', 'global-builtin', 'color-functions'],
      },
    },
  },
});
