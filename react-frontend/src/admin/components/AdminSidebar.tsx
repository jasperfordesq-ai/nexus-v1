// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Sidebar Navigation
 *
 * HeroUI-native, workflow-grouped admin navigation with:
 * - one globally open accordion section
 * - active route and active parent highlighting
 * - scroll-to-open behaviour for long sidebars
 * - recent pages and synonym-aware search
 * - attention strip for operational items that need review
 */

import { Fragment, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  Accordion,
  AccordionItem,
  Button,
  Input,
  ScrollShadow,
  Tooltip,
} from '@heroui/react';
import { useAuth, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import LayoutDashboard from 'lucide-react/icons/layout-dashboard';
import Users from 'lucide-react/icons/users';
import ListChecks from 'lucide-react/icons/list-checks';
import Newspaper from 'lucide-react/icons/newspaper';
import Trophy from 'lucide-react/icons/trophy';
import Megaphone from 'lucide-react/icons/megaphone';
import Coins from 'lucide-react/icons/coins';
import Building2 from 'lucide-react/icons/building-2';
import Globe from 'lucide-react/icons/globe';
import Settings from 'lucide-react/icons/settings';
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
import KeyIcon from 'lucide-react/icons/key';
import ShieldCheck from 'lucide-react/icons/shield-check';
import Heart from 'lucide-react/icons/heart';
import HelpCircle from 'lucide-react/icons/help-circle';
import Timer from 'lucide-react/icons/timer';
import Contact from 'lucide-react/icons/contact';
import StickyNote from 'lucide-react/icons/sticky-note';
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
import type { LucideIcon } from 'lucide-react';

interface NavItem {
  label: string;
  href: string;
  icon: LucideIcon;
  badge?: string;
  group?: string;
  keywords?: string[];
  attention?: string;
}

interface NavSection {
  key: string;
  label: string;
  icon: LucideIcon;
  href?: string;
  items?: NavItem[];
  zone: NavZoneKey | 'pinned';
}

type NavZoneKey =
  | 'overview'
  | 'people'
  | 'community'
  | 'safety'
  | 'communications'
  | 'growth'
  | 'commerce'
  | 'platform'
  | 'diagnostics';

interface NavZone {
  key: NavZoneKey;
  label: string;
  sectionKeys: string[];
}

interface RecentPage {
  label: string;
  href: string;
  visitedAt: number;
}

interface FilteredNavItem extends NavItem {
  sectionLabel: string;
}

const RECENT_PAGES_KEY = 'admin_recent_pages';
const RECENT_PAGES_MAX = 5;

const ZONES: NavZone[] = [
  { key: 'overview', label: 'zone_overview', sectionKeys: ['dashboard', 'broker-panel'] },
  { key: 'people', label: 'zone_people', sectionKeys: ['users', 'crm'] },
  { key: 'community', label: 'zone_community', sectionKeys: ['caring_community', 'community', 'listings', 'content', 'jobs'] },
  { key: 'safety', label: 'zone_safety', sectionKeys: ['moderation', 'matching'] },
  { key: 'communications', label: 'zone_communications', sectionKeys: ['communications', 'marketing', 'advertising'] },
  { key: 'growth', label: 'zone_growth', sectionKeys: ['engagement', 'analytics', 'discovery'] },
  { key: 'commerce', label: 'zone_commerce', sectionKeys: ['financial', 'marketplace'] },
  { key: 'platform', label: 'zone_platform', sectionKeys: ['system', 'enterprise', 'federation', 'integrations'] },
  { key: 'diagnostics', label: 'zone_diagnostics', sectionKeys: ['intelligence'] },
];

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
  const updated = [page, ...existing.filter((p) => p.href !== page.href)].slice(0, RECENT_PAGES_MAX);
  try {
    localStorage.setItem(RECENT_PAGES_KEY, JSON.stringify(updated));
  } catch {
    // Ignore storage quota and private-mode errors.
  }
  return updated;
}

function fuzzyMatch(query: string, target: string): boolean {
  if (!query) return true;
  const q = query.toLowerCase().trim();
  const t = target.toLowerCase();
  if (t.includes(q)) return true;
  let qi = 0;
  for (let ti = 0; ti < t.length && qi < q.length; ti++) {
    if (t[ti] === q[qi]) qi++;
  }
  return qi === q.length;
}

function getPathAndQuery(href: string) {
  const [path = '', rawQuery = ''] = href.split('?');
  return { path, rawQuery };
}

function keyword(...words: string[]): string[] {
  return words;
}

function useAdminNav(safeguardingFlagCount: number): NavSection[] {
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
    const communityItems: NavItem[] = [
      ...(hasFeature('groups') ? [
        { label: t('groups'), href: '/admin/groups', icon: Users, keywords: keyword('clubs', 'circles', 'communities') },
        { label: t('group_types'), href: '/admin/groups/types', icon: FolderTree },
        { label: t('group_recommendations'), href: '/admin/groups/recommendations', icon: Brain },
        { label: t('group_ranking'), href: '/admin/groups/ranking', icon: Trophy },
      ] : []),
      ...(hasFeature('events') ? [{ label: t('events'), href: '/admin/events', icon: Calendar }] : []),
      ...(hasFeature('polls') ? [{ label: t('polls'), href: '/admin/polls', icon: BarChart2 }] : []),
      ...(hasFeature('goals') ? [{ label: t('goals'), href: '/admin/goals', icon: Target }] : []),
      ...(hasFeature('ideation_challenges') ? [{ label: t('ideation_challenges'), href: '/admin/ideation', icon: Lightbulb }] : []),
      ...(hasFeature('volunteering') ? [{ label: t('volunteering'), href: '/admin/volunteering', icon: Heart }] : []),
    ];

    const sections: NavSection[] = [
      {
        key: 'dashboard',
        label: t('dashboard'),
        icon: LayoutDashboard,
        href: '/admin',
        zone: 'overview',
      },
      {
        key: 'broker-panel',
        label: t('broker_panel'),
        icon: ShieldCheck,
        href: '/broker',
        zone: 'overview',
      },
      {
        key: 'users',
        label: t('users'),
        icon: Users,
        zone: 'people',
        items: [
          { label: t('all_users'), href: '/admin/users', icon: Users, keywords: keyword('members', 'accounts') },
          { label: t('pending_approvals'), href: '/admin/users?filter=pending', icon: UserCheck, keywords: keyword('approval', 'waiting') },
        ],
      },
      {
        key: 'crm',
        label: t('crm'),
        icon: Contact,
        zone: 'people',
        items: [
          { label: t('crm_dashboard'), href: '/admin/crm', icon: Contact },
          { label: t('member_notes'), href: '/admin/crm/notes', icon: StickyNote },
          { label: t('coordinator_tasks'), href: '/admin/crm/tasks', icon: ClipboardList },
          { label: t('member_tags'), href: '/admin/crm/tags', icon: Tag },
          { label: t('activity_timeline'), href: '/admin/crm/timeline', icon: Activity },
          { label: t('onboarding_funnel'), href: '/admin/crm/funnel', icon: Filter },
        ],
      },
      ...(hasFeature('caring_community') ? [{
        key: 'caring_community',
        label: t('caring_community'),
        icon: Heart,
        href: '/caring',
        zone: 'community' as const,
      }] : []),
      ...(communityItems.length > 0 ? [{
        key: 'community',
        label: t('community'),
        icon: Users,
        zone: 'community' as const,
        items: communityItems,
      }] : []),
      ...(hasModule('listings') ? [{
        key: 'listings',
        label: t('listings'),
        icon: ListChecks,
        zone: 'community' as const,
        items: [{ label: t('all_content'), href: '/admin/listings', icon: ListChecks }],
      }] : []),
      {
        key: 'content',
        label: t('content'),
        icon: Newspaper,
        zone: 'community',
        items: [
          ...(hasFeature('blog') ? [{ label: t('blog_posts'), href: '/admin/blog', icon: FileText }] : []),
          ...(hasFeature('resources') ? [{ label: t('resources'), href: '/admin/resources', icon: BookOpen }] : []),
          { label: t('pages'), href: '/admin/pages', icon: FileText },
          { label: t('landing_page'), href: '/admin/landing-page', icon: Palette },
          { label: t('menus'), href: '/admin/menus', icon: Menu },
          { label: t('categories'), href: '/admin/categories', icon: FolderTree },
          { label: t('attributes'), href: '/admin/attributes', icon: Tags },
        ],
      },
      ...(hasFeature('job_vacancies') ? [{
        key: 'jobs',
        label: t('jobs'),
        icon: Briefcase,
        zone: 'community' as const,
        items: [
          { label: t('job_vacancies'), href: '/admin/jobs', icon: Briefcase },
          { label: t('job_moderation'), href: '/admin/jobs/moderation', icon: ShieldCheck },
          { label: t('job_pipeline'), href: '/admin/jobs/pipeline', icon: Handshake },
          { label: t('job_bias_audit'), href: '/admin/jobs/bias-audit', icon: BarChart3 },
          { label: t('job_templates'), href: '/admin/jobs/templates', icon: FileText },
        ],
      }] : []),
      {
        key: 'moderation',
        label: t('moderation'),
        icon: Shield,
        zone: 'safety',
        items: [
          { label: t('content_queue'), href: '/admin/moderation/queue', icon: Shield, badge: t('badge_new'), attention: 'info' },
          ...(hasModule('feed') ? [{ label: t('feed_posts'), href: '/admin/moderation/feed', icon: MessageSquare }] : []),
          { label: t('comments'), href: '/admin/moderation/comments', icon: MessageCircle },
          ...(hasFeature('reviews') ? [{ label: t('reviews'), href: '/admin/moderation/reviews', icon: Star }] : []),
          { label: t('reports'), href: '/admin/moderation/reports', icon: Flag },
        ],
      },
      {
        key: 'matching',
        label: t('matching'),
        icon: Zap,
        zone: 'safety',
        items: [
          ...(hasFeature('exchange_workflow') ? [
            { label: t('smart_matching'), href: '/admin/smart-matching', icon: Brain },
            { label: t('match_approvals'), href: '/admin/match-approvals', icon: UserCheck, badge: t('badge_new'), attention: 'info' },
          ] : []),
          {
            label: t('safeguarding'),
            href: '/admin/safeguarding',
            icon: ShieldCheck,
            badge: safeguardingFlagCount > 0 ? String(safeguardingFlagCount) : undefined,
            attention: safeguardingFlagCount > 0 ? 'danger' : undefined,
          },
          { label: t('member_safeguarding'), href: '/admin/safeguarding?tab=preferences', icon: Users },
          { label: t('safeguarding_options'), href: '/admin/safeguarding-options', icon: Shield },
        ],
      },
      {
        key: 'communications',
        label: t('communications'),
        icon: Mail,
        zone: 'communications',
        items: [
          { label: t('email_settings'), href: '/admin/email-settings', icon: Mail, keywords: keyword('smtp', 'mail', 'sendgrid', 'from address') },
          { label: t('email_deliverability'), href: '/admin/email-deliverability', icon: Mail, keywords: keyword('mail failed', 'smtp health', 'delivery') },
          { label: t('deliverability'), href: '/admin/deliverability', icon: Mail, keywords: keyword('deliverables', 'scheduled email') },
        ],
      },
      {
        key: 'marketing',
        label: t('marketing'),
        icon: Megaphone,
        zone: 'communications',
        items: [
          ...(hasFeature('newsletter') ? [
            { label: t('newsletters'), href: '/admin/newsletters', icon: Megaphone },
            { label: t('subscribers'), href: '/admin/newsletters/subscribers', icon: Users },
            { label: t('templates'), href: '/admin/newsletters/templates', icon: FileText },
            { label: t('bounces'), href: '/admin/newsletters/bounces', icon: AlertTriangle, keywords: keyword('email failed', 'mail returned') },
            { label: t('send_time_optimizer'), href: '/admin/newsletters/send-time-optimizer', icon: Clock },
            { label: t('diagnostics'), href: '/admin/newsletters/diagnostics', icon: Stethoscope },
          ] : []),
        ],
      },
      ...(hasFeature('local_advertising') ? [{
        key: 'advertising',
        label: t('advertising'),
        icon: Megaphone,
        zone: 'communications' as const,
        items: [
          { label: t('ad_campaigns'), href: '/admin/advertising/campaigns', icon: Megaphone },
          { label: t('push_campaigns'), href: '/admin/advertising/push-campaigns', icon: BellRing },
        ],
      }] : []),
      ...(hasFeature('gamification') ? [{
        key: 'engagement',
        label: t('engagement'),
        icon: Trophy,
        zone: 'growth' as const,
        items: [
          { label: t('gamification_hub'), href: '/admin/gamification', icon: Gamepad2 },
          { label: t('campaigns'), href: '/admin/gamification/campaigns', icon: Target },
          { label: t('custom_badges'), href: '/admin/custom-badges', icon: Medal },
          { label: t('analytics'), href: '/admin/gamification/analytics', icon: BarChart3 },
        ],
      }] : []),
      {
        key: 'analytics',
        label: t('analytics_reporting'),
        icon: BarChart3,
        zone: 'growth',
        items: [
          { label: t('community_analytics'), href: '/admin/community-analytics', icon: BarChart3 },
          { label: t('impact_report'), href: '/admin/impact-report', icon: FileText },
          { label: t('member_reports'), href: '/admin/reports/members', icon: Users },
          ...(hasModule('wallet') ? [{ label: t('hours_reports'), href: '/admin/reports/hours', icon: Clock }] : []),
          { label: t('inactive_members'), href: '/admin/reports/inactive-members', icon: UserX },
        ],
      },
      {
        key: 'discovery',
        label: t('growth_discovery'),
        icon: Search,
        zone: 'growth',
        items: [
          { label: t('seo_overview'), href: '/admin/seo', icon: Search },
          ...(isPlatformSuperAdmin ? [{ label: t('prerender_engine'), href: '/admin/seo/prerender', icon: Zap }] : []),
          { label: t('error_404_tracking'), href: '/admin/404-errors', icon: AlertTriangle, keywords: keyword('not found', 'broken links') },
        ],
      },
      {
        key: 'financial',
        label: t('financial'),
        icon: Coins,
        zone: 'commerce',
        items: [
          ...(hasModule('wallet') ? [
            { label: t('timebanking'), href: '/admin/timebanking', icon: Clock },
            { label: t('fraud_alerts'), href: '/admin/timebanking/alerts', icon: AlertTriangle },
            { label: t('org_wallets'), href: '/admin/timebanking/org-wallets', icon: Wallet },
            { label: t('starting_balances'), href: '/admin/timebanking/starting-balances', icon: Wallet },
          ] : []),
          { label: t('plans_pricing'), href: '/admin/plans', icon: CreditCard },
          { label: t('billing'), href: '/admin/billing', icon: CreditCard },
          ...(hasFeature('member_premium') ? [
            { label: t('member_premium'), href: '/admin/member-premium', icon: Crown },
            { label: t('premium_subscribers'), href: '/admin/member-premium/subscribers', icon: Users },
          ] : []),
        ],
      },
      ...(hasFeature('marketplace') ? [{
        key: 'marketplace',
        label: t('marketplace'),
        icon: ShoppingBag,
        zone: 'commerce' as const,
        items: [
          { label: t('marketplace_dashboard'), href: '/admin/marketplace', icon: ShoppingBag },
          { label: t('marketplace_moderation'), href: '/admin/marketplace/moderation', icon: ShieldCheck },
          { label: t('marketplace_sellers'), href: '/admin/marketplace/sellers', icon: Store },
        ],
      }] : []),
      {
        key: 'system',
        label: t('platform_operations'),
        icon: Settings,
        zone: 'platform',
        items: [
          { label: t('settings'), href: '/admin/settings', icon: Settings },
          { label: t('onboarding_settings'), href: '/admin/onboarding-settings', icon: Heart },
          { label: t('module_configuration'), href: '/admin/module-configuration', icon: Puzzle },
          { label: t('operations'), href: '/admin/operations', icon: Activity },
          { label: t('translation_config'), href: '/admin/translation-config', icon: Languages },
          { label: t('activity_log'), href: '/admin/activity-log', icon: Activity },
          { label: t('cron_jobs'), href: '/admin/cron-jobs', icon: Timer },
          { label: t('cron_logs'), href: '/admin/cron-jobs/logs', icon: FileText },
          { label: t('cron_setup'), href: '/admin/cron-jobs/setup', icon: Wrench },
          ...(isPlatformSuperAdmin ? [{ label: t('cron_settings'), href: '/admin/cron-jobs/settings', icon: Settings }] : []),
        ],
      },
      {
        key: 'enterprise',
        label: t('enterprise'),
        icon: Building2,
        zone: 'platform',
        items: [
          { label: t('enterprise_dashboard'), href: '/admin/enterprise', icon: Building2 },
          { label: t('roles_permissions'), href: '/admin/enterprise/roles', icon: KeyIcon },
          { label: t('gdpr_dashboard'), href: '/admin/enterprise/gdpr', icon: ShieldCheck },
          { label: t('legal_documents'), href: '/admin/legal-documents', icon: FileText },
          { label: t('compliance_dashboard'), href: '/admin/legal-documents/compliance', icon: ShieldCheck },
          { label: t('monitoring'), href: '/admin/enterprise/monitoring', icon: Heart },
        ],
      },
      {
        key: 'intelligence',
        label: t('intelligence_diagnostics'),
        icon: Brain,
        zone: 'diagnostics',
        items: [
          ...(hasFeature('ai_chat') ? [
            { label: t('ai_settings'), href: '/admin/ai-settings', icon: Brain },
            { label: t('ai_module_docs'), href: '/admin/ai/module-docs', icon: Brain },
            { label: t('ai_trace_metrics'), href: '/admin/ai/metrics', icon: Brain },
          ] : []),
          ...(hasFeature('ai_agents') ? [
            { label: t('ai_agents'), href: '/admin/agents', icon: Bot },
            { label: t('agent_proposals'), href: '/admin/agents/proposals', icon: Bot },
            { label: t('agent_runs'), href: '/admin/agents/runs', icon: Bot },
          ] : []),
          { label: t('algorithm_settings'), href: '/admin/algorithm-settings', icon: Cpu },
          { label: t('diagnostics'), href: '/admin/matching-diagnostic', icon: Stethoscope },
          { label: t('match_debug_panel'), href: '/admin/match-debug', icon: Target },
        ],
      },
    ];

    if (hasFeature('federation')) {
      sections.push({
        key: 'federation',
        label: t('partner_timebanks'),
        icon: Globe,
        zone: 'platform',
        items: [
          { label: t('federation_settings'), href: '/admin/federation', icon: Settings },
          { label: t('federation_partnerships'), href: '/admin/federation/partnerships', icon: ArrowLeftRight },
          { label: t('federation_directory'), href: '/admin/federation/directory', icon: Globe },
          { label: t('federation_credit_agreements'), href: '/admin/federation/credit-agreements', icon: Handshake },
          { label: t('federation_neighborhoods'), href: '/admin/federation/neighborhoods', icon: MapPin },
          { label: t('federation_analytics'), href: '/admin/federation/analytics', icon: BarChart3 },
          { label: t('federation_api_keys'), href: '/admin/federation/api-keys', icon: KeyIcon },
          { label: t('federation_api_docs'), href: '/admin/federation/api-docs', icon: BookOpen },
          { label: t('federation_external_partners'), href: '/admin/federation/external-partners', icon: Globe },
          { label: t('federation_cc_config'), href: '/admin/federation/cc-config', icon: Network },
          { label: t('federation_webhooks'), href: '/admin/federation/webhooks', icon: Webhook },
          { label: t('federation_activity'), href: '/admin/federation/activity', icon: Activity },
          { label: t('federation_data_management'), href: '/admin/federation/data', icon: Database },
          { label: t('federation_aggregates'), href: '/admin/federation/aggregates', icon: ShieldCheck },
        ],
      });
    }

    if (hasFeature('partner_api')) {
      sections.push({
        key: 'integrations',
        label: t('integrations'),
        icon: Webhook,
        zone: 'platform',
        items: [{ label: t('api_partners'), href: '/admin/api-partners', icon: KeyIcon }],
      });
    }

    if (isSuperAdmin) {
      sections.push({
        key: 'super-admin',
        label: t('super_admin'),
        icon: Crown,
        zone: 'pinned',
        items: [
          { label: t('super_dashboard'), href: '/admin/super', icon: Crown },
          { label: t('national_kiss_dashboard'), href: '/admin/national/kiss', icon: Landmark },
          { label: t('provisioning_queue'), href: '/admin/provisioning-requests', icon: Building2 },
          { label: t('super_tenants'), href: '/admin/super/tenants', icon: Building2 },
          { label: t('super_hierarchy'), href: '/admin/super/tenants/hierarchy', icon: Network },
          { label: t('super_cross_tenant_users'), href: '/admin/super/users', icon: Users },
          { label: t('super_bulk_operations'), href: '/admin/super/bulk', icon: ListChecks },
          { label: t('super_audit_log'), href: '/admin/super/audit', icon: ScrollText },
          { label: t('super_federation_controls'), href: '/admin/super/federation', icon: Globe },
          { label: t('super_federation_whitelist'), href: '/admin/super/federation/whitelist', icon: Shield },
          { label: t('super_federation_partnerships'), href: '/admin/super/federation/partnerships', icon: Handshake },
          { label: t('super_federation_audit'), href: '/admin/super/federation/audit', icon: FileSearch },
          { label: t('regional_analytics_paid'), href: '/admin/regional-analytics/subscriptions', icon: BarChart3 },
        ],
      });
    }

    return sections.filter((section) => section.href || (section.items?.length ?? 0) > 0);
  }, [hasFeature, hasModule, isPlatformSuperAdmin, isSuperAdmin, safeguardingFlagCount, t]);
}

interface AdminSidebarProps {
  collapsed?: boolean;
  onToggle?: () => void;
}

export function AdminSidebar({ collapsed = false, onToggle = () => undefined }: AdminSidebarProps) {
  const { t } = useTranslation('admin_nav');
  const location = useLocation();
  const { tenantPath } = useTenant();
  const [searchQuery, setSearchQuery] = useState('');
  const [recentPages, setRecentPages] = useState<RecentPage[]>(() => readRecentPages());
  const [safeguardingFlagCount, setSafeguardingFlagCount] = useState(0);
  const sections = useAdminNav(safeguardingFlagCount);
  const sectionRefs = useRef(new Map<string, HTMLDivElement>());

  const allItems = useMemo(() => {
    return sections.flatMap((section) => [
      ...(section.href ? [{ ...section, label: section.label, href: section.href, icon: section.icon, sectionLabel: section.label }] : []),
      ...(section.items ?? []).map((item) => ({ ...item, sectionLabel: section.label })),
    ]);
  }, [sections]);

  const activeHref = useMemo(() => {
    let bestHref: string | null = null;
    let bestScore = -1;

    for (const item of allItems) {
      const { path, rawQuery } = getPathAndQuery(item.href);
      const fullPath = tenantPath(path);
      const isDashboard = path === '/admin';

      if (isDashboard && location.pathname !== fullPath) continue;
      if (!isDashboard && !(location.pathname === fullPath || location.pathname.startsWith(`${fullPath}/`))) continue;

      let score = path.length;
      if (rawQuery) {
        const required = new URLSearchParams(rawQuery);
        const current = new URLSearchParams(location.search);
        let queryMatches = true;
        for (const [key, value] of required.entries()) {
          if (current.get(key) !== value) {
            queryMatches = false;
            break;
          }
        }
        if (!queryMatches) continue;
        score += 1000;
      } else if (new URLSearchParams(location.search).get('filter')) {
        score -= 100;
      }

      if (score > bestScore) {
        bestScore = score;
        bestHref = item.href;
      }
    }

    return bestHref;
  }, [allItems, location.pathname, location.search, tenantPath]);

  const activeSectionKey = useMemo(() => {
    if (!activeHref) return null;
    return sections.find((section) => section.href === activeHref || section.items?.some((item) => item.href === activeHref))?.key ?? null;
  }, [activeHref, sections]);

  const [openSection, setOpenSection] = useState<string | null>(() => activeSectionKey);

  useEffect(() => {
    if (!collapsed && activeSectionKey) {
      setOpenSection(activeSectionKey);
    }
  }, [activeSectionKey, collapsed]);

  useEffect(() => {
    if (!openSection || collapsed) return;
    window.setTimeout(() => {
      sectionRefs.current.get(openSection)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }, 80);
  }, [collapsed, openSection]);

  const fetchSafeguardingFlags = useCallback(async () => {
    try {
      const res = await api.get<{ unreviewed_flags?: number } | { data: { unreviewed_flags?: number } }>(
        '/v2/admin/safeguarding/dashboard',
      );
      if (res.success && res.data) {
        const payload = 'data' in res.data ? res.data.data : res.data;
        setSafeguardingFlagCount(Number(payload?.unreviewed_flags ?? 0));
      }
    } catch {
      // Tenants without safeguarding enabled can 404 here.
    }
  }, []);

  useEffect(() => {
    fetchSafeguardingFlags();
    const interval = window.setInterval(fetchSafeguardingFlags, 60000);
    return () => window.clearInterval(interval);
  }, [fetchSafeguardingFlags]);

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

  const filteredResults = useMemo((): FilteredNavItem[] => {
    const query = searchQuery.trim();
    if (!query) return [];

    return sections.flatMap((section) =>
      (section.items ?? [])
        .filter((item) =>
          fuzzyMatch(query, `${item.label} ${section.label} ${(item.keywords ?? []).join(' ')}`),
        )
        .map((item) => ({ ...item, sectionLabel: section.label })),
    );
  }, [sections, searchQuery]);

  const attentionItems = useMemo(() => {
    return sections
      .flatMap((section) => (section.items ?? []).map((item) => ({ ...item, sectionLabel: section.label })))
      .filter((item) => item.attention && item.badge)
      .slice(0, 4);
  }, [sections]);

  const trackVisit = (label: string, href: string) => {
    setRecentPages(saveRecentPage({ label, href, visitedAt: Date.now() }));
  };

  const isActive = (href: string) => href === activeHref;

  const renderBadge = (item: NavItem) => {
    if (!item.badge) return null;
    const tone = item.attention === 'danger' ? 'bg-danger text-danger-foreground' : 'bg-primary text-primary-foreground';
    return <span className={`ml-auto rounded-full px-1.5 py-0.5 text-[10px] font-bold ${tone}`}>{item.badge}</span>;
  };

  const renderNavLink = (item: NavItem, compact = false) => {
    const ItemIcon = item.icon;
    const active = isActive(item.href);

    return (
      <Link
        to={tenantPath(item.href)}
        onClick={() => {
          setSearchQuery('');
          trackVisit(item.label, item.href);
        }}
        aria-current={active ? 'page' : undefined}
        className={`group relative flex min-h-8 items-center gap-2 rounded-lg px-3 py-1.5 text-sm transition-colors ${
          active
            ? 'bg-primary/10 text-primary font-semibold shadow-sm'
            : 'text-default-500 hover:bg-default-100 hover:text-foreground'
        } ${compact ? 'justify-center px-2' : ''}`}
      >
        {active && !compact && <span className="absolute left-0 top-1.5 h-5 w-0.5 rounded-r bg-primary" />}
        <ItemIcon size={16} className="shrink-0" />
        {!compact && <span className="min-w-0 flex-1 truncate">{item.label}</span>}
        {!compact && renderBadge(item)}
      </Link>
    );
  };

  const renderCollapsedSection = (section: NavSection) => {
    const Icon = section.icon;
    const active = activeSectionKey === section.key;
    const href = section.href ?? section.items?.[0]?.href ?? '/admin';

    return (
      <Tooltip key={section.key} content={section.label} placement="right" delay={250}>
        <Link
          to={tenantPath(href)}
          aria-current={active ? 'page' : undefined}
          className={`flex items-center justify-center rounded-lg px-2 py-2 transition-colors ${
            active ? 'bg-primary/10 text-primary' : 'text-default-500 hover:bg-default-100 hover:text-foreground'
          }`}
        >
          <Icon size={18} />
        </Link>
      </Tooltip>
    );
  };

  const renderSection = (section: NavSection) => {
    const Icon = section.icon;
    const active = activeSectionKey === section.key;

    if (section.href && !section.items) {
      return (
        <div
          key={section.key}
          ref={(node) => {
            if (node) sectionRefs.current.set(section.key, node);
            else sectionRefs.current.delete(section.key);
          }}
        >
          {renderNavLink({ label: section.label, href: section.href, icon: section.icon })}
        </div>
      );
    }

    return (
      <div
        key={section.key}
        ref={(node) => {
          if (node) sectionRefs.current.set(section.key, node);
          else sectionRefs.current.delete(section.key);
        }}
      >
        <Accordion
          selectedKeys={openSection === section.key ? new Set<string>([section.key]) : new Set<string>()}
          selectionMode="single"
          variant="light"
          onSelectionChange={(keys) => {
            const selected = Array.from(keys as Set<string>)[0]?.toString() ?? null;
            setOpenSection(selected === section.key ? section.key : null);
          }}
          itemClasses={{
            base: 'py-0',
            trigger: `rounded-lg px-3 py-2 text-sm transition-colors ${
              active ? 'bg-primary/10 text-primary font-semibold' : 'text-default-600 hover:bg-default-100 hover:text-foreground'
            }`,
            title: 'text-sm font-medium',
            content: 'pb-1 pt-0',
            indicator: 'text-default-400',
          }}
        >
          <AccordionItem
            key={section.key}
            aria-label={section.label}
            title={
              <span className="flex items-center gap-3">
                <Icon size={20} className="shrink-0" />
                <span className="min-w-0 flex-1 truncate text-left">{section.label}</span>
                {section.items?.some((item) => item.attention === 'danger' && item.badge) && (
                  <span className="ml-auto h-2 w-2 rounded-full bg-danger" />
                )}
              </span>
            }
          >
            <ul className="ml-4 mt-1 space-y-0.5 border-l border-divider pl-3">
              {(section.items ?? []).map((item, idx) => (
                <Fragment key={item.href}>
                  {item.group && (
                    <li className={`px-3 text-[10px] font-semibold uppercase tracking-wider text-default-400 ${idx === 0 ? 'pb-0.5' : 'pb-0.5 pt-2'}`}>
                      {item.group}
                    </li>
                  )}
                  <li>{renderNavLink(item)}</li>
                </Fragment>
              ))}
            </ul>
          </AccordionItem>
        </Accordion>
      </div>
    );
  };

  const showRecent = !collapsed && recentPages.length >= 2 && !searchQuery.trim();
  const groupedSections = ZONES.map((zone) => ({
    ...zone,
    sections: zone.sectionKeys
      .map((key) => sections.find((section) => section.key === key))
      .filter((section): section is NavSection => Boolean(section)),
  })).filter((zone) => zone.sections.length > 0);
  const superAdmin = sections.find((section) => section.key === 'super-admin');

  return (
    <aside
      className={`fixed left-0 top-0 z-40 flex h-screen flex-col border-r border-divider bg-content1 transition-all duration-300 ${
        collapsed ? 'w-16' : 'w-64'
      }`}
    >
      <div className="flex h-16 shrink-0 items-center justify-between border-b border-divider px-4">
        {!collapsed && (
          <Link to={tenantPath('/admin')} className="text-lg font-bold text-foreground">
            {t('admin')}
          </Link>
        )}
        <Button
          variant="light"
          isIconOnly
          onPress={onToggle}
          className="h-auto min-w-0 rounded-lg p-2 text-default-500 hover:bg-default-100 hover:text-foreground"
          aria-label={collapsed ? t('expand_sidebar') : t('collapse_sidebar')}
        >
          {collapsed ? <PanelLeft size={20} /> : <PanelLeftClose size={20} />}
        </Button>
      </div>

      {!collapsed && (
        <div className="shrink-0 border-b border-divider px-2 py-2">
          <Input
            size="sm"
            variant="flat"
            type="search"
            name="admin-sidebar-search"
            autoComplete="off"
            placeholder={t('search_nav')}
            value={searchQuery}
            onValueChange={setSearchQuery}
            startContent={<Search size={14} className="text-default-400" />}
            endContent={
              searchQuery ? (
                <button
                  onClick={() => setSearchQuery('')}
                  className="text-default-400 hover:text-foreground"
                  aria-label={t('clear_search')}
                >
                  <X size={14} />
                </button>
              ) : null
            }
            classNames={{ input: 'text-sm', inputWrapper: 'h-8 min-h-8' }}
            aria-label={t('search_nav')}
          />
        </div>
      )}

      <ScrollShadow as="nav" className="min-h-0 flex-1 px-2 py-3" hideScrollBar>
        {searchQuery.trim() ? (
          <ul className="space-y-0.5 py-1">
            {filteredResults.length === 0 ? (
              <li className="px-4 py-6 text-center text-sm text-default-400">{t('no_results')}</li>
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
                          ? 'bg-primary/10 text-primary font-semibold'
                          : 'text-default-500 hover:bg-default-100 hover:text-foreground'
                      }`}
                    >
                      <ItemIcon size={16} className="shrink-0" />
                      <span className="min-w-0 flex-1 truncate">{item.label}</span>
                      <span className="max-w-[80px] truncate text-[10px] text-default-400">{item.sectionLabel}</span>
                    </Link>
                  </li>
                );
              })
            )}
          </ul>
        ) : collapsed ? (
          <ul className="space-y-1">
            {sections.filter((section) => section.key !== 'super-admin').map((section) => (
              <li key={section.key}>{renderCollapsedSection(section)}</li>
            ))}
            {superAdmin && (
              <>
                <li><div className="mx-2 my-2 border-t border-divider" /></li>
                <li>{renderCollapsedSection(superAdmin)}</li>
              </>
            )}
          </ul>
        ) : (
          <ul className="space-y-1">
            {attentionItems.length > 0 && (
              <>
                <li className="px-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-danger">
                  {t('needs_attention')}
                </li>
                {attentionItems.map((item) => (
                  <li key={`attention-${item.href}`}>{renderNavLink(item)}</li>
                ))}
                <li><div className="mx-3 mb-1 mt-1 border-b border-divider/50" /></li>
              </>
            )}

            {showRecent && (
              <>
                <li className="px-3 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-default-400">
                  {t('recent')}
                </li>
                {recentPages.map((page) => (
                  <li key={page.href}>
                    <Link
                      to={tenantPath(page.href)}
                      className={`flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm transition-colors ${
                        isActive(page.href)
                          ? 'bg-primary/10 text-primary font-semibold'
                          : 'text-default-500 hover:bg-default-100 hover:text-foreground'
                      }`}
                    >
                      <Clock size={14} className="shrink-0 text-default-400" />
                      <span className="truncate">{hrefToLabel.get(page.href) ?? hrefToLabel.get(page.href.split('?')[0] ?? page.href) ?? page.label}</span>
                    </Link>
                  </li>
                ))}
                <li><div className="mx-3 mb-1 mt-1 border-b border-divider/50" /></li>
              </>
            )}

            {groupedSections.map((zone, zoneIdx) => (
              <li key={zone.key}>
                <div className="px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-wider text-default-400">
                  {t(zone.label)}
                </div>
                <div className="space-y-1">{zone.sections.map((section) => renderSection(section))}</div>
                {zoneIdx < groupedSections.length - 1 && <div className="mx-3 mt-2 border-b border-divider/40" />}
              </li>
            ))}

            {superAdmin && (
              <li>
                <div className="my-2 border-t border-warning/30" />
                {renderSection(superAdmin)}
              </li>
            )}
          </ul>
        )}
      </ScrollShadow>

      <div className="shrink-0 border-t border-divider px-2 py-2">
        <Tooltip content={t('help_centre')} placement="right" isDisabled={!collapsed}>
          <Link
            to={tenantPath('/admin/help')}
            className={`flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors hover:bg-default-100 ${
              location.pathname.includes('/admin/help') ? 'bg-primary/10 text-primary' : 'text-default-600'
            } ${collapsed ? 'justify-center' : ''}`}
            title={t('help_centre')}
          >
            <HelpCircle size={16} className="shrink-0" />
            {!collapsed && <span>{t('help_centre')}</span>}
          </Link>
        </Tooltip>
      </div>
    </aside>
  );
}

export default AdminSidebar;
