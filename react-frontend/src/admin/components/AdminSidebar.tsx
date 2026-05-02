// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Sidebar Navigation
 * Collapsible sidebar matching the PHP admin navigation structure.
 * Uses Lucide icons (consistent with React frontend).
 *
 * Features:
 * - Zone grouping: 4 named super-groups containing related sections
 * - Accordion collapse: opening one section auto-closes others in the same zone
 * - Search/filter: real-time fuzzy search across all 139 nav items
 * - Recent pages: last 5 visited admin pages tracked in localStorage
 */

import { Fragment, useState, useEffect, useCallback, useMemo } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Button, Input } from '@heroui/react';
import { useAuth, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import LayoutDashboard from 'lucide-react/icons/layout-dashboard';
import Users from 'lucide-react/icons/users';
import ListChecks from 'lucide-react/icons/list-checks';
import Newspaper from 'lucide-react/icons/newspaper';
import Trophy from 'lucide-react/icons/trophy';
import Megaphone from 'lucide-react/icons/megaphone';
import Sparkles from 'lucide-react/icons/sparkles';
import Bell from 'lucide-react/icons/bell';
import Coins from 'lucide-react/icons/coins';
import ArrowRightLeft from 'lucide-react/icons/arrow-right-left';
import Building2 from 'lucide-react/icons/building-2';
import Globe from 'lucide-react/icons/globe';
import Settings from 'lucide-react/icons/settings';
import ChevronDown from 'lucide-react/icons/chevron-down';
import ChevronRight from 'lucide-react/icons/chevron-right';
import PanelLeftClose from 'lucide-react/icons/panel-left-close';
import PanelLeft from 'lucide-react/icons/panel-left';
import UserCheck from 'lucide-react/icons/user-check';
import FileText from 'lucide-react/icons/file-text';
import Menu from 'lucide-react/icons/menu';
import FolderTree from 'lucide-react/icons/folder-tree';
import Tags from 'lucide-react/icons/tags';
import Tag from 'lucide-react/icons/tag';
import Gamepad2 from 'lucide-react/icons/gamepad-2';
import Medal from 'lucide-react/icons/medal';
import BarChart3 from 'lucide-react/icons/chart-column';
import Zap from 'lucide-react/icons/zap';
import Target from 'lucide-react/icons/target';
import Brain from 'lucide-react/icons/brain';
import Bot from 'lucide-react/icons/bot';
import Search from 'lucide-react/icons/search';
import ArrowLeftRight from 'lucide-react/icons/arrow-left-right';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import Clock from 'lucide-react/icons/clock';
import Wallet from 'lucide-react/icons/wallet';
import CreditCard from 'lucide-react/icons/credit-card';
import Shield from 'lucide-react/icons/shield';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import Key from 'lucide-react/icons/key';
import ShieldCheck from 'lucide-react/icons/shield-check';
import Heart from 'lucide-react/icons/heart';
import Cog from 'lucide-react/icons/cog';
import Timer from 'lucide-react/icons/timer';
import Contact from 'lucide-react/icons/contact';
import StickyNote from 'lucide-react/icons/sticky-note';
import ClipboardCheck from 'lucide-react/icons/clipboard-check';
import ClipboardList from 'lucide-react/icons/clipboard-list';
import Filter from 'lucide-react/icons/filter';
import Activity from 'lucide-react/icons/activity';
import Crown from 'lucide-react/icons/crown';
import Network from 'lucide-react/icons/network';
import ScrollText from 'lucide-react/icons/scroll-text';
import Mail from 'lucide-react/icons/mail';
import Wrench from 'lucide-react/icons/wrench';
import Stethoscope from 'lucide-react/icons/stethoscope';
import MessageSquare from 'lucide-react/icons/message-square';
import MessageCircle from 'lucide-react/icons/message-circle';
import Star from 'lucide-react/icons/star';
import Flag from 'lucide-react/icons/flag';
import UserX from 'lucide-react/icons/user-x';
import Server from 'lucide-react/icons/server';
import Scale from 'lucide-react/icons/scale';
import Layers from 'lucide-react/icons/layers';
import PlugZap from 'lucide-react/icons/plug-zap';
import Calendar from 'lucide-react/icons/calendar';
import BarChart2 from 'lucide-react/icons/chart-no-axes-column';
import Lightbulb from 'lucide-react/icons/lightbulb';
import Briefcase from 'lucide-react/icons/briefcase';
import BookOpen from 'lucide-react/icons/book-open';
import Cpu from 'lucide-react/icons/cpu';
import Handshake from 'lucide-react/icons/handshake';
import Database from 'lucide-react/icons/database';
import MapPin from 'lucide-react/icons/map-pin';
import FileSearch from 'lucide-react/icons/file-search';
import Webhook from 'lucide-react/icons/webhook';
import Puzzle from 'lucide-react/icons/puzzle';
import Palette from 'lucide-react/icons/palette';
import ShoppingBag from 'lucide-react/icons/shopping-bag';
import Store from 'lucide-react/icons/store';
import Languages from 'lucide-react/icons/languages';
import Landmark from 'lucide-react/icons/landmark';
import X from 'lucide-react/icons/x';
import BellRing from 'lucide-react/icons/bell-ring';
import FlaskConical from 'lucide-react/icons/flask-conical';
import Rocket from 'lucide-react/icons/rocket';
import Sliders from 'lucide-react/icons/sliders';
import Users2 from 'lucide-react/icons/users-2';
import TrendingUp from 'lucide-react/icons/trending-up';
import type { LucideIcon } from 'lucide-react';

// ─────────────────────────────────────────────────────────────────────────────
// Navigation config — mirrors PHP admin-navigation-config.php
// ─────────────────────────────────────────────────────────────────────────────

interface NavItem {
  label: string;
  href: string;
  icon: LucideIcon;
  badge?: string;
  // Optional faint heading rendered ABOVE this item to visually group long
  // dropdowns (used by the Caring Community section). Tag the FIRST item of
  // each group; subsequent items inherit the grouping until the next tag.
  group?: string;
}

interface NavSection {
  key: string;
  label: string;
  icon: LucideIcon;
  href?: string;
  items?: NavItem[];
  condition?: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Zone config — groups related sections into collapsible super-sections
// Dashboard (key:'dashboard') and Super Admin (key:'super-admin') are NOT zoned.
// ─────────────────────────────────────────────────────────────────────────────

type NavZoneKey = 'people' | 'content_commerce' | 'growth' | 'platform' | 'ops';

interface NavZone {
  key: NavZoneKey;
  label: string; // i18n key in admin_nav.json (top-level)
  sectionKeys: string[];
}

interface RecentPage {
  label: string;
  href: string;
  visitedAt: number;
}

const ZONES: NavZone[] = [
  {
    // Individual member management: who your people are
    key: 'people',
    label: 'zone_members',
    sectionKeys: ['users', 'crm'],
  },
  {
    // What your community does: content, activities, commerce
    key: 'content_commerce',
    label: 'zone_community',
    sectionKeys: ['community', 'listings', 'content', 'jobs', 'marketplace', 'advertising'],
  },
  {
    // Keeping the platform safe: content + user safety together
    key: 'growth',
    label: 'zone_safety',
    sectionKeys: ['moderation', 'matching'],
  },
  {
    // Growing and measuring the community (no safety mixed in)
    key: 'platform',
    label: 'zone_growth',
    sectionKeys: ['engagement', 'marketing', 'analytics'],
  },
  {
    // Running the platform: config, finance, infrastructure
    key: 'ops',
    label: 'zone_platform',
    sectionKeys: ['financial', 'enterprise', 'advanced', 'federation', 'integrations', 'system'],
  },
];

// ─────────────────────────────────────────────────────────────────────────────
// localStorage — Recent pages tracking
// ─────────────────────────────────────────────────────────────────────────────

const RECENT_PAGES_KEY = 'admin_recent_pages';
const RECENT_PAGES_MAX = 5;

function readRecentPages(): RecentPage[] {
  try {
    const raw = localStorage.getItem(RECENT_PAGES_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw) as unknown;
    if (!Array.isArray(parsed)) return [];
    return (parsed as RecentPage[]).filter(
      (p) =>
        p !== null &&
        typeof p === 'object' &&
        typeof (p as RecentPage).label === 'string' &&
        typeof (p as RecentPage).href === 'string',
    );
  } catch {
    return [];
  }
}

function saveRecentPage(page: RecentPage): RecentPage[] {
  const existing = readRecentPages();
  const updated = [page, ...existing.filter((p) => p.href !== page.href)].slice(
    0,
    RECENT_PAGES_MAX,
  );
  try {
    localStorage.setItem(RECENT_PAGES_KEY, JSON.stringify(updated));
  } catch {
    // Quota errors silently ignored
  }
  return updated;
}

// ─────────────────────────────────────────────────────────────────────────────
// Fuzzy search — simple character-sequence match (no external dependency)
// ─────────────────────────────────────────────────────────────────────────────

function fuzzyMatch(query: string, target: string): boolean {
  if (!query) return true;
  const q = query.toLowerCase().trim();
  const t = target.toLowerCase();
  if (t.includes(q)) return true;
  // Character-sequence: every char of q must appear in order in t
  let qi = 0;
  for (let ti = 0; ti < t.length && qi < q.length; ti++) {
    if (t[ti] === q[qi]) qi++;
  }
  return qi === q.length;
}

// ─────────────────────────────────────────────────────────────────────────────
// Navigation data hook
// ─────────────────────────────────────────────────────────────────────────────

// ⚠️ TRANSLATION KEY CONVENTION — READ BEFORE EDITING
// ALL labels use TOP-LEVEL keys from admin_nav.json: "Users", "Admin", etc.
// Do NOT add a "sidebar." prefix — per CLAUDE.md, admin sidebar keys are top-level only.
// Sub-item labels also use top-level keys: "All Users", "Blog Posts", etc.
function useAdminNav(): NavSection[] {
  const { t } = useTranslation('admin_nav');
  const { hasFeature, hasModule } = useTenant();
  const { user } = useAuth();

  const userRecord = user as Record<string, unknown> | null;
  const isSuperAdmin =
    (user?.role as string) === 'super_admin' ||
    userRecord?.is_super_admin === true ||
    userRecord?.is_tenant_super_admin === true;
  const isPlatformSuperAdmin =
    (user?.role as string) === 'super_admin' ||
    userRecord?.is_super_admin === true;

  return useMemo(() => {
    // ── Community items — each sub-feature gated independently ───────────
    const communityItems = [
      ...(hasFeature('groups') ? [
        { label: "Groups", href: '/admin/groups', icon: Users },
        { label: "Group Types", href: '/admin/groups/types', icon: FolderTree },
        { label: "Group Recommendations", href: '/admin/groups/recommendations', icon: Brain },
        { label: "Group Ranking", href: '/admin/groups/ranking', icon: Trophy },
      ] : []),
      ...(hasFeature('events') ? [
        { label: "Events", href: '/admin/events', icon: Calendar },
      ] : []),
      ...(hasFeature('polls') ? [
        { label: "Polls", href: '/admin/polls', icon: BarChart2 },
      ] : []),
      ...(hasFeature('goals') ? [
        { label: "Goals", href: '/admin/goals', icon: Target },
      ] : []),
      ...(hasFeature('ideation_challenges') ? [
        { label: "Ideation Challenges", href: '/admin/ideation', icon: Lightbulb },
      ] : []),
      ...(hasFeature('volunteering') ? [
        { label: "Volunteering", href: '/admin/volunteering', icon: Heart },
      ] : []),
    ];

    // ── Matching items — broker/exchange gated; safeguarding always shown ─
    // Broker Controls moved to /broker/* — single "Broker Panel" entry here
    // links into the dedicated panel. The 6 sub-pages that used to live
    // under Matching & Safety (Message Review, User Monitoring, Vetting,
    // Insurance, Review Archive) are now in the broker panel's own sidebar.
    const matchingItems = [
      ...(hasFeature('exchange_workflow') ? [
        { label: "Smart Matching", href: '/admin/smart-matching', icon: Brain },
        { label: "Match Approvals", href: '/admin/match-approvals', icon: UserCheck, badge: 'NEW' },
        { label: "Broker Panel", href: '/broker', icon: Shield },
      ] : []),
      { label: "Safeguarding", href: '/admin/safeguarding', icon: ShieldCheck },
      { label: "Member Safeguarding", href: '/admin/safeguarding?tab=preferences', icon: Users },
      { label: "Safeguarding Options", href: '/admin/safeguarding-options', icon: Shield },
    ];

    // ── Moderation items — feed posts and reviews gated ──────────────────
    const moderationItems = [
      { label: "Content Queue", href: '/admin/moderation/queue', icon: Shield, badge: 'NEW' },
      ...(hasModule('feed') ? [
        { label: "Feed Posts", href: '/admin/moderation/feed', icon: MessageSquare },
      ] : []),
      { label: "Comments", href: '/admin/moderation/comments', icon: MessageCircle },
      ...(hasFeature('reviews') ? [
        { label: "Reviews", href: '/admin/moderation/reviews', icon: Star },
      ] : []),
      { label: "Reports", href: '/admin/moderation/reports', icon: Flag },
    ];

    // ── Content items — blog and resources gated ─────────────────────────
    const contentItems = [
      ...(hasFeature('blog') ? [
        { label: "Blog Posts", href: '/admin/blog', icon: FileText },
      ] : []),
      ...(hasFeature('resources') ? [
        { label: "Resources", href: '/admin/resources', icon: BookOpen },
      ] : []),
      { label: "Pages", href: '/admin/pages', icon: FileText },
      { label: "Landing Page", href: '/admin/landing-page', icon: Palette },
      { label: "Menus", href: '/admin/menus', icon: Menu },
      { label: "Categories", href: '/admin/categories', icon: FolderTree },
      { label: "Attributes", href: '/admin/attributes', icon: Tags },
    ];

    // ── Financial items — timebanking gated behind wallet module ─────────
    const financialItems = [
      ...(hasModule('wallet') ? [
        { label: "Timebanking", href: '/admin/timebanking', icon: Clock },
        { label: "Fraud Alerts", href: '/admin/timebanking/alerts', icon: AlertTriangle },
        { label: "Organisation Wallets", href: '/admin/timebanking/org-wallets', icon: Wallet },
        { label: "Starting Balances", href: '/admin/timebanking/starting-balances', icon: Wallet },
      ] : []),
      { label: "Plans & Pricing", href: '/admin/plans', icon: CreditCard },
      { label: "Billing", href: '/admin/billing', icon: CreditCard },
      ...(hasFeature('member_premium') ? [
        { label: "Member Premium", href: '/admin/member-premium', icon: Crown },
        { label: "Premium Subscribers", href: '/admin/member-premium/subscribers', icon: Users },
      ] : []),
    ];

    // ── Advanced items — AI settings gated ───────────────────────────────
    const advancedItems = [
      ...(hasFeature('ai_chat') ? [
        { label: "AI Settings", href: '/admin/ai-settings', icon: Brain },
      ] : []),
      ...(hasFeature('ai_agents') ? [
        { label: "AI Agents", href: '/admin/agents', icon: Bot },
        { label: "Agent Proposals", href: '/admin/agents/proposals', icon: Bot },
        { label: "Agent Runs", href: '/admin/agents/runs', icon: Bot },
      ] : []),
      { label: "Email Settings", href: '/admin/email-settings', icon: Mail },
      { label: "Algorithm Settings", href: '/admin/algorithm-settings', icon: Cpu },
      { label: "SEO Overview", href: '/admin/seo', icon: Search },
      { label: "404 Error Tracking", href: '/admin/404-errors', icon: AlertTriangle },
      { label: "Diagnostics", href: '/admin/matching-diagnostic', icon: Stethoscope },
      { label: "Match Debug Panel", href: '/admin/match-debug', icon: Target },
    ];

    const sections: NavSection[] = [
      {
        key: 'dashboard',
        label: "Dashboard",
        icon: LayoutDashboard,
        href: '/admin',
      },
      {
        key: 'users',
        label: "Users",
        icon: Users,
        items: [
          { label: "All Users", href: '/admin/users', icon: Users },
          { label: "Pending Approvals", href: '/admin/users?filter=pending', icon: UserCheck },
        ],
      },
      {
        key: 'crm',
        label: "CRM",
        icon: Contact,
        items: [
          { label: "CRM Dashboard", href: '/admin/crm', icon: Contact },
          { label: "Member Notes", href: '/admin/crm/notes', icon: StickyNote },
          { label: "Coordinator Tasks", href: '/admin/crm/tasks', icon: ClipboardList },
          { label: "Member Tags", href: '/admin/crm/tags', icon: Tag },
          { label: "Activity Timeline", href: '/admin/crm/timeline', icon: Activity },
          { label: "Onboarding Funnel", href: '/admin/crm/funnel', icon: Filter },
        ],
      },
      // Listings — only when module is enabled
      ...(hasModule('listings') ? [{
        key: 'listings',
        label: "Listings",
        icon: ListChecks,
        items: [
          { label: "All Content", href: '/admin/listings', icon: ListChecks },
        ],
      }] as NavSection[] : []),
      // Content — always has core items (Pages, Menus, etc.)
      {
        key: 'content',
        label: "Content",
        icon: Newspaper,
        items: contentItems,
      },
      // Engagement — entirely gamification, hidden when feature is off
      ...(hasFeature('gamification') ? [{
        key: 'engagement',
        label: "Engagement",
        icon: Trophy,
        items: [
          { label: "Gamification Hub", href: '/admin/gamification', icon: Gamepad2 },
          { label: "Campaigns", href: '/admin/gamification/campaigns', icon: Target },
          { label: "Custom Badges", href: '/admin/custom-badges', icon: Medal },
          { label: "Analytics", href: '/admin/gamification/analytics', icon: BarChart3 },
        ],
      }] as NavSection[] : []),
      // Matching & Safety — broker items gated, safeguarding always present
      {
        key: 'matching',
        label: "Matching & Safety",
        icon: Zap,
        items: matchingItems,
      },
      // Moderation — always has core items
      {
        key: 'moderation',
        label: "Moderation",
        icon: Shield,
        items: moderationItems,
      },
      // Community — hidden entirely if all sub-features are disabled
      ...(communityItems.length > 0 ? [{
        key: 'community',
        label: "Community",
        icon: Users,
        items: communityItems,
      }] as NavSection[] : []),
      // Jobs — gated by job_vacancies feature
      ...(hasFeature('job_vacancies') ? [{
        key: 'jobs',
        label: "Job Vacancies",
        icon: Briefcase,
        items: [
          { label: "Job Listings", href: '/admin/jobs', icon: Briefcase },
          { label: "Job Moderation", href: '/admin/jobs/moderation', icon: ShieldCheck },
          { label: "Pipeline", href: '/admin/jobs/pipeline', icon: Handshake },
          { label: "Bias Audit", href: '/admin/jobs/bias-audit', icon: BarChart3 },
          { label: "Templates", href: '/admin/jobs/templates', icon: FileText },
        ],
      }] as NavSection[] : []),
      // Marketplace — gated by marketplace feature
      ...(hasFeature('marketplace') ? [{
        key: 'marketplace',
        label: "Marketplace",
        icon: ShoppingBag as LucideIcon,
        items: [
          { label: "Dashboard", href: '/admin/marketplace', icon: ShoppingBag as LucideIcon },
          { label: "Moderation", href: '/admin/marketplace/moderation', icon: ShieldCheck as LucideIcon },
          { label: "Sellers", href: '/admin/marketplace/sellers', icon: Store as LucideIcon },
        ],
      }] as NavSection[] : []),
      // Advertising — gated by local_advertising feature
      ...(hasFeature('local_advertising') ? [{
        key: 'advertising',
        label: "Advertising",
        icon: Megaphone,
        items: [
          { label: "Ad Campaigns", href: '/admin/advertising/campaigns', icon: Megaphone },
          { label: "Push Campaigns", href: '/admin/advertising/push-campaigns', icon: BellRing },
        ],
      }] as NavSection[] : []),
      {
        key: 'marketing',
        label: "Marketing",
        icon: Megaphone,
        items: [
          { label: "Newsletters", href: '/admin/newsletters', icon: Megaphone },
          { label: "Subscribers", href: '/admin/newsletters/subscribers', icon: Users },
          { label: "Templates", href: '/admin/newsletters/templates', icon: FileText },
          { label: "Bounces", href: '/admin/newsletters/bounces', icon: AlertTriangle },
          { label: "Send Time Optimizer", href: '/admin/newsletters/send-time-optimizer', icon: Clock },
          { label: "Diagnostics", href: '/admin/newsletters/diagnostics', icon: Stethoscope },
          { label: "Deliverability", href: '/admin/deliverability', icon: Mail },
        ],
      },
      {
        key: 'analytics',
        label: "Analytics & Reporting",
        icon: BarChart3,
        items: [
          { label: "Community Analytics", href: '/admin/community-analytics', icon: BarChart3 },
          { label: "Impact Report", href: '/admin/impact-report', icon: FileText },
          { label: "Member Reports", href: '/admin/reports/members', icon: Users },
          ...(hasModule('wallet') ? [
            { label: "Hours Reports", href: '/admin/reports/hours', icon: Clock },
          ] : []),
          { label: "Inactive Members", href: '/admin/reports/inactive-members', icon: UserX },
        ],
      },
      {
        key: 'advanced',
        label: "Advanced",
        icon: Sparkles,
        items: advancedItems,
      },
      {
        key: 'financial',
        label: "Financial",
        icon: Coins,
        items: financialItems,
      },
      {
        key: 'enterprise',
        label: "Enterprise",
        icon: Building2,
        items: [
          { label: "Enterprise Dashboard", href: '/admin/enterprise', icon: Building2 },
          { label: "Roles & Permissions", href: '/admin/enterprise/roles', icon: Key },
          { label: "GDPR Dashboard", href: '/admin/enterprise/gdpr', icon: ShieldCheck },
          { label: "Legal Documents", href: '/admin/legal-documents', icon: FileText },
          { label: "Compliance Dashboard", href: '/admin/legal-documents/compliance', icon: ShieldCheck },
          { label: "Monitoring", href: '/admin/enterprise/monitoring', icon: Heart },
          { label: "System Configuration", href: '/admin/enterprise/config', icon: Cog },
          { label: "Feature Flags", href: '/admin/enterprise/config/features', icon: Settings },
          { label: "Secrets Vault", href: '/admin/enterprise/config/secrets', icon: Key },
        ],
      },
      {
        key: 'system',
        label: "System",
        icon: Settings,
        items: [
          { label: "Settings", href: '/admin/settings', icon: Settings },
          { label: "Onboarding Settings", href: '/admin/onboarding-settings', icon: Sparkles },
          { label: "Tenant Features", href: '/admin/tenant-features', icon: Cog },
          { label: "Module Configuration", href: '/admin/module-configuration', icon: Puzzle, badge: 'BETA' },
          { label: "Translation Settings", href: '/admin/translation-config', icon: Languages },
          { label: "Activity Log", href: '/admin/activity-log', icon: Activity },
          { label: "Cron Jobs", href: '/admin/cron-jobs', icon: Timer },
          { label: "Cron Logs", href: '/admin/cron-jobs/logs', icon: FileText },
          { label: "Cron Setup", href: '/admin/cron-jobs/setup', icon: Wrench },
          ...(isPlatformSuperAdmin
            ? [{ label: "Cron Settings", href: '/admin/cron-jobs/settings', icon: Settings }]
            : []),
        ],
      },
    ];

    // Conditionally add federation (belongs in 'platform' zone)
    if (hasFeature('federation')) {
      sections.splice(sections.length - 1, 0, {
        key: 'federation',
        label: "Partner Timebanks",
        icon: Globe,
        items: [
          { label: "Federation Settings", href: '/admin/federation', icon: Settings },
          { label: "Partnerships", href: '/admin/federation/partnerships', icon: ArrowLeftRight },
          { label: "Directory", href: '/admin/federation/directory', icon: Globe },
          { label: "Credit Agreements", href: '/admin/federation/credit-agreements', icon: Handshake },
          { label: "Neighborhoods", href: '/admin/federation/neighborhoods', icon: MapPin },
          { label: "Federation Analytics", href: '/admin/federation/analytics', icon: BarChart3 },
          { label: "API Keys", href: '/admin/federation/api-keys', icon: Key },
          { label: "API Documentation", href: '/admin/federation/api-docs', icon: BookOpen },
          { label: "External Partners", href: '/admin/federation/external-partners', icon: Globe },
          { label: "Credit Card Config", href: '/admin/federation/cc-config', icon: Network },
          { label: "Webhooks", href: '/admin/federation/webhooks', icon: Webhook },
          { label: "Activity Feed", href: '/admin/federation/activity', icon: Activity },
          { label: "Data Management", href: '/admin/federation/data', icon: Database },
          { label: "Aggregates", href: '/admin/federation/aggregates', icon: ShieldCheck },
        ],
      });
    }

    // AG60 — Integrations zone — currently houses the API Partners admin
    if (hasFeature('partner_api')) {
      sections.splice(sections.length - 1, 0, {
        key: 'integrations',
        label: "Integrations",
        icon: Webhook,
        items: [
          { label: "API Partners", href: '/admin/api-partners', icon: Key },
        ],
      });
    }

    // Super Admin section — only visible to super admins, always at bottom (not in any zone)
    if (isSuperAdmin) {
      sections.push({
        key: 'super-admin',
        label: "Super Admin",
        icon: Crown,
        items: [
          { label: "Super Dashboard", href: '/admin/super', icon: Crown },
          { label: "National KISS Dashboard", href: '/admin/national/kiss', icon: Landmark },
          { label: "Provisioning Queue", href: '/admin/provisioning-requests', icon: Building2 },
          { label: "All Tenants", href: '/admin/super/tenants', icon: Building2 },
          { label: "Tenant Hierarchy", href: '/admin/super/tenants/hierarchy', icon: Network },
          { label: "Cross-Tenant Users", href: '/admin/super/users', icon: Users },
          { label: "Bulk Operations", href: '/admin/super/bulk', icon: ListChecks },
          { label: "Audit Log", href: '/admin/super/audit', icon: ScrollText },
          { label: "Federation Controls", href: '/admin/super/federation', icon: Globe },
          { label: "Federation Whitelist", href: '/admin/super/federation/whitelist', icon: Shield },
          { label: "Federation Partnerships", href: '/admin/super/federation/partnerships', icon: Handshake },
          { label: "Federation Audit Log", href: '/admin/super/federation/audit', icon: FileSearch },
          // AG59 — Sellable products (paid regional analytics)
          { label: "Regional Analytics (Paid)", href: '/admin/regional-analytics/subscriptions', icon: BarChart3 },
        ],
      });
    }

    return sections;
  }, [hasFeature, hasModule, isPlatformSuperAdmin, isSuperAdmin, t])

}

// ─────────────────────────────────────────────────────────────────────────────
// Sidebar Component
// ─────────────────────────────────────────────────────────────────────────────

interface AdminSidebarProps {
  collapsed: boolean;
  onToggle: () => void;
}

interface FilteredNavItem extends NavItem {
  sectionLabel: string;
}

export function AdminSidebar({ collapsed, onToggle }: AdminSidebarProps) {
  const { t } = useTranslation('admin_nav');
  const sections = useAdminNav();
  const location = useLocation();
  const { tenantPath, hasFeature } = useTenant();

  // ── Expanded sections (accordion per zone) ──────────────────────────────
  const [expandedSections, setExpandedSections] = useState<Set<string>>(
    () => {
      // Auto-expand the active section on mount
      const active = new Set<string>();
      for (const section of sections) {
        if (section.href && location.pathname === tenantPath(section.href)) {
          active.add(section.key);
        }
        if (section.items) {
          for (const item of section.items) {
            if (location.pathname.startsWith(tenantPath(item.href.split('?')[0] ?? ''))) {
              active.add(section.key);
            }
          }
        }
      }
      return active;
    }
  );

  // ── Zone collapse state (all open by default) ───────────────────────────
  const [collapsedZones, setCollapsedZones] = useState<Set<NavZoneKey>>(new Set());

  // ── Search query ────────────────────────────────────────────────────────
  const [searchQuery, setSearchQuery] = useState('');

  // ── Recent pages (from localStorage) ────────────────────────────────────
  const [recentPages, setRecentPages] = useState<RecentPage[]>(() => readRecentPages());

  // ── Dynamic unreviewed safeguarding flag count ──────────────────────────
  const [safeguardingFlagCount, setSafeguardingFlagCount] = useState(0);

  // The unreviewed-message badge moved to the broker panel sidebar when
  // /admin/broker-controls/messages was retired. The broker panel polls
  // its own dashboard endpoint for the count; the admin sidebar no longer
  // needs to.

  // Fetch the safeguarding dashboard summary so the Safeguarding nav item can
  // show a live red badge when there are unreviewed flags waiting. Failure is
  // silent — this endpoint 404s for tenants that haven't enabled the feature.
  const fetchSafeguardingFlags = useCallback(async () => {
    try {
      const res = await api.get<{ unreviewed_flags?: number } | { data: { unreviewed_flags?: number } }>(
        '/v2/admin/safeguarding/dashboard'
      );
      if (res.success && res.data) {
        const payload = 'data' in res.data ? res.data.data : res.data;
        setSafeguardingFlagCount(Number(payload?.unreviewed_flags ?? 0));
      }
    } catch {
      // Silent — feature not enabled on this tenant
    }
  }, []);

  useEffect(() => {
    fetchSafeguardingFlags();
    const interval = setInterval(fetchSafeguardingFlags, 60000);
    return () => clearInterval(interval);
  }, [fetchSafeguardingFlags]);

  // ── Section map for O(1) zone lookup ────────────────────────────────────
  const sectionMap = useMemo(
    () => new Map(sections.map((s) => [s.key, s])),
    [sections],
  );

  // ── href → current translated label (for re-resolving stale localStorage entries) ──
  const hrefToLabel = useMemo(() => {
    const map = new Map<string, string>();
    for (const section of sections) {
      if (section.href) map.set(section.href, section.label);
      for (const item of section.items ?? []) {
        map.set(item.href, item.label);
        const base = item.href.split('?')[0];
        if (base && !map.has(base)) map.set(base, item.label);
      }
    }
    return map;
  }, [sections]);

  // ── Toggle a section (accordion within zone) ─────────────────────────────
  const toggleSection = (key: string, zoneKey: NavZoneKey | null) => {
    setExpandedSections((prev) => {
      const next = new Set(prev);
      if (next.has(key)) {
        next.delete(key);
      } else {
        // Accordion: close all sibling sections in the same zone first
        if (zoneKey) {
          const zone = ZONES.find((z) => z.key === zoneKey);
          zone?.sectionKeys.forEach((sk) => next.delete(sk));
        }
        next.add(key);
      }
      return next;
    });
  };

  // ── Toggle a zone (show/hide all its sections) ───────────────────────────
  const toggleZone = (key: NavZoneKey) => {
    setCollapsedZones((prev) => {
      const next = new Set(prev);
      if (next.has(key)) next.delete(key);
      else next.add(key);
      return next;
    });
  };

  // ── Active route detection ───────────────────────────────────────────────
  const isActive = (href: string) => {
    const [path, rawQuery] = href.split('?');
    const cleanPath = path ?? '';
    const fullPath = tenantPath(cleanPath);

    if (cleanPath === '/admin') {
      return location.pathname === fullPath;
    }

    if (!location.pathname.startsWith(fullPath)) return false;

    if (rawQuery) {
      // Query-specific link: all required params must match the current URL
      const required = new URLSearchParams(rawQuery);
      const current = new URLSearchParams(location.search);
      for (const [k, v] of required.entries()) {
        if (current.get(k) !== v) return false;
      }
      return true;
    }

    // No-query link: treat as the "base/default" view.
    // Not active if the current URL has a non-default filter that would
    // belong to a sibling query-specific link (e.g. ?filter=pending).
    const currentFilter = new URLSearchParams(location.search).get('filter');
    if (currentFilter && currentFilter !== 'all') return false;

    return true;
  };

  // ── Track recent page visit ──────────────────────────────────────────────
  const trackVisit = (label: string, href: string) => {
    const updated = saveRecentPage({ label, href, visitedAt: Date.now() });
    setRecentPages(updated);
  };

  // ── Search: filtered results across all sections ─────────────────────────
  const filteredResults = useMemo((): FilteredNavItem[] => {
    if (!searchQuery.trim()) return [];
    return sections.flatMap((section) =>
      (section.items ?? [])
        .filter((item) => fuzzyMatch(searchQuery, item.label))
        .map((item) => ({ ...item, sectionLabel: section.label })),
    );
  }, [sections, searchQuery]);

  // ── Render a single nav item link ────────────────────────────────────────
  const renderNavItem = (item: NavItem) => {
    const ItemIcon = item.icon;
    // Safeguarding root link carries a red live-count badge for unreviewed
    // flags (excluding the query-scoped Member Safeguarding child link).
    // The unreviewed-message badge moved to the broker panel sidebar when
    // /admin/broker-controls/messages was retired.
    const isSafeguardingRoot = item.href === '/admin/safeguarding';
    const showSafeguardingBadge = isSafeguardingRoot && safeguardingFlagCount > 0;
    const dynamicBadge = showSafeguardingBadge
      ? String(safeguardingFlagCount)
      : item.badge;
    const isUrgentBadge = showSafeguardingBadge;

    return (
      <li key={item.href}>
        <Link
          to={tenantPath(item.href)}
          onClick={() => trackVisit(item.label, item.href)}
          className={`flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm transition-colors ${
            isActive(item.href)
              ? 'bg-primary/10 text-primary font-medium'
              : 'text-default-500 hover:bg-default-100 hover:text-foreground'
          }`}
        >
          <ItemIcon size={16} className="shrink-0" />
          <span>{item.label}</span>
          {dynamicBadge && (
            <span
              className={`ml-auto rounded-full px-1.5 py-0.5 text-[10px] font-bold ${
                isUrgentBadge
                  ? 'bg-danger text-danger-foreground'
                  : 'bg-primary text-primary-foreground'
              }`}
            >
              {dynamicBadge}
            </span>
          )}
        </Link>
      </li>
    );
  };

  // ── Render a collapsible section ─────────────────────────────────────────
  const renderSection = (section: NavSection, zoneKey: NavZoneKey | null) => {
    const Icon = section.icon;
    const isExpanded = expandedSections.has(section.key);
    const sectionActive = section.href
      ? isActive(section.href)
      : section.items?.some((item) => isActive(item.href));
    const isSuperSection = section.key === 'super-admin';

    // Single-link section (Dashboard)
    if (section.href && !section.items) {
      return (
        <li key={section.key}>
          <Link
            to={tenantPath(section.href)}
            onClick={() => trackVisit(section.label, section.href!)}
            className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
              sectionActive
                ? 'bg-primary/10 text-primary'
                : 'text-default-600 hover:bg-default-100 hover:text-foreground'
            }`}
            title={collapsed ? section.label : undefined}
          >
            <Icon size={20} className="shrink-0" />
            {!collapsed && <span>{section.label}</span>}
          </Link>
        </li>
      );
    }

    // Collapsible section
    return (
      <li key={section.key}>
        {isSuperSection && !collapsed && (
          <div className="my-2 border-t border-warning/30" />
        )}
        <div className={isSuperSection ? 'rounded-lg bg-primary/5 py-1 px-1' : ''}>
          <Button
            variant="light"
            onPress={() => toggleSection(section.key, zoneKey)}
            className={`flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors h-auto min-w-0 justify-start ${
              sectionActive
                ? 'text-primary'
                : 'text-default-600 hover:bg-default-100 hover:text-foreground'
            }`}
            title={collapsed ? section.label : undefined}
          >
            <Icon size={20} className="shrink-0" />
            {!collapsed && (
              <>
                <span className="flex-1 text-left">{section.label}</span>
                {isExpanded ? (
                  <ChevronDown size={16} className="shrink-0" />
                ) : (
                  <ChevronRight size={16} className="shrink-0" />
                )}
              </>
            )}
          </Button>
          {!collapsed && isExpanded && section.items && (
            <ul className="ml-4 mt-1 space-y-0.5 border-l border-divider pl-3">
              {section.items.map((item, idx) => (
                <Fragment key={item.href}>
                  {item.group && (
                    <li
                      className={`px-3 text-[10px] font-semibold uppercase tracking-wider text-default-400 select-none ${
                        idx === 0 ? 'pb-0.5' : 'pt-2 pb-0.5'
                      }`}
                    >
                      {item.group}
                    </li>
                  )}
                  {renderNavItem(item)}
                </Fragment>
              ))}
            </ul>
          )}
        </div>
      </li>
    );
  };

  const showRecent = !collapsed && recentPages.length >= 2 && !searchQuery.trim();

  return (
    <aside
      className={`fixed left-0 top-0 z-40 flex h-screen flex-col border-r border-divider bg-content1 transition-all duration-300 ${
        collapsed ? 'w-16' : 'w-64'
      }`}
    >
      {/* Header */}
      <div className="flex h-16 shrink-0 items-center justify-between border-b border-divider px-4">
        {!collapsed && (
          <Link to={tenantPath('/admin')} className="text-lg font-bold text-foreground">
            {"Admin"}
          </Link>
        )}
        <Button
          variant="light"
          isIconOnly
          onPress={onToggle}
          className="rounded-lg p-2 text-default-500 hover:bg-default-100 hover:text-foreground min-w-0 h-auto"
          aria-label={collapsed ? "Expand sidebar" : "Collapse sidebar"}
        >
          {collapsed ? <PanelLeft size={20} /> : <PanelLeftClose size={20} />}
        </Button>
      </div>

      {/* Search bar — hidden in icon-only mode */}
      {!collapsed && (
        <div className="shrink-0 px-2 py-2 border-b border-divider">
          <Input
            size="sm"
            variant="flat"
            placeholder={"Search admin..."}
            value={searchQuery}
            onValueChange={setSearchQuery}
            startContent={<Search size={14} className="text-default-400" />}
            endContent={
              searchQuery ? (
                <button
                  onClick={() => setSearchQuery('')}
                  className="text-default-400 hover:text-foreground"
                  aria-label="Clear search"
                >
                  <X size={14} />
                </button>
              ) : null
            }
            classNames={{
              input: 'text-sm',
              inputWrapper: 'h-8 min-h-8',
            }}
            aria-label={"Search admin..."}
          />
        </div>
      )}

      {/* Navigation */}
      <nav className="flex-1 min-h-0 overflow-y-auto px-2 py-3">

        {/* ── Search results view ──────────────────────────────────────────── */}
        {searchQuery.trim() ? (
          <ul className="space-y-0.5 py-1">
            {filteredResults.length === 0 ? (
              <li className="px-4 py-6 text-center text-sm text-default-400">
                {"No results found"}
              </li>
            ) : (
              filteredResults.map((item) => {
                const ItemIcon = item.icon;
                return (
                  <li key={item.href}>
                    <Link
                      to={tenantPath(item.href)}
                      onClick={() => {
                        setSearchQuery('');
                        trackVisit(item.label, item.href);
                      }}
                      className={`flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm transition-colors ${
                        isActive(item.href)
                          ? 'bg-primary/10 text-primary font-medium'
                          : 'text-default-500 hover:bg-default-100 hover:text-foreground'
                      }`}
                    >
                      <ItemIcon size={16} className="shrink-0" />
                      <span className="flex-1 truncate">{item.label}</span>
                      <span className="text-[10px] text-default-400 truncate max-w-[80px]">
                        {item.sectionLabel}
                      </span>
                    </Link>
                  </li>
                );
              })
            )}
          </ul>
        ) : (
          /* ── Normal zone view ───────────────────────────────────────────── */
          <ul className="space-y-1">
            {/* Dashboard — always pinned at top, no zone */}
            {(() => {
              const dashboard = sectionMap.get('dashboard');
              return dashboard ? renderSection(dashboard, null) : null;
            })()}

            {/* Broker Panel — pinned at top for visibility */}
            <li>
              {collapsed ? (
                <Link
                  to={tenantPath('/broker')}
                  className="flex items-center justify-center rounded-lg px-2 py-2 text-primary hover:bg-primary/10 transition-colors"
                  title={"Broker Panel"}
                >
                  <ShieldCheck size={18} />
                </Link>
              ) : (
                <Link
                  to={tenantPath('/broker')}
                  className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-primary hover:bg-primary/10 transition-colors"
                >
                  <ShieldCheck size={18} className="shrink-0" />
                  <span>{"Broker Panel"}</span>
                </Link>
              )}
            </li>

            {/* Community Caring Panel — pinned below Broker Panel, gated by caring_community feature */}
            {hasFeature('caring_community') && (
              <li>
                {collapsed ? (
                  <Link
                    to={tenantPath('/caring')}
                    className="flex items-center justify-center rounded-lg px-2 py-2 text-primary hover:bg-primary/10 transition-colors"
                    title={"Community Caring Panel"}
                  >
                    <Heart size={18} />
                  </Link>
                ) : (
                  <Link
                    to={tenantPath('/caring')}
                    className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-primary hover:bg-primary/10 transition-colors"
                  >
                    <Heart size={18} className="shrink-0" />
                    <span>{"Community Caring Panel"}</span>
                  </Link>
                )}
              </li>
            )}

            {/* Recent pages — shown if 2+ visits and sidebar is expanded */}
            {showRecent && (
              <>
                <li className="px-3 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-default-400">
                  {"Recent"}
                </li>
                {recentPages.map((page) => (
                  <li key={page.href}>
                    <Link
                      to={tenantPath(page.href)}
                      className={`flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm transition-colors ${
                        isActive(page.href)
                          ? 'bg-primary/10 text-primary font-medium'
                          : 'text-default-500 hover:bg-default-100 hover:text-foreground'
                      }`}
                    >
                      <Clock size={14} className="shrink-0 text-default-400" />
                      <span className="truncate">{hrefToLabel.get(page.href) ?? hrefToLabel.get(page.href.split('?')[0] ?? page.href) ?? page.label}</span>
                    </Link>
                  </li>
                ))}
                <li>
                  <div className="mx-3 mb-1 mt-1 border-b border-divider/50" />
                </li>
              </>
            )}

            {/* Zones */}
            {ZONES.map((zone, zoneIdx) => {
              const zoneSections = zone.sectionKeys
                .map((k) => sectionMap.get(k))
                .filter((s): s is NavSection => s !== undefined);

              if (zoneSections.length === 0) return null;

              const isZoneOpen = !collapsedZones.has(zone.key);

              return (
                <li key={zone.key}>
                  {/* Zone header */}
                  {!collapsed ? (
                    <button
                      onClick={() => toggleZone(zone.key)}
                      className="flex w-full items-center gap-1 px-3 py-1 mt-2 text-[10px] font-semibold uppercase tracking-wider text-default-400 hover:text-default-600 transition-colors"
                      aria-expanded={isZoneOpen}
                    >
                      <span className="flex-1 text-left">{t(zone.label)}</span>
                      <ChevronDown
                        size={11}
                        className={`shrink-0 transition-transform duration-200 ${isZoneOpen ? '' : '-rotate-90'}`}
                      />
                    </button>
                  ) : (
                    /* Icon-only mode: thin divider in place of zone header */
                    <div className="mx-2 my-2 border-t border-divider" />
                  )}

                  {/* Zone sections (accordion within zone) */}
                  {isZoneOpen && (
                    <ul className="space-y-1">
                      {zoneSections.map((section) => renderSection(section, zone.key))}
                    </ul>
                  )}

                  {/* Divider between zones (not after last zone) */}
                  {!collapsed && zoneIdx < ZONES.length - 1 && (
                    <div className="mx-3 mt-2 border-b border-divider/40" />
                  )}
                </li>
              );
            })}

            {/* Super Admin — always pinned at bottom, no zone */}
            {(() => {
              const superAdmin = sectionMap.get('super-admin');
              return superAdmin ? renderSection(superAdmin, null) : null;
            })()}
          </ul>
        )}

      </nav>
    </aside>
  );
}

export default AdminSidebar;
