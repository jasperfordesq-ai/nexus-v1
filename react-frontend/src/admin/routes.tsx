/**
 * Admin Routes Definition
 * Maps all admin URL paths to their React components.
 * Uses lazy loading for all module pages.
 */

import { Suspense, lazy } from 'react';
import { Route } from 'react-router-dom';
import { LoadingScreen } from '@/components/feedback';
import { AdminPlaceholder } from './modules/AdminPlaceholder';

// Lazy-loaded admin pages
const AdminDashboard = lazy(() => import('./modules/dashboard/AdminDashboard'));
const UserList = lazy(() => import('./modules/users/UserList'));
const TenantFeatures = lazy(() => import('./modules/config/TenantFeatures'));
const ListingsAdmin = lazy(() => import('./modules/listings/ListingsAdmin'));

// Wrap lazy components in Suspense
function Lazy({ children }: { children: React.ReactNode }) {
  return <Suspense fallback={<LoadingScreen message="Loading..." />}>{children}</Suspense>;
}

// Placeholder helpers for modules in migration
function P({ title, description, path }: { title: string; description?: string; path?: string }) {
  return <AdminPlaceholder title={title} description={description} legacyPath={path} />;
}

/**
 * All admin routes — rendered inside AdminLayout + AdminRoute guard.
 * Matches the PHP admin navigation structure exactly.
 */
export function AdminRoutes() {
  return (
    <>
      {/* Dashboard */}
      <Route index element={<Lazy><AdminDashboard /></Lazy>} />

      {/* ─── USERS ─── */}
      <Route path="users" element={<Lazy><UserList /></Lazy>} />
      <Route path="users/create" element={<P title="Create User" path="/admin/users/create" />} />
      <Route path="users/:id/edit" element={<P title="Edit User" path="/admin/users/edit" />} />
      <Route path="users/:id/permissions" element={<P title="User Permissions" path="/admin/enterprise/permissions" />} />

      {/* ─── LISTINGS ─── */}
      <Route path="listings" element={<Lazy><ListingsAdmin /></Lazy>} />

      {/* ─── CONTENT ─── */}
      <Route path="blog" element={<P title="Blog Posts" description="Create and manage blog posts" path="/admin/blog" />} />
      <Route path="blog/create" element={<P title="Create Blog Post" path="/admin/blog/create" />} />
      <Route path="blog/edit/:id" element={<P title="Edit Blog Post" path="/admin/blog/edit" />} />
      <Route path="pages" element={<P title="Pages" description="Manage CMS pages" path="/admin/pages" />} />
      <Route path="pages/builder/:id" element={<P title="Page Builder" path="/admin/pages/builder" />} />
      <Route path="menus" element={<P title="Menus" description="Manage navigation menus" path="/admin/menus" />} />
      <Route path="menus/builder/:id" element={<P title="Menu Builder" path="/admin/menus/builder" />} />
      <Route path="categories" element={<P title="Categories" description="Content categories" path="/admin/categories" />} />
      <Route path="categories/create" element={<P title="Create Category" path="/admin/categories/create" />} />
      <Route path="categories/edit/:id" element={<P title="Edit Category" path="/admin/categories/edit" />} />
      <Route path="attributes" element={<P title="Attributes" description="Listing attributes" path="/admin/attributes" />} />

      {/* ─── ENGAGEMENT ─── */}
      <Route path="gamification" element={<P title="Gamification Hub" description="Badges, achievements, and XP" path="/admin/gamification" />} />
      <Route path="gamification/campaigns" element={<P title="Campaigns" path="/admin/gamification/campaigns" />} />
      <Route path="gamification/campaigns/create" element={<P title="Create Campaign" path="/admin/gamification/campaigns/create" />} />
      <Route path="gamification/campaigns/edit/:id" element={<P title="Edit Campaign" path="/admin/gamification/campaigns/edit" />} />
      <Route path="gamification/analytics" element={<P title="Gamification Analytics" path="/admin/gamification/analytics" />} />
      <Route path="custom-badges" element={<P title="Custom Badges" path="/admin/custom-badges" />} />
      <Route path="custom-badges/create" element={<P title="Create Badge" path="/admin/custom-badges/create" />} />

      {/* ─── MATCHING & BROKER ─── */}
      <Route path="smart-matching" element={<P title="Smart Matching" description="Algorithm configuration and analytics" path="/admin/smart-matching" />} />
      <Route path="smart-matching/analytics" element={<P title="Matching Analytics" path="/admin/smart-matching/analytics" />} />
      <Route path="smart-matching/configuration" element={<P title="Matching Configuration" path="/admin/smart-matching/configuration" />} />
      <Route path="match-approvals" element={<P title="Match Approvals" description="Review and approve matches" path="/admin/match-approvals" />} />
      <Route path="match-approvals/:id" element={<P title="Match Detail" path="/admin/match-approvals" />} />
      <Route path="broker-controls" element={<P title="Broker Controls" description="Exchange management and monitoring" path="/admin/broker-controls" />} />
      <Route path="broker-controls/exchanges" element={<P title="Exchange Management" path="/admin/broker-controls/exchanges" />} />
      <Route path="broker-controls/risk-tags" element={<P title="Risk Tags" path="/admin/broker-controls/risk-tags" />} />
      <Route path="broker-controls/messages" element={<P title="Message Review" path="/admin/broker-controls/messages" />} />
      <Route path="broker-controls/monitoring" element={<P title="User Monitoring" path="/admin/broker-controls/monitoring" />} />

      {/* ─── MARKETING ─── */}
      <Route path="newsletters" element={<P title="Newsletters" description="Email campaign management" path="/admin/newsletters" />} />
      <Route path="newsletters/create" element={<P title="Create Newsletter" path="/admin/newsletters/create" />} />
      <Route path="newsletters/edit/:id" element={<P title="Edit Newsletter" path="/admin/newsletters/edit" />} />
      <Route path="newsletters/subscribers" element={<P title="Subscribers" path="/admin/newsletters/subscribers" />} />
      <Route path="newsletters/segments" element={<P title="Segments" path="/admin/newsletters/segments" />} />
      <Route path="newsletters/templates" element={<P title="Templates" path="/admin/newsletters/templates" />} />
      <Route path="newsletters/analytics" element={<P title="Newsletter Analytics" path="/admin/newsletters/analytics" />} />

      {/* ─── ADVANCED ─── */}
      <Route path="ai-settings" element={<P title="AI Settings" description="Configure AI providers" path="/admin/ai-settings" />} />
      <Route path="feed-algorithm" element={<P title="Feed Algorithm" path="/admin/feed-algorithm" />} />
      <Route path="algorithm-settings" element={<P title="Algorithm Settings" path="/admin/algorithm-settings" />} />
      <Route path="seo" element={<P title="SEO Overview" description="Search engine optimization" path="/admin/seo" />} />
      <Route path="seo/audit" element={<P title="SEO Audit" path="/admin/seo/audit" />} />
      <Route path="seo/redirects" element={<P title="Redirects" path="/admin/seo/redirects" />} />
      <Route path="404-errors" element={<P title="404 Error Tracking" path="/admin/404-errors" />} />

      {/* ─── FINANCIAL ─── */}
      <Route path="timebanking" element={<P title="Timebanking" description="Transaction analytics and abuse detection" path="/admin/timebanking" />} />
      <Route path="timebanking/alerts" element={<P title="Fraud Alerts" path="/admin/timebanking/alerts" />} />
      <Route path="timebanking/user-report" element={<P title="User Report" path="/admin/timebanking/user-report" />} />
      <Route path="timebanking/user-report/:id" element={<P title="User Report" path="/admin/timebanking/user-report" />} />
      <Route path="timebanking/org-wallets" element={<P title="Organization Wallets" path="/admin/timebanking/org-wallets" />} />
      <Route path="timebanking/create-org" element={<P title="Create Organization" path="/admin/timebanking/create-org" />} />
      <Route path="plans" element={<P title="Plans & Pricing" path="/admin/plans" />} />
      <Route path="plans/create" element={<P title="Create Plan" path="/admin/plans/create" />} />
      <Route path="plans/edit/:id" element={<P title="Edit Plan" path="/admin/plans/edit" />} />
      <Route path="plans/subscriptions" element={<P title="Subscriptions" path="/admin/plans/subscriptions" />} />

      {/* ─── ENTERPRISE ─── */}
      <Route path="enterprise" element={<P title="Enterprise Dashboard" path="/admin/enterprise" />} />
      <Route path="enterprise/roles" element={<P title="Roles & Permissions" description="RBAC management" path="/admin/enterprise/roles" />} />
      <Route path="enterprise/roles/create" element={<P title="Create Role" path="/admin/enterprise/roles/create" />} />
      <Route path="enterprise/roles/:id" element={<P title="View Role" path="/admin/enterprise/roles" />} />
      <Route path="enterprise/roles/:id/edit" element={<P title="Edit Role" path="/admin/enterprise/roles" />} />
      <Route path="enterprise/permissions" element={<P title="Permission Browser" path="/admin/enterprise/permissions" />} />
      <Route path="enterprise/gdpr" element={<P title="GDPR Dashboard" description="Data protection compliance" path="/admin/enterprise/gdpr" />} />
      <Route path="enterprise/gdpr/requests" element={<P title="Data Requests" path="/admin/enterprise/gdpr/requests" />} />
      <Route path="enterprise/gdpr/consents" element={<P title="Consent Records" path="/admin/enterprise/gdpr/consents" />} />
      <Route path="enterprise/gdpr/breaches" element={<P title="Data Breaches" path="/admin/enterprise/gdpr/breaches" />} />
      <Route path="enterprise/gdpr/audit" element={<P title="GDPR Audit Log" path="/admin/enterprise/gdpr/audit" />} />
      <Route path="enterprise/monitoring" element={<P title="System Monitoring" path="/admin/enterprise/monitoring" />} />
      <Route path="enterprise/monitoring/health" element={<P title="Health Check" path="/admin/enterprise/monitoring/health" />} />
      <Route path="enterprise/monitoring/logs" element={<P title="Error Logs" path="/admin/enterprise/monitoring/logs" />} />
      <Route path="enterprise/config" element={<P title="System Configuration" path="/admin/enterprise/config" />} />
      <Route path="enterprise/config/secrets" element={<P title="Secrets Vault" path="/admin/enterprise/config/secrets" />} />
      <Route path="legal-documents" element={<P title="Legal Documents" description="Document versioning and compliance" path="/admin/legal-documents" />} />
      <Route path="legal-documents/create" element={<P title="Create Legal Document" path="/admin/legal-documents/create" />} />
      <Route path="legal-documents/:id" element={<P title="View Legal Document" path="/admin/legal-documents" />} />
      <Route path="legal-documents/:id/edit" element={<P title="Edit Legal Document" path="/admin/legal-documents" />} />

      {/* ─── FEDERATION ─── */}
      <Route path="federation" element={<P title="Federation Settings" path="/admin/federation" />} />
      <Route path="federation/partnerships" element={<P title="Partnerships" path="/admin/federation/partnerships" />} />
      <Route path="federation/directory" element={<P title="Partner Directory" path="/admin/federation/directory" />} />
      <Route path="federation/directory/profile" element={<P title="My Listing" path="/admin/federation/directory/profile" />} />
      <Route path="federation/analytics" element={<P title="Federation Analytics" path="/admin/federation/analytics" />} />
      <Route path="federation/api-keys" element={<P title="API Keys" path="/admin/federation/api-keys" />} />
      <Route path="federation/api-keys/create" element={<P title="Create API Key" path="/admin/federation/api-keys/create" />} />
      <Route path="federation/data" element={<P title="Data Management" path="/admin/federation/data" />} />

      {/* ─── SYSTEM ─── */}
      <Route path="settings" element={<P title="Admin Settings" description="Global platform settings" path="/admin/settings" />} />
      <Route path="tenant-features" element={<Lazy><TenantFeatures /></Lazy>} />
      <Route path="cron-jobs" element={<P title="Cron Jobs" description="Scheduled task management" path="/admin/cron-jobs" />} />
      <Route path="activity-log" element={<P title="Activity Log" description="Admin action audit trail" path="/admin/activity-log" />} />
      <Route path="tests" element={<P title="API Test Runner" path="/admin/tests" />} />
      <Route path="seed-generator" element={<P title="Seed Generator" path="/admin/seed-generator" />} />
      <Route path="webp-converter" element={<P title="WebP Converter" path="/admin/webp-converter" />} />
      <Route path="image-settings" element={<P title="Image Settings" path="/admin/image-settings" />} />
      <Route path="native-app" element={<P title="Native App" path="/admin/native-app" />} />
      <Route path="blog-restore" element={<P title="Blog Restore" path="/admin/blog-restore" />} />

      {/* ─── COMMUNITY TOOLS ─── */}
      <Route path="groups" element={<P title="Groups" path="/admin/groups" />} />
      <Route path="groups/analytics" element={<P title="Group Analytics" path="/admin/groups/analytics" />} />
      <Route path="groups/approvals" element={<P title="Group Approvals" path="/admin/groups/approvals" />} />
      <Route path="groups/moderation" element={<P title="Content Moderation" path="/admin/groups/moderation" />} />
      <Route path="group-types" element={<P title="Group Types" path="/admin/group-types" />} />
      <Route path="group-ranking" element={<P title="Group Ranking" path="/admin/group-ranking" />} />
      <Route path="group-locations" element={<P title="Group Locations" path="/admin/group-locations" />} />
      <Route path="geocode-groups" element={<P title="Geocoding" path="/admin/geocode-groups" />} />
      <Route path="smart-match-users" element={<P title="Smart Match Users" path="/admin/smart-match-users" />} />
      <Route path="smart-match-monitoring" element={<P title="Match Monitoring" path="/admin/smart-match-monitoring" />} />
      <Route path="volunteering" element={<P title="Volunteering" path="/admin/volunteering" />} />
      <Route path="volunteering/approvals" element={<P title="Volunteer Approvals" path="/admin/volunteering/approvals" />} />
      <Route path="volunteering/organizations" element={<P title="Organizations" path="/admin/volunteering/organizations" />} />

      {/* ─── DELIVERABILITY ─── */}
      <Route path="deliverability" element={<P title="Deliverability Dashboard" path="/admin/deliverability" />} />
      <Route path="deliverability/list" element={<P title="All Deliverables" path="/admin/deliverability/list" />} />
      <Route path="deliverability/create" element={<P title="Create Deliverable" path="/admin/deliverability/create" />} />
      <Route path="deliverability/analytics" element={<P title="Deliverability Analytics" path="/admin/deliverability/analytics" />} />

      {/* ─── MATCHING DIAGNOSTIC ─── */}
      <Route path="matching-diagnostic" element={<P title="Matching Diagnostic" path="/admin/matching-diagnostic" />} />

      {/* ─── NEXUS SCORE ─── */}
      <Route path="nexus-score/analytics" element={<P title="Nexus Score Analytics" path="/admin/nexus-score/analytics" />} />
    </>
  );
}

export default AdminRoutes;
