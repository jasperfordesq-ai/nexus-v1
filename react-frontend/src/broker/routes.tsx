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

const BrokerDashboardPage = lazy(() => import('./pages/BrokerDashboardPage'));
const MembersPage = lazy(() => import('./pages/MembersPage'));
const OnboardingPage = lazy(() => import('./pages/OnboardingPage'));
const SafeguardingPage = lazy(() => import('./pages/SafeguardingPage'));
const VettingPage = lazy(() => import('./pages/VettingPage'));
const ExchangesPage = lazy(() => import('./pages/ExchangesPage'));
const MessageReviewPage = lazy(() => import('./pages/MessageReviewPage'));

function Lazy({ children }: { children: React.ReactNode }) {
  return <Suspense fallback={<LoadingScreen />}>{children}</Suspense>;
}

export function BrokerRoutes() {
  return (
    <>
      <Route index element={<Lazy><BrokerDashboardPage /></Lazy>} />
      <Route path="members" element={<Lazy><MembersPage /></Lazy>} />
      <Route path="onboarding" element={<Lazy><OnboardingPage /></Lazy>} />
      <Route path="safeguarding" element={<Lazy><SafeguardingPage /></Lazy>} />
      <Route path="vetting" element={<Lazy><VettingPage /></Lazy>} />
      <Route path="exchanges" element={<Lazy><ExchangesPage /></Lazy>} />
      <Route path="messages" element={<Lazy><MessageReviewPage /></Lazy>} />
      <Route path="*" element={<Navigate to="" replace />} />
    </>
  );
}
