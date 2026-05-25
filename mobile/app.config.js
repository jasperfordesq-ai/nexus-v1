// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
//
// Dynamic Expo config — evaluated at build time by EAS.
// Static fields live in app.json; this file overrides/extends them with
// environment-specific values (API URL, tenant, EAS project ID).
//
// Setup notes:
//   1. Run `eas init` once to get your EXPO_PROJECT_ID from expo.dev.
//   2. Set EAS_PROJECT_ID in your local .env.local (gitignored) and in EAS
//      secrets (eas secret:create --name EAS_PROJECT_ID --value <id>).
//   3. The OTA update URL is https://u.expo.dev/<EAS_PROJECT_ID> — set this
//      in app.json once you have the real ID.

const appJson = require('./app.json');

module.exports = () => {
  const projectId = process.env.EAS_PROJECT_ID ?? null;

  return {
    ...appJson.expo,
    extra: {
      ...(appJson.expo.extra ?? {}),
      apiUrl: process.env.EXPO_PUBLIC_API_URL ?? 'https://api.project-nexus.ie',
      defaultTenant: process.env.EXPO_PUBLIC_DEFAULT_TENANT ?? 'hour-timebank',
      // EAS project ID — required for EAS Update (OTA) and push notifications.
      // Populated from the EAS_PROJECT_ID environment variable.
      ...(projectId ? { eas: { projectId } } : {}),
    },
    // Override the OTA update URL with the real project ID when available.
    ...(projectId
      ? {
          updates: {
            ...appJson.expo.updates,
            url: `https://u.expo.dev/${projectId}`,
          },
        }
      : {}),
  };
};
