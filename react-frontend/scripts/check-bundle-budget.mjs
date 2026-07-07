// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { gzipSync } from 'node:zlib';
import { readFile, readdir } from 'node:fs/promises';
import path from 'node:path';

const distDir = path.resolve(import.meta.dirname, '..', 'dist', 'assets');
const indexHtmlPath = path.resolve(import.meta.dirname, '..', 'dist', 'index.html');

const budgets = {
  mainJsGzipBytes: 220 * 1024,
  mainCssGzipBytes: 100 * 1024,
};

const startupImportBudgets = [
  {
    file: 'src/App.tsx',
    pattern: /from ['"]@\/components\/ui['"]/,
    message: 'App.tsx must import startup UI pieces directly, not through the full @/components/ui barrel.',
  },
  {
    file: 'src/main.tsx',
    pattern: /from ['"]@\/lib\/sentry['"]/,
    message: 'main.tsx must lazy-load the Sentry wrapper after first paint/idle instead of importing it on the startup path.',
  },
  {
    file: 'src/main.tsx',
    pattern: /\binitSentry\(/,
    message: 'main.tsx must not initialize Sentry directly before render; use idle-after-mount telemetry loading.',
  },
  {
    file: 'src/pages/auth/LoginPage.tsx',
    pattern: /from ['"]@\/components\/ui['"]/,
    message: 'LoginPage.tsx must not import the full @/components/ui barrel on the auth startup path.',
  },
  {
    file: 'src/pages/auth/RegisterPage.tsx',
    pattern: /from ['"]@\/components\/ui['"]/,
    message: 'RegisterPage.tsx must not import the full @/components/ui barrel on the auth startup path.',
  },
  {
    file: 'src/pages/auth/RegisterPage.tsx',
    pattern: /from ['"]@\/components\/location['"]/,
    message: 'RegisterPage.tsx must lazy-load location autocomplete instead of importing the Google Maps/location barrel on the auth startup path.',
  },
  {
    file: 'src/pages/auth/ForgotPasswordPage.tsx',
    pattern: /from ['"]@\/components\/ui['"]/,
    message: 'ForgotPasswordPage.tsx must not import the full @/components/ui barrel on the auth startup path.',
  },
  {
    file: 'src/pages/auth/ResetPasswordPage.tsx',
    pattern: /from ['"]@\/components\/ui['"]/,
    message: 'ResetPasswordPage.tsx must not import the full @/components/ui barrel on the auth startup path.',
  },
  {
    file: 'src/pages/auth/VerifyEmailPage.tsx',
    pattern: /from ['"]@\/components\/ui['"]/,
    message: 'VerifyEmailPage.tsx must not import the full @/components/ui barrel on the auth startup path.',
  },
  {
    file: 'src/pages/auth/VerifyIdentityPage.tsx',
    pattern: /from ['"]@\/components\/ui['"]/,
    message: 'VerifyIdentityPage.tsx must not import the full @/components/ui barrel on the auth startup path.',
  },
  {
    file: 'src/pages/auth/OauthCallbackPage.tsx',
    pattern: /from ['"]@\/components\/ui['"]/,
    message: 'OauthCallbackPage.tsx must not import the full @/components/ui barrel on the auth startup path.',
  },
  {
    file: 'src/pages/settings/VerifyIdentityOptionalPage.tsx',
    pattern: /from ['"]@\/components\/ui['"]/,
    message: 'VerifyIdentityOptionalPage.tsx must not import the full @/components/ui barrel on the identity startup path.',
  },
  {
    file: 'src/components/LanguageSwitcher.tsx',
    pattern: /from ['"]@\/components\/ui['"]/,
    message: 'LanguageSwitcher.tsx renders on auth pages, so it must avoid the full @/components/ui barrel.',
  },
  {
    file: 'src/components/layout/SourceRepositoryLink.tsx',
    pattern: /from ['"]@\/components\/ui['"]/,
    message: 'SourceRepositoryLink.tsx renders in the auth footer, so it must avoid the full @/components/ui barrel.',
  },
  {
    file: 'src/components/routing/TenantShell.tsx',
    pattern: /from ['"]@\/components\/ui['"]/,
    message: 'TenantShell.tsx is on every route startup path, so it must avoid the full @/components/ui barrel.',
  },
  {
    file: 'src/admin/AdminApp.tsx',
    pattern: /useTranslation\([^)]*['"]admin['"]/,
    message: 'AdminApp.tsx must not preload the monolithic admin locale namespace.',
  },
  {
    file: 'src/admin/AdminLayout.tsx',
    pattern: /useTranslation\([^)]*['"]admin['"]/,
    message: 'AdminLayout.tsx must not preload the monolithic admin locale namespace.',
  },
  {
    file: 'src/admin/components/AdminBreadcrumbs.tsx',
    pattern: /useTranslation\([^)]*['"]admin['"]/,
    message: 'AdminBreadcrumbs.tsx must use admin_nav, not the monolithic admin namespace.',
  },
  {
    file: 'src/super-admin/SuperAdminApp.tsx',
    pattern: /useTranslation\([^)]*['"]admin['"]/,
    message: 'SuperAdminApp.tsx must not preload the monolithic admin locale namespace.',
  },
];

const criticalPublicImportDirs = [
  'src/pages/public',
  'src/pages/about',
  'src/pages/help',
  'src/components/legal',
  'src/pages/listings',
  'src/pages/blog',
  'src/pages/explore',
  'src/pages/marketplace',
  'src/components/marketplace',
  'src/pages/activity',
  'src/pages/feed',
  'src/pages/group-exchanges',
  'src/pages/matches',
  'src/pages/skills',
  'src/pages/volunteering',
  'src/components/endorsements',
  'src/components/feed',
  'src/components/hashtags',
  'src/pages/bookmarks',
  'src/pages/federation',
  'src/pages/kb',
  'src/pages/onboarding',
  'src/pages/resources',
  'src/pages/advertise',
  'src/pages/caring-community',
  'src/pages/clubs',
  'src/pages/ideation',
  'src/pages/organisations',
  'src/pages/settings',
  'src/pages/jobs',
  'src/components/jobs',
  'src/pages/goals',
  'src/pages/polls',
  'src/pages/groups',
  'src/components/ideation',
  'src/pages/errors',
  'src/pages/exchanges',
  'src/pages/leaderboard',
  'src/pages/achievements',
  'src/pages/nexus-score',
  'src/components/landing',
  'src/pages/dashboard',
  'src/components/listings',
  'src/pages/messages',
  'src/components/wallet',
  'src/pages/wallet',
  'src/components/profile',
  'src/pages/profile',
  'src/components/availability',
  'src/components/search',
  'src/pages/search',
  'src/pages/notifications',
  'src/pages/members',
  'src/pages/events',
];

async function sourceFilesIn(relativeDir) {
  const dir = path.resolve(import.meta.dirname, '..', relativeDir);
  const entries = await readdir(dir, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    const relativePath = `${relativeDir}/${entry.name}`;
    if (entry.isDirectory()) {
      files.push(...await sourceFilesIn(relativePath));
      continue;
    }

    if (/\.(tsx?|jsx?)$/.test(entry.name) && !/\.(test|spec)\.(tsx?|jsx?)$/.test(entry.name)) {
      files.push(relativePath);
    }
  }

  return files;
}

function formatKiB(bytes) {
  return `${(bytes / 1024).toFixed(1)} KiB`;
}

async function gzipSize(filePath) {
  const contents = await readFile(filePath);
  return gzipSync(contents).byteLength;
}

async function main() {
  const html = await readFile(indexHtmlPath, 'utf8');
  const mainJs = html.match(/<script[^>]+type="module"[^>]+src="\/assets\/([^"]+\.js)"/)?.[1];
  const mainCss = html.match(/<link[^>]+rel="stylesheet"[^>]+href="\/assets\/([^"]+\.css)"/)?.[1];
  const failures = [];

  if (!mainJs) {
    failures.push('Could not find the module entry script in dist/index.html.');
  } else {
    const size = await gzipSize(path.join(distDir, mainJs));
    if (size > budgets.mainJsGzipBytes) {
      failures.push(`${mainJs} gzip size ${formatKiB(size)} exceeds ${formatKiB(budgets.mainJsGzipBytes)}.`);
    }
  }

  if (!mainCss) {
    failures.push('Could not find the stylesheet entry in dist/index.html.');
  } else {
    const size = await gzipSize(path.join(distDir, mainCss));
    if (size > budgets.mainCssGzipBytes) {
      failures.push(`${mainCss} gzip size ${formatKiB(size)} exceeds ${formatKiB(budgets.mainCssGzipBytes)}.`);
    }
  }

  for (const budget of startupImportBudgets) {
    const source = await readFile(path.resolve(import.meta.dirname, '..', budget.file), 'utf8');
    if (budget.pattern.test(source)) {
      failures.push(`${budget.file}: ${budget.message}`);
    }
  }

  for (const dir of criticalPublicImportDirs) {
    for (const file of await sourceFilesIn(dir)) {
      const source = await readFile(path.resolve(import.meta.dirname, '..', file), 'utf8');
      if (/from ['"]@\/components\/ui['"]/.test(source)) {
        failures.push(`${file}: performance-critical route surfaces must import UI pieces directly instead of through @/components/ui.`);
      }
    }
  }

  for (const file of await sourceFilesIn('src')) {
    const source = await readFile(path.resolve(import.meta.dirname, '..', file), 'utf8');
    if (file !== 'src/lib/sentry.ts' && /from ['"]@\/lib\/sentry['"]/.test(source)) {
      failures.push(`${file}: import the Sentry wrapper lazily so telemetry cannot re-enter startup chunks.`);
    }
    if (file !== 'src/lib/sentry.ts' && /from ['"]@sentry\/react['"]/.test(source)) {
      failures.push(`${file}: do not import @sentry/react directly; route telemetry through the lazy Sentry wrapper.`);
    }
  }

  if (failures.length > 0) {
    console.error('[bundle-budget] failed');
    for (const failure of failures) {
      console.error(`- ${failure}`);
    }
    process.exit(1);
  }

  console.log('[bundle-budget] passed');
}

main().catch((error) => {
  console.error('[bundle-budget] failed to inspect dist assets');
  console.error(error);
  process.exit(1);
});
