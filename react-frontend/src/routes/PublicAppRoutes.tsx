// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * NEXUS React Frontend - public tenant route registry.
 *
 * This keeps the first-load public route map away from protected member,
 * admin, panel, editor, and seller-tool route declarations.
 */

import { type ReactNode } from 'react';
import { Navigate, Route } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts/TenantContext';
import { CARING_COMMUNITY_ROUTE } from '@/pages/caring-community/config';
import { ErrorBoundary } from '@/components/feedback/ErrorBoundary';
import { FeatureErrorBoundary } from '@/components/feedback/FeatureErrorBoundary';
import { FeatureGate } from '@/components/routing/FeatureGate';
import { lazyWithRetry } from './lazyWithRetry';
import { renderSharedPublicFeatureRoutes } from './sharedPublicFeatureRoutes';

const Layout = lazyWithRetry(() => import('@/components/layout/Layout'));
const HomePage = lazyWithRetry(() => import('@/pages/public/HomePage'));
const ListingsPage = lazyWithRetry(() => import('@/pages/listings/ListingsPage'));
const ListingDetailPage = lazyWithRetry(() => import('@/pages/listings/ListingDetailPage'));
const EventsPage = lazyWithRetry(() => import('@/pages/events/EventsPage'));
const EventDetailPage = lazyWithRetry(() => import('@/pages/events/EventDetailPage'));
const GroupsPage = lazyWithRetry(() => import('@/pages/groups/GroupsPage'));
const GroupDetailPage = lazyWithRetry(() => import('@/pages/groups/GroupDetailPage'));
const JobsPage = lazyWithRetry(() => import('@/pages/jobs/JobsPage'));
const JobDetailPage = lazyWithRetry(() => import('@/pages/jobs/JobDetailPage'));
const CoursesPage = lazyWithRetry(() => import('@/pages/courses/CoursesPage'));
const CourseDetailPage = lazyWithRetry(() => import('@/pages/courses/CourseDetailPage'));
const PodcastsPage = lazyWithRetry(() => import('@/pages/podcasts/PodcastsPage'));
const PodcastShowPage = lazyWithRetry(() => import('@/pages/podcasts/PodcastShowPage'));
const PodcastEpisodePage = lazyWithRetry(() => import('@/pages/podcasts/PodcastEpisodePage'));
const MarketplacePage = lazyWithRetry(() => import('@/pages/marketplace/MarketplacePage'));
const MarketplaceListingPage = lazyWithRetry(() => import('@/pages/marketplace/MarketplaceListingPage'));
const MarketplaceSearchPage = lazyWithRetry(() => import('@/pages/marketplace/MarketplaceSearchPage'));
const SellerProfilePage = lazyWithRetry(() => import('@/pages/marketplace/SellerProfilePage'));
const MarketplaceCategoryPage = lazyWithRetry(() => import('@/pages/marketplace/MarketplaceCategoryPage'));
const MyListingsPage = lazyWithRetry(() => import('@/pages/marketplace/MyListingsPage'));
const MyOffersPage = lazyWithRetry(() => import('@/pages/marketplace/MyOffersPage'));
const MarketplaceCollectionsPage = lazyWithRetry(() => import('@/pages/marketplace/MarketplaceCollectionsPage'));
const MarketplaceMapSearchPage = lazyWithRetry(() => import('@/pages/marketplace/MarketplaceMapSearchPage'));
const FreeItemsPage = lazyWithRetry(() => import('@/pages/marketplace/FreeItemsPage'));
const CouponsPage = lazyWithRetry(() => import('@/pages/coupons/CouponsPage'));
const CouponDetailPage = lazyWithRetry(() => import('@/pages/coupons/CouponDetailPage'));
const PricingPage = lazyWithRetry(() => import('@/pages/premium/PricingPage'));
const VolunteeringPage = lazyWithRetry(() => import('@/pages/volunteering/VolunteeringPage'));
const OpportunityDetailPage = lazyWithRetry(() => import('@/pages/volunteering/OpportunityDetailPage'));
const ResourcesPage = lazyWithRetry(() => import('@/pages/resources/ResourcesPage'));
const KnowledgeBasePage = lazyWithRetry(() => import('@/pages/kb/KnowledgeBasePage'));
const KBArticlePage = lazyWithRetry(() => import('@/pages/kb/KBArticlePage'));
const OrganisationsPage = lazyWithRetry(() => import('@/pages/organisations/OrganisationsPage'));
const OrganisationDetailPage = lazyWithRetry(() => import('@/pages/organisations/OrganisationDetailPage'));
const IdeationPage = lazyWithRetry(() => import('@/pages/ideation/IdeationPage'));
const ChallengeDetailPage = lazyWithRetry(() => import('@/pages/ideation/ChallengeDetailPage'));
const IdeaDetailPage = lazyWithRetry(() => import('@/pages/ideation/IdeaDetailPage'));
const CaringCommunityPage = lazyWithRetry(() => import('@/pages/caring-community/CaringCommunityPage'));
const InviteRedemptionPage = lazyWithRetry(() => import('@/pages/caring-community/InviteRedemptionPage'));
const BlogPage = lazyWithRetry(() => import('@/pages/blog/BlogPage'));
const BlogPostPage = lazyWithRetry(() => import('@/pages/blog/BlogPostPage'));
const NewsletterUnsubscribePage = lazyWithRetry(() => import('@/pages/newsletter/NewsletterUnsubscribePage'));
const NotFoundPage = lazyWithRetry(() => import('@/pages/errors/NotFoundPage'));
const ComingSoonPage = lazyWithRetry(() => import('@/pages/errors/ComingSoonPage'));

const FeaturesPage = lazyWithRetry(() => import('@/pages/public/FeaturesPage'));
const ChangelogPage = lazyWithRetry(() => import('@/pages/public/ChangelogPage'));
const AboutPage = lazyWithRetry(() => import('@/pages/public/AboutPage'));
const ContactPage = lazyWithRetry(() => import('@/pages/public/ContactPage'));
const TermsPage = lazyWithRetry(() => import('@/pages/public/TermsPage'));
const PrivacyPage = lazyWithRetry(() => import('@/pages/public/PrivacyPage'));
const AccessibilityPage = lazyWithRetry(() => import('@/pages/public/AccessibilityPage'));
const CookiesPage = lazyWithRetry(() => import('@/pages/public/CookiesPage'));
const CommunityGuidelinesPage = lazyWithRetry(() => import('@/pages/public/CommunityGuidelinesPage'));
const TrustSafetyPage = lazyWithRetry(() => import('@/pages/public/TrustSafetyPage'));
const AcceptableUsePage = lazyWithRetry(() => import('@/pages/public/AcceptableUsePage'));
const LegalHubPage = lazyWithRetry(() => import('@/pages/public/LegalHubPage'));
const LegalVersionHistoryPage = lazyWithRetry(() => import('@/pages/public/LegalVersionHistoryPage'));
const FaqPage = lazyWithRetry(() => import('@/pages/public/FaqPage'));
const HelpCenterPage = lazyWithRetry(() => import('@/pages/help/HelpCenterPage'));
const PilotInquiryPage = lazyWithRetry(() => import('@/pages/public/PilotInquiryPage'));
const PilotApplyPage = lazyWithRetry(() => import('@/pages/public/PilotApplyPage'));
const PilotApplyStatusPage = lazyWithRetry(() => import('@/pages/public/PilotApplyStatusPage'));
const PlatformTermsPage = lazyWithRetry(() => import('@/pages/platform/PlatformTermsPage'));
const PlatformPrivacyPage = lazyWithRetry(() => import('@/pages/platform/PlatformPrivacyPage'));
const PlatformDisclaimerPage = lazyWithRetry(() => import('@/pages/platform/PlatformDisclaimerPage'));
const CustomPage = lazyWithRetry(() => import('@/pages/public/CustomPage'));
const TimebankingGuidePage = lazyWithRetry(() => import('@/pages/about/TimebankingGuidePage'));
const DevelopersHomePage = lazyWithRetry(() => import('@/pages/developers/DevelopersHomePage'));
const DevelopersAuthPage = lazyWithRetry(() => import('@/pages/developers/DevelopersAuthPage'));
const DevelopersEndpointsPage = lazyWithRetry(() => import('@/pages/developers/DevelopersEndpointsPage'));
const DevelopersWebhooksPage = lazyWithRetry(() => import('@/pages/developers/DevelopersWebhooksPage'));
const RegionalAnalyticsLandingPage = lazyWithRetry(() => import('@/pages/public/RegionalAnalyticsLandingPage'));
const PartnerDashboardPage = lazyWithRetry(() => import('@/pages/partner-analytics/PartnerDashboardPage'));
const PartnerPage = lazyWithRetry(() => import('@/pages/about/PartnerPage'));
const SocialPrescribingPage = lazyWithRetry(() => import('@/pages/about/SocialPrescribingPage'));
const ImpactSummaryPage = lazyWithRetry(() => import('@/pages/about/ImpactSummaryPage'));
const ImpactReportPage = lazyWithRetry(() => import('@/pages/about/ImpactReportPage'));
const StrategicPlanPage = lazyWithRetry(() => import('@/pages/about/StrategicPlanPage'));

function TenantSlugGate({ slug, children }: { slug: string; children: ReactNode }) {
  const { tenant } = useTenant();
  if (tenant?.slug !== slug) {
    return <Navigate to="about" replace />;
  }
  return <>{children}</>;
}

export function PublicAppRoutes() {
  const { t } = useTranslation(['utility', 'common']);
  const label = (key: string) => String(t(`coming_soon.features.${key}`));
  const navLabel = (key: string) => String(t(`common:nav.${key}`));

  return (
    <>
      <Route element={<Layout />}>
        <Route index element={<ErrorBoundary><HomePage /></ErrorBoundary>} />
        <Route path="features" element={<ErrorBoundary><FeaturesPage /></ErrorBoundary>} />
        <Route path="changelog" element={<ErrorBoundary><ChangelogPage /></ErrorBoundary>} />
        <Route path="development-status" element={<Navigate to="../features" replace />} />
        <Route path="about" element={<ErrorBoundary><AboutPage /></ErrorBoundary>} />
        <Route path="faq" element={<ErrorBoundary><FaqPage /></ErrorBoundary>} />
        <Route path="contact" element={<ErrorBoundary><ContactPage /></ErrorBoundary>} />
        <Route path="pilot-inquiry" element={<ErrorBoundary><PilotInquiryPage /></ErrorBoundary>} />
        <Route path="pilot-apply" element={<ErrorBoundary><PilotApplyPage /></ErrorBoundary>} />
        <Route path="pilot-apply/status/:token" element={<ErrorBoundary><PilotApplyStatusPage /></ErrorBoundary>} />
        <Route path="help" element={<ErrorBoundary><HelpCenterPage /></ErrorBoundary>} />
        <Route path="terms" element={<ErrorBoundary><TermsPage /></ErrorBoundary>} />
        <Route path="terms/versions" element={<ErrorBoundary><LegalVersionHistoryPage /></ErrorBoundary>} />
        <Route path="privacy" element={<ErrorBoundary><PrivacyPage /></ErrorBoundary>} />
        <Route path="privacy/versions" element={<ErrorBoundary><LegalVersionHistoryPage /></ErrorBoundary>} />
        <Route path="accessibility" element={<ErrorBoundary><AccessibilityPage /></ErrorBoundary>} />
        <Route path="accessibility/versions" element={<ErrorBoundary><LegalVersionHistoryPage /></ErrorBoundary>} />
        <Route path="cookies" element={<ErrorBoundary><CookiesPage /></ErrorBoundary>} />
        <Route path="cookies/versions" element={<ErrorBoundary><LegalVersionHistoryPage /></ErrorBoundary>} />
        <Route path="community-guidelines" element={<ErrorBoundary><CommunityGuidelinesPage /></ErrorBoundary>} />
        <Route path="community-guidelines/versions" element={<ErrorBoundary><LegalVersionHistoryPage /></ErrorBoundary>} />
        <Route path="trust-and-safety" element={<ErrorBoundary><TrustSafetyPage /></ErrorBoundary>} />
        <Route path="acceptable-use" element={<ErrorBoundary><AcceptableUsePage /></ErrorBoundary>} />
        <Route path="acceptable-use/versions" element={<ErrorBoundary><LegalVersionHistoryPage /></ErrorBoundary>} />
        <Route path="legal" element={<ErrorBoundary><LegalHubPage /></ErrorBoundary>} />
        <Route path="platform/terms" element={<ErrorBoundary><PlatformTermsPage /></ErrorBoundary>} />
        <Route path="platform/privacy" element={<ErrorBoundary><PlatformPrivacyPage /></ErrorBoundary>} />
        <Route path="platform/disclaimer" element={<ErrorBoundary><PlatformDisclaimerPage /></ErrorBoundary>} />
        <Route path="timebanking-guide" element={<ErrorBoundary><TimebankingGuidePage /></ErrorBoundary>} />
        <Route path="developers" element={<ErrorBoundary><DevelopersHomePage /></ErrorBoundary>} />
        <Route path="developers/auth" element={<ErrorBoundary><DevelopersAuthPage /></ErrorBoundary>} />
        <Route path="developers/endpoints" element={<ErrorBoundary><DevelopersEndpointsPage /></ErrorBoundary>} />
        <Route path="developers/webhooks" element={<ErrorBoundary><DevelopersWebhooksPage /></ErrorBoundary>} />
        <Route path="regional-analytics" element={<ErrorBoundary><RegionalAnalyticsLandingPage /></ErrorBoundary>} />
        <Route path="partner-analytics/dashboard" element={<ErrorBoundary><PartnerDashboardPage /></ErrorBoundary>} />
        <Route path="newsletter/unsubscribe" element={<ErrorBoundary><NewsletterUnsubscribePage /></ErrorBoundary>} />
        {renderSharedPublicFeatureRoutes()}
        <Route path="partner" element={<ErrorBoundary><TenantSlugGate slug="hour-timebank"><PartnerPage /></TenantSlugGate></ErrorBoundary>} />
        <Route path="social-prescribing" element={<ErrorBoundary><TenantSlugGate slug="hour-timebank"><SocialPrescribingPage /></TenantSlugGate></ErrorBoundary>} />
        <Route path="impact-summary" element={<ErrorBoundary><TenantSlugGate slug="hour-timebank"><ImpactSummaryPage /></TenantSlugGate></ErrorBoundary>} />
        <Route path="impact-report" element={<ErrorBoundary><TenantSlugGate slug="hour-timebank"><ImpactReportPage /></TenantSlugGate></ErrorBoundary>} />
        <Route path="strategic-plan" element={<ErrorBoundary><TenantSlugGate slug="hour-timebank"><StrategicPlanPage /></TenantSlugGate></ErrorBoundary>} />
        <Route path="page/:slug" element={<ErrorBoundary><CustomPage /></ErrorBoundary>} />

        <Route path="blog" element={<FeatureGate feature="blog" redirect="/"><FeatureErrorBoundary featureName={navLabel('blog')}><BlogPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="blog/:slug" element={<FeatureGate feature="blog" redirect="/"><FeatureErrorBoundary featureName={navLabel('blog')}><BlogPostPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="listings" element={<FeatureGate module="listings" redirect="/"><FeatureErrorBoundary featureName={navLabel('listings')}><ListingsPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="listings/:id" element={<FeatureGate module="listings" redirect="/"><FeatureErrorBoundary featureName={navLabel('listings')}><ListingDetailPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="events" element={<FeatureGate feature="events" fallback={<ComingSoonPage feature={label('events')} />}><FeatureErrorBoundary featureName={label('events')}><EventsPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="events/:id" element={<FeatureGate feature="events" redirect="/"><FeatureErrorBoundary featureName={label('events')}><EventDetailPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="groups" element={<FeatureGate feature="groups" fallback={<ComingSoonPage feature={label('groups')} />}><FeatureErrorBoundary featureName={label('groups')}><GroupsPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="groups/:id" element={<FeatureGate feature="groups" redirect="/"><FeatureErrorBoundary featureName={label('groups')}><GroupDetailPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="jobs" element={<FeatureGate feature="job_vacancies" fallback={<ComingSoonPage feature={label('job_vacancies')} />}><FeatureErrorBoundary featureName={label('job_vacancies')}><JobsPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="jobs/:id" element={<FeatureGate feature="job_vacancies" redirect="/"><FeatureErrorBoundary featureName={label('job_vacancies')}><JobDetailPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="courses" element={<FeatureGate feature="courses" redirect="/"><FeatureErrorBoundary featureName={navLabel('courses')}><CoursesPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="courses/:idOrSlug" element={<FeatureGate feature="courses" redirect="/"><FeatureErrorBoundary featureName={navLabel('courses')}><CourseDetailPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="podcasts" element={<FeatureGate feature="podcasts" redirect="/"><FeatureErrorBoundary featureName={navLabel('podcasts')}><PodcastsPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="podcasts/:showSlug" element={<FeatureGate feature="podcasts" redirect="/"><FeatureErrorBoundary featureName={navLabel('podcasts')}><PodcastShowPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="podcasts/:showSlug/:episodeSlug" element={<FeatureGate feature="podcasts" redirect="/"><FeatureErrorBoundary featureName={navLabel('podcasts')}><PodcastEpisodePage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="marketplace" element={<FeatureGate feature="marketplace" fallback={<ComingSoonPage feature={label('marketplace')} />}><FeatureErrorBoundary featureName={label('marketplace')}><MarketplacePage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="marketplace/search" element={<FeatureGate feature="marketplace" redirect="/"><FeatureErrorBoundary featureName={label('marketplace')}><MarketplaceSearchPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="marketplace/map" element={<FeatureGate feature="marketplace" redirect="/"><FeatureErrorBoundary featureName={label('marketplace')}><MarketplaceMapSearchPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="marketplace/seller/:id" element={<FeatureGate feature="marketplace" redirect="/"><FeatureErrorBoundary featureName={label('marketplace')}><SellerProfilePage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="marketplace/category/:slug" element={<FeatureGate feature="marketplace" redirect="/"><FeatureErrorBoundary featureName={label('marketplace')}><MarketplaceCategoryPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="marketplace/my-listings" element={<FeatureGate feature="marketplace" redirect="/"><FeatureErrorBoundary featureName={label('marketplace')}><MyListingsPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="marketplace/my-offers" element={<FeatureGate feature="marketplace" redirect="/"><FeatureErrorBoundary featureName={label('marketplace')}><MyOffersPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="marketplace/collections" element={<FeatureGate feature="marketplace" redirect="/"><FeatureErrorBoundary featureName={label('marketplace')}><MarketplaceCollectionsPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="marketplace/free" element={<FeatureGate feature="marketplace" fallback={<ComingSoonPage feature={label('marketplace')} />}><FeatureErrorBoundary featureName={label('marketplace')}><FreeItemsPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="marketplace/:id" element={<FeatureGate feature="marketplace" redirect="/"><FeatureErrorBoundary featureName={label('marketplace')}><MarketplaceListingPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="coupons" element={<FeatureGate feature="merchant_coupons" fallback={<ComingSoonPage feature={label('coupons')} />}><FeatureErrorBoundary featureName={label('coupons')}><CouponsPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="coupons/:id" element={<FeatureGate feature="merchant_coupons" redirect="/coupons"><FeatureErrorBoundary featureName={label('coupons')}><CouponDetailPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="pricing" element={<ErrorBoundary><PricingPage /></ErrorBoundary>} />
        <Route path={CARING_COMMUNITY_ROUTE.path} element={<FeatureGate feature={CARING_COMMUNITY_ROUTE.feature} fallback={<ComingSoonPage feature={label('caring_community')} />}><FeatureErrorBoundary featureName={label('caring_community')}><CaringCommunityPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="join/:code" element={<ErrorBoundary><InviteRedemptionPage /></ErrorBoundary>} />
        <Route path="volunteering" element={<FeatureGate feature="volunteering" fallback={<ComingSoonPage feature={label('volunteering')} />}><FeatureErrorBoundary featureName={label('volunteering')}><VolunteeringPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="volunteering/opportunities/:id" element={<FeatureGate feature="volunteering" redirect="/"><FeatureErrorBoundary featureName={label('volunteering')}><OpportunityDetailPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="resources" element={<FeatureGate feature="resources" fallback={<ComingSoonPage feature={label('resources')} />}><FeatureErrorBoundary featureName={label('resources')}><ResourcesPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="kb" element={<FeatureGate feature="resources" fallback={<ComingSoonPage feature={label('knowledge_base')} />}><FeatureErrorBoundary featureName={label('knowledge_base')}><KnowledgeBasePage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="kb/:id" element={<FeatureGate feature="resources" redirect="/"><FeatureErrorBoundary featureName={label('knowledge_base')}><KBArticlePage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="organisations" element={<FeatureGate feature="volunteering" fallback={<ComingSoonPage feature={label('organisations')} />}><FeatureErrorBoundary featureName={label('organisations')}><OrganisationsPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="organisations/:id" element={<FeatureGate feature="volunteering" redirect="/"><FeatureErrorBoundary featureName={label('organisations')}><OrganisationDetailPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="ideation" element={<FeatureGate feature="ideation_challenges" fallback={<ComingSoonPage feature={label('ideation_challenges')} />}><FeatureErrorBoundary featureName={label('ideation_challenges')}><IdeationPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="ideation/:id" element={<FeatureGate feature="ideation_challenges" redirect="/"><FeatureErrorBoundary featureName={label('ideation_challenges')}><ChallengeDetailPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="ideation/:challengeId/ideas/:id" element={<FeatureGate feature="ideation_challenges" redirect="/"><FeatureErrorBoundary featureName={label('ideation_challenges')}><IdeaDetailPage /></FeatureErrorBoundary></FeatureGate>} />
        <Route path="*" element={<ErrorBoundary><NotFoundPage /></ErrorBoundary>} />
      </Route>
    </>
  );
}

export default PublicAppRoutes;
