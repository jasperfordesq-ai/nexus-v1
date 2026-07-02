// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Timebanks Panel Routes
 * All pages are lazy-loaded for code splitting. Route-level feature gates
 * mirror the sidebar visibility rules in PartnersSidebar so deep links on
 * a tenant without the feature bounce back to the panel overview.
 */

import { Suspense, lazy } from 'react';
import { Route, Navigate } from 'react-router-dom';
import { LoadingScreen } from '@/components/feedback';
import { useTenant } from '@/contexts';
import type { TenantFeatures } from '@/types';

/**
 * Tenant-aware fallback for unmatched /partner-timebanks/* paths. Without
 * tenantPath() a literal redirect would drop the tenant slug.
 */
function PartnersNotFoundRedirect() {
  const { tenantPath } = useTenant();
  return <Navigate to={tenantPath('/partner-timebanks')} replace />;
}

/**
 * Generic tenant-feature gate for panel sub-pages. The panel guard
 * (PartnersRoute) already ensures the user is a super admin and that at
 * least one partnering surface is enabled; this narrows individual routes
 * to their own feature, matching the sidebar's visibility rules.
 */
function FeatureRoute({ feature, children }: { feature: keyof TenantFeatures; children: React.ReactNode }) {
  const { tenantPath, hasFeature } = useTenant();
  if (!hasFeature(feature)) {
    return <Navigate to={tenantPath('/partner-timebanks')} replace />;
  }
  return <>{children}</>;
}

// Overview (native page — the panel's landing dashboard)
const PartnersOverviewPage = lazy(() => import('./pages/PartnersOverviewPage'));

// Partner network
const PartnershipsPage = lazy(() => import('./pages/PartnershipsPage'));
const PartnerDirectoryPage = lazy(() => import('./pages/PartnerDirectoryPage'));
const NeighborhoodsPage = lazy(() => import('./pages/NeighborhoodsPage'));
const CreditAgreementsPage = lazy(() => import('./pages/CreditAgreementsPage'));

// External connections
const ExternalPartnersPage = lazy(() => import('./pages/ExternalPartnersPage'));
const CreditCommonsPage = lazy(() => import('./pages/CreditCommonsPage'));
const InboundApiPartnersPage = lazy(() => import('./pages/InboundApiPartnersPage'));

// Caring Community protocols (module-gated)
const CaringPeersPage = lazy(() => import('./pages/CaringPeersPage'));

// Access & security
const ApiKeysPage = lazy(() => import('./pages/ApiKeysPage'));
const CreateApiKeyPage = lazy(() => import('./pages/CreateApiKeyPage'));
const WebhooksPage = lazy(() => import('./pages/WebhooksPage'));
const ApiDocsPage = lazy(() => import('./pages/ApiDocsPage'));

// Activity & data
const ActivityFeedPage = lazy(() => import('./pages/ActivityFeedPage'));
const AnalyticsPage = lazy(() => import('./pages/AnalyticsPage'));
const AggregatesPage = lazy(() => import('./pages/AggregatesPage'));
const DataManagementPage = lazy(() => import('./pages/DataManagementPage'));

// Settings
const NetworkSettingsPage = lazy(() => import('./pages/NetworkSettingsPage'));

function Lazy({ children }: { children: React.ReactNode }) {
  return <Suspense fallback={<LoadingScreen />}>{children}</Suspense>;
}

function Fed({ children }: { children: React.ReactNode }) {
  return <FeatureRoute feature="federation">{children}</FeatureRoute>;
}

export function PartnersRoutes() {
  return (
    <>
      <Route index element={<Lazy><PartnersOverviewPage /></Lazy>} />

      {/* Partner network */}
      <Route path="partnerships" element={<Fed><Lazy><PartnershipsPage /></Lazy></Fed>} />
      {/* Our-listing editing lives on the directory page's "profile" tab. */}
      <Route path="directory" element={<Fed><Lazy><PartnerDirectoryPage /></Lazy></Fed>} />
      <Route path="neighborhoods" element={<Fed><Lazy><NeighborhoodsPage /></Lazy></Fed>} />
      <Route path="credit-agreements" element={<Fed><Lazy><CreditAgreementsPage /></Lazy></Fed>} />

      {/* External connections */}
      <Route path="external-partners" element={<Fed><Lazy><ExternalPartnersPage /></Lazy></Fed>} />
      <Route path="credit-commons" element={<Fed><Lazy><CreditCommonsPage /></Lazy></Fed>} />
      <Route
        path="inbound-api"
        element={<FeatureRoute feature="partner_api"><Lazy><InboundApiPartnersPage /></Lazy></FeatureRoute>}
      />

      {/* Caring Community protocols — hidden entirely when the module is off */}
      <Route
        path="caring/peers"
        element={<FeatureRoute feature="caring_community"><Lazy><CaringPeersPage /></Lazy></FeatureRoute>}
      />

      {/* Access & security */}
      <Route path="api-keys" element={<Fed><Lazy><ApiKeysPage /></Lazy></Fed>} />
      <Route path="api-keys/create" element={<Fed><Lazy><CreateApiKeyPage /></Lazy></Fed>} />
      <Route path="webhooks" element={<Fed><Lazy><WebhooksPage /></Lazy></Fed>} />
      <Route path="api-docs" element={<Fed><Lazy><ApiDocsPage /></Lazy></Fed>} />

      {/* Activity & data */}
      <Route path="activity" element={<Fed><Lazy><ActivityFeedPage /></Lazy></Fed>} />
      <Route path="analytics" element={<Fed><Lazy><AnalyticsPage /></Lazy></Fed>} />
      <Route path="aggregates" element={<Fed><Lazy><AggregatesPage /></Lazy></Fed>} />
      <Route path="data" element={<Fed><Lazy><DataManagementPage /></Lazy></Fed>} />

      {/* Settings */}
      <Route path="settings" element={<Fed><Lazy><NetworkSettingsPage /></Lazy></Fed>} />

      {/* Unknown sub-route → bounce back to the panel overview. */}
      <Route path="*" element={<PartnersNotFoundRedirect />} />
    </>
  );
}
