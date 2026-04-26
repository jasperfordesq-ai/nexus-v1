// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Panel Routes
 * All broker pages are lazy-loaded for code splitting.
 */

import { Suspense, lazy } from 'react';
import { Route, Navigate } from 'react-router-dom';
import { LoadingScreen } from '@/components/feedback';
import { useTenant } from '@/contexts';

/**
 * Tenant-aware fallback for unmatched /broker/* paths. Without using
 * tenantPath() here a literal `/broker` redirect would drop the tenant
 * slug; with relative `to=""` React Router preserves the current path
 * and never resolves the unmatched segment.
 */
function BrokerNotFoundRedirect() {
  const { tenantPath } = useTenant();
  return <Navigate to={tenantPath('/broker')} replace />;
}

// Core daily-workflow pages
const BrokerDashboardPage = lazy(() => import('./pages/BrokerDashboardPage'));
const MembersPage = lazy(() => import('./pages/MembersPage'));
const OnboardingPage = lazy(() => import('./pages/OnboardingPage'));
const SafeguardingPage = lazy(() => import('./pages/SafeguardingPage'));
const VettingPage = lazy(() => import('./pages/VettingPage'));
const ExchangesPage = lazy(() => import('./pages/ExchangesPage'));
const MessageReviewPage = lazy(() => import('./pages/MessageReviewPage'));

// Detail / drill-down pages
const ExchangeDetailPage = lazy(() => import('./pages/ExchangeDetailPage'));
const MessageDetailPage = lazy(() => import('./pages/MessageDetailPage'));
const ArchiveDetailPage = lazy(() => import('./pages/ArchiveDetailPage'));

// Compliance & oversight pages (ported from admin/broker-controls)
const RiskTagsPage = lazy(() => import('./pages/RiskTagsPage'));
const UserMonitoringPage = lazy(() => import('./pages/UserMonitoringPage'));
const InsuranceCertificatesPage = lazy(() => import('./pages/InsuranceCertificatesPage'));
const ReviewArchivePage = lazy(() => import('./pages/ReviewArchivePage'));

// Settings & help
const BrokerConfigurationPage = lazy(() => import('./pages/BrokerConfigurationPage'));
const BrokerHelpPage = lazy(() => import('./pages/BrokerHelpPage'));

function Lazy({ children }: { children: React.ReactNode }) {
  return <Suspense fallback={<LoadingScreen />}>{children}</Suspense>;
}

export function BrokerRoutes() {
  return (
    <>
      <Route index element={<Lazy><BrokerDashboardPage /></Lazy>} />

      {/* Daily workflow */}
      <Route path="members" element={<Lazy><MembersPage /></Lazy>} />
      <Route path="onboarding" element={<Lazy><OnboardingPage /></Lazy>} />
      <Route path="safeguarding" element={<Lazy><SafeguardingPage /></Lazy>} />

      {/* Exchanges */}
      <Route path="exchanges" element={<Lazy><ExchangesPage /></Lazy>} />
      <Route path="exchanges/:id" element={<Lazy><ExchangeDetailPage /></Lazy>} />

      {/* Messages */}
      <Route path="messages" element={<Lazy><MessageReviewPage /></Lazy>} />
      <Route path="messages/:id" element={<Lazy><MessageDetailPage /></Lazy>} />

      {/* Vetting */}
      <Route path="vetting" element={<Lazy><VettingPage /></Lazy>} />

      {/* Compliance & oversight */}
      <Route path="monitoring" element={<Lazy><UserMonitoringPage /></Lazy>} />
      <Route path="risk-tags" element={<Lazy><RiskTagsPage /></Lazy>} />
      <Route path="insurance" element={<Lazy><InsuranceCertificatesPage /></Lazy>} />

      {/* Records */}
      <Route path="archives" element={<Lazy><ReviewArchivePage /></Lazy>} />
      <Route path="archives/:id" element={<Lazy><ArchiveDetailPage /></Lazy>} />

      {/* Settings & help */}
      <Route path="configuration" element={<Lazy><BrokerConfigurationPage /></Lazy>} />
      <Route path="help" element={<Lazy><BrokerHelpPage /></Lazy>} />

      {/* Unknown sub-route under /broker → bounce back to the broker dashboard. */}
      <Route path="*" element={<BrokerNotFoundRedirect />} />
    </>
  );
}
