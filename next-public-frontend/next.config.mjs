// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = dirname(fileURLToPath(import.meta.url));

// The repo root: the shared presentational core lives under react-frontend/, a
// sibling of this app. Turbopack only resolves files inside its root, so the
// root is widened to the repo so the @nexus/public-shared alias can reach across.
const repoRoot = resolve(projectRoot, '..');

// Shared public-frontend presentational core lives in the React app's source
// tree; both apps resolve it via the @nexus/public-shared alias so the SAME
// files render in the Vite SPA and this Next.js SSR app (one source of truth).
const publicShared = resolve(projectRoot, '../react-frontend/src/public-shared');

// Singletons pinned to THIS app's node_modules. The shared files physically sit
// under react-frontend/ (which has its own node_modules), so without pinning a
// shared import of react/@heroui could resolve a second copy → duplicate-React
// hook/context crashes. Pinning guarantees one instance across the boundary.
const nm = (pkg) => resolve(projectRoot, 'node_modules', pkg).replace(/\\/g, '/');

/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'standalone',
  poweredByHeader: false,
  reactStrictMode: true,
  outputFileTracingRoot: repoRoot,
  turbopack: {
    root: repoRoot,
    resolveAlias: {
      '@nexus/public-shared': publicShared,
    },
  },
};

export default nextConfig;
