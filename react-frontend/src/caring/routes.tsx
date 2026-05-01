// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Suspense, lazy } from 'react';
import { Route, Navigate } from 'react-router-dom';
import { LoadingScreen } from '@/components/feedback';
import { useTenant } from '@/contexts';

function CaringNotFoundRedirect() {
  const { tenantPath } = useTenant();
  return <Navigate to={tenantPath('/caring')} replace />;
}

// Overview
const CaringCommunityAdmin = lazy(() => import('@/admin/modules/caring-community/CaringCommunityAdmin'));
const CaringCommunityWorkflowPage = lazy(() => import('@/admin/modules/caring-community/CaringCommunityWorkflowPage'));
const ProjectAnnouncementsAdminPage = lazy(() => import('@/admin/modules/caring-community/ProjectAnnouncementsAdminPage'));

// Operations
const LoyaltyAdminPage = lazy(() => import('@/admin/modules/caring-community/LoyaltyAdminPage'));
const HourTransferAdminPage = lazy(() => import('@/admin/modules/caring-community/HourTransferAdminPage'));
const RegionalPointsAdminPage = lazy(() => import('@/admin/modules/caring-community/RegionalPointsAdminPage'));
const SubRegionsAdminPage = lazy(() => import('@/admin/modules/caring-community/SubRegionsAdminPage'));
const FederationPeersAdminPage = lazy(() => import('@/admin/modules/caring-community/FederationPeersAdminPage'));
const HelpRequestSlaAdminPage = lazy(() => import('@/admin/modules/caring-community/HelpRequestSlaAdminPage'));
const CareProviderAdminPage = lazy(() => import('@/admin/modules/caring-community/CareProviderAdminPage'));
const WarmthPassAdminPage = lazy(() => import('@/admin/modules/caring-community/WarmthPassAdminPage'));
const CareRecipientCirclePage = lazy(() => import('@/admin/modules/caring-community/CareRecipientCirclePage'));

// Engagement
const SmartNudgesAdminPage = lazy(() => import('@/admin/modules/caring-community/SmartNudgesAdminPage'));
const EmergencyAlertAdminPage = lazy(() => import('@/admin/modules/caring-community/EmergencyAlertAdminPage'));
const MunicipalSurveyAdminPage = lazy(() => import('@/admin/modules/caring-community/MunicipalSurveyAdminPage'));
const MunicipalCopilotAdminPage = lazy(() => import('@/admin/modules/caring-community/MunicipalCopilotAdminPage'));
const CivicDigestAdminPage = lazy(() => import('@/admin/modules/caring-community/CivicDigestAdminPage'));
const LeadNurtureAdminPage = lazy(() => import('@/admin/modules/caring-community/LeadNurtureAdminPage'));
const SuccessStoryAdminPage = lazy(() => import('@/admin/modules/caring-community/SuccessStoryAdminPage'));
const MunicipalityFeedbackAdminPage = lazy(() => import('@/admin/modules/caring-community/MunicipalityFeedbackAdminPage'));

// Trust & Safety
const MunicipalVerificationAdminPage = lazy(() => import('@/admin/modules/caring-community/MunicipalVerificationAdminPage'));
const SafeguardingReportsAdminPage = lazy(() => import('@/admin/modules/caring-community/SafeguardingReportsAdminPage'));
const TrustTierAdminPage = lazy(() => import('@/admin/modules/caring-community/TrustTierAdminPage'));

// Pilot Governance
const PilotLaunchReadinessAdminPage = lazy(() => import('@/admin/modules/caring-community/PilotLaunchReadinessAdminPage'));
const PilotScoreboardAdminPage = lazy(() => import('@/admin/modules/caring-community/PilotScoreboardAdminPage'));
const DataQualityAdminPage = lazy(() => import('@/admin/modules/caring-community/DataQualityAdminPage'));
const OperatingPolicyAdminPage = lazy(() => import('@/admin/modules/caring-community/OperatingPolicyAdminPage'));
const DisclosurePackAdminPage = lazy(() => import('@/admin/modules/caring-community/DisclosurePackAdminPage'));
const CommercialBoundaryAdminPage = lazy(() => import('@/admin/modules/caring-community/CommercialBoundaryAdminPage'));
const IsolatedNodeAdminPage = lazy(() => import('@/admin/modules/caring-community/IsolatedNodeAdminPage'));

// Partnerships
const ResearchPartnershipsAdminPage = lazy(() => import('@/admin/modules/caring-community/ResearchPartnershipsAdminPage'));
const ExternalIntegrationsAdminPage = lazy(() => import('@/admin/modules/caring-community/ExternalIntegrationsAdminPage'));
const IntegrationShowcaseAdminPage = lazy(() => import('@/admin/modules/caring-community/IntegrationShowcaseAdminPage'));

// Reporting
const KpiBaselineAdminPage = lazy(() => import('@/admin/modules/caring-community/KpiBaselineAdminPage'));
const MunicipalRoiAdminPage = lazy(() => import('@/admin/modules/caring-community/MunicipalRoiAdminPage'));
const CategoryCoefficientsAdminPage = lazy(() => import('@/admin/modules/caring-community/CategoryCoefficientsAdminPage'));

// Municipal Impact — previously at /admin/reports/municipal-impact
const MunicipalImpactReportsPage = lazy(() => import('@/admin/modules/reports/MunicipalImpactReportsPage'));

function Lazy({ children }: { children: React.ReactNode }) {
  return <Suspense fallback={<LoadingScreen />}>{children}</Suspense>;
}

export function CaringRoutes() {
  return (
    <>
      {/* Overview */}
      <Route index element={<Lazy><CaringCommunityAdmin /></Lazy>} />
      <Route path="workflow" element={<Lazy><CaringCommunityWorkflowPage /></Lazy>} />
      <Route path="projects" element={<Lazy><ProjectAnnouncementsAdminPage /></Lazy>} />

      {/* Operations */}
      <Route path="loyalty" element={<Lazy><LoyaltyAdminPage /></Lazy>} />
      <Route path="hour-transfers" element={<Lazy><HourTransferAdminPage /></Lazy>} />
      <Route path="regional-points" element={<Lazy><RegionalPointsAdminPage /></Lazy>} />
      <Route path="sub-regions" element={<Lazy><SubRegionsAdminPage /></Lazy>} />
      <Route path="federation-peers" element={<Lazy><FederationPeersAdminPage /></Lazy>} />
      <Route path="sla-dashboard" element={<Lazy><HelpRequestSlaAdminPage /></Lazy>} />
      <Route path="providers" element={<Lazy><CareProviderAdminPage /></Lazy>} />
      <Route path="warmth-pass" element={<Lazy><WarmthPassAdminPage /></Lazy>} />
      <Route path="warmth-pass/:userId" element={<Lazy><WarmthPassAdminPage /></Lazy>} />
      <Route path="recipient-circle" element={<Lazy><CareRecipientCirclePage /></Lazy>} />

      {/* Engagement */}
      <Route path="nudges" element={<Lazy><SmartNudgesAdminPage /></Lazy>} />
      <Route path="emergency-alerts" element={<Lazy><EmergencyAlertAdminPage /></Lazy>} />
      <Route path="surveys" element={<Lazy><MunicipalSurveyAdminPage /></Lazy>} />
      <Route path="copilot" element={<Lazy><MunicipalCopilotAdminPage /></Lazy>} />
      <Route path="civic-digest" element={<Lazy><CivicDigestAdminPage /></Lazy>} />
      <Route path="lead-nurture" element={<Lazy><LeadNurtureAdminPage /></Lazy>} />
      <Route path="success-stories" element={<Lazy><SuccessStoryAdminPage /></Lazy>} />
      <Route path="feedback" element={<Lazy><MunicipalityFeedbackAdminPage /></Lazy>} />

      {/* Trust & Safety */}
      <Route path="verification" element={<Lazy><MunicipalVerificationAdminPage /></Lazy>} />
      <Route path="safeguarding" element={<Lazy><SafeguardingReportsAdminPage /></Lazy>} />
      <Route path="trust-tier" element={<Lazy><TrustTierAdminPage /></Lazy>} />

      {/* Pilot Governance */}
      <Route path="launch-readiness" element={<Lazy><PilotLaunchReadinessAdminPage /></Lazy>} />
      <Route path="pilot-scoreboard" element={<Lazy><PilotScoreboardAdminPage /></Lazy>} />
      <Route path="data-quality" element={<Lazy><DataQualityAdminPage /></Lazy>} />
      <Route path="operating-policy" element={<Lazy><OperatingPolicyAdminPage /></Lazy>} />
      <Route path="disclosure-pack" element={<Lazy><DisclosurePackAdminPage /></Lazy>} />
      <Route path="commercial-boundary" element={<Lazy><CommercialBoundaryAdminPage /></Lazy>} />
      <Route path="isolated-node" element={<Lazy><IsolatedNodeAdminPage /></Lazy>} />

      {/* Partnerships */}
      <Route path="research" element={<Lazy><ResearchPartnershipsAdminPage /></Lazy>} />
      <Route path="external-integrations" element={<Lazy><ExternalIntegrationsAdminPage /></Lazy>} />
      <Route path="integration-showcase" element={<Lazy><IntegrationShowcaseAdminPage /></Lazy>} />

      {/* Reporting */}
      <Route path="municipal-impact" element={<Lazy><MunicipalImpactReportsPage /></Lazy>} />
      <Route path="kpi-baselines" element={<Lazy><KpiBaselineAdminPage /></Lazy>} />
      <Route path="municipal-roi" element={<Lazy><MunicipalRoiAdminPage /></Lazy>} />
      <Route path="category-coefficients" element={<Lazy><CategoryCoefficientsAdminPage /></Lazy>} />

      {/* Unknown sub-route → bounce back to caring dashboard */}
      <Route path="*" element={<CaringNotFoundRedirect />} />
    </>
  );
}
