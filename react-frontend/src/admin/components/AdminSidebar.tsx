// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Sidebar Navigation
 * Collapsible sidebar matching the PHP admin navigation structure.
 * Uses Lucide icons (consistent with React frontend).
 */

import { useState, useEffect, useCallback, useMemo } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Button } from '@heroui/react';
import { useAuth, useTenant } from '@/contexts';
import { adminBroker } from '../api/adminApi';
import {
  LayoutDashboard,
  Users,
  ListChecks,
  Newspaper,
  Trophy,
  Megaphone,
  Sparkles,
  Coins,
  Building2,
  Globe,
  Settings,
  ChevronDown,
  ChevronRight,
  PanelLeftClose,
  PanelLeft,
  UserCheck,
  FileText,
  Menu,
  FolderTree,
  Tags,
  Tag,
  Gamepad2,
  Medal,
  BarChart3,
  Zap,
  Target,
  Brain,
  Search,
  ArrowLeftRight,
  AlertTriangle,
  Clock,
  Wallet,
  CreditCard,
  Shield,
  Key,
  ShieldCheck,
  Heart,
  Cog,
  Timer,
  Contact,
  StickyNote,
  ClipboardList,
  Filter,
  Activity,
  Crown,
  Network,
  ScrollText,
  Mail,
  Wrench,
  Stethoscope,
  MessageSquare,
  MessageSquareWarning,
  MessageCircle,
  Star,
  Flag,
  Eye,
  Archive,
  FileCheck,
  UserX,
  DollarSign,
  Calendar,
  BarChart2,
  Lightbulb,
  Briefcase,
  BookOpen,
  Cpu,
  Handshake,
  Database,
  MapPin,
  FileSearch,
  Webhook,
  Puzzle,
  Palette,
  ShoppingBag,
  Store,
  Languages,
  type LucideIcon,
} from 'lucide-react';

// ─────────────────────────────────────────────────────────────────────────────
// Navigation config — mirrors PHP admin-navigation-config.php
// ─────────────────────────────────────────────────────────────────────────────

interface NavItem {
  label: string;
  href: string;
  icon: LucideIcon;
  badge?: string;
}

interface NavSection {
  key: string;
  label: string;
  icon: LucideIcon;
  href?: string;
  items?: NavItem[];
  condition?: string;
}

function useAdminNav(): NavSection[] {
  const { t } = useTranslation('admin_nav');
  const { hasFeature } = useTenant();
  const { user } = useAuth();

  const userRecord = user as Record<string, unknown> | null;
  const isSuperAdmin =
    (user?.role as string) === 'super_admin' ||
    userRecord?.is_super_admin === true ||
    userRecord?.is_tenant_super_admin === true;

  return useMemo(() => {
    const sections: NavSection[] = [
      {
        key: 'dashboard',
        label: t('dashboard'),
        icon: LayoutDashboard,
        href: '/admin',
      },
      {
        key: 'users',
        label: t('sidebar.users'),
        icon: Users,
        items: [
          { label: t('all_users'), href: '/admin/users', icon: Users },
          { label: t('pending_approvals'), href: '/admin/users?filter=pending', icon: UserCheck },
        ],
      },
      {
        key: 'crm',
        label: t('sidebar.crm'),
        icon: Contact,
        items: [
          { label: t('crm_dashboard'), href: '/admin/crm', icon: Contact },
          { label: t('member_notes'), href: '/admin/crm/notes', icon: StickyNote },
          { label: t('coordinator_tasks'), href: '/admin/crm/tasks', icon: ClipboardList },
          { label: t('member_tags'), href: '/admin/crm/tags', icon: Tag },
          { label: t('activity_timeline'), href: '/admin/crm/timeline', icon: Activity },
          { label: t('onboarding_funnel'), href: '/admin/crm/funnel', icon: Filter },
        ],
      },
      {
        key: 'listings',
        label: t('sidebar.listings'),
        icon: ListChecks,
        items: [
          { label: t('all_content'), href: '/admin/listings', icon: ListChecks },
        ],
      },
      {
        key: 'content',
        label: t('sidebar.content'),
        icon: Newspaper,
        items: [
          { label: t('blog_posts'), href: '/admin/blog', icon: FileText },
          { label: t('sidebar.resources'), href: '/admin/resources', icon: BookOpen },
          { label: t('pages'), href: '/admin/pages', icon: FileText },
          { label: t('landing_page', { defaultValue: 'Landing Page' }), href: '/admin/landing-page', icon: Palette },
          { label: t('menus'), href: '/admin/menus', icon: Menu },
          { label: t('sidebar.categories'), href: '/admin/categories', icon: FolderTree },
          { label: t('attributes'), href: '/admin/attributes', icon: Tags },
        ],
      },
      {
        key: 'engagement',
        label: t('engagement'),
        icon: Trophy,
        items: [
          { label: t('gamification_hub'), href: '/admin/gamification', icon: Gamepad2 },
          { label: t('campaigns'), href: '/admin/gamification/campaigns', icon: Target },
          { label: t('custom_badges'), href: '/admin/custom-badges', icon: Medal },
          { label: t('analytics'), href: '/admin/gamification/analytics', icon: BarChart3 },
        ],
      },
      {
        key: 'matching',
        label: t('sidebar.matching'),
        icon: Zap,
        items: [
          { label: t('smart_matching'), href: '/admin/smart-matching', icon: Brain },
          { label: t('match_approvals'), href: '/admin/match-approvals', icon: UserCheck, badge: 'NEW' },
          { label: t('broker_controls'), href: '/admin/broker-controls', icon: Shield },
          { label: t('message_review'), href: '/admin/broker-controls/messages', icon: MessageSquareWarning },
          { label: t('user_monitoring'), href: '/admin/broker-controls/monitoring', icon: Eye },
          { label: t('vetting_records'), href: '/admin/broker-controls/vetting', icon: ShieldCheck },
          { label: t('insurance_certificates'), href: '/admin/broker-controls/insurance', icon: FileCheck },
          { label: t('review_archive'), href: '/admin/broker-controls/archives', icon: Archive },
          { label: t('sidebar.safeguarding'), href: '/admin/safeguarding', icon: ShieldCheck },
          { label: t('safeguarding_options'), href: '/admin/safeguarding-options', icon: Shield },
        ],
      },
      {
        key: 'moderation',
        label: t('sidebar.moderation'),
        icon: Shield,
        items: [
          { label: t('content_queue'), href: '/admin/moderation/queue', icon: Shield, badge: 'NEW' },
          { label: t('feed_posts'), href: '/admin/moderation/feed', icon: MessageSquare },
          { label: t('comments'), href: '/admin/moderation/comments', icon: MessageCircle },
          { label: t('reviews'), href: '/admin/moderation/reviews', icon: Star },
          { label: t('reports'), href: '/admin/moderation/reports', icon: Flag },
        ],
      },
      {
        key: 'community',
        label: t('sidebar.community'),
        icon: Users,
        items: [
          { label: t('sidebar.groups'), href: '/admin/groups', icon: Users },
          { label: t('group_types'), href: '/admin/groups/types', icon: FolderTree },
          { label: t('group_recommendations'), href: '/admin/groups/recommendations', icon: Brain },
          { label: t('group_ranking'), href: '/admin/groups/ranking', icon: Trophy },
          { label: t('sidebar.events'), href: '/admin/events', icon: Calendar },
          { label: t('sidebar.polls'), href: '/admin/polls', icon: BarChart2 },
          { label: t('sidebar.goals'), href: '/admin/goals', icon: Target },
          { label: t('ideation_challenges'), href: '/admin/ideation', icon: Lightbulb },
          { label: t('sidebar.jobs'), href: '/admin/jobs', icon: Briefcase },
          { label: t('job_moderation'), href: '/admin/jobs/moderation', icon: ShieldCheck },
          { label: t('sidebar.volunteering'), href: '/admin/volunteering', icon: Heart },
        ],
      },
      ...(hasFeature('marketplace') ? [{
        key: 'marketplace',
        label: t('marketplace', { defaultValue: 'Marketplace' }),
        icon: ShoppingBag as LucideIcon,
        items: [
          { label: t('marketplace_dashboard', { defaultValue: 'Dashboard' }), href: '/admin/marketplace', icon: ShoppingBag as LucideIcon },
          { label: t('marketplace_moderation', { defaultValue: 'Moderation Queue' }), href: '/admin/marketplace/moderation', icon: ShieldCheck as LucideIcon },
          { label: t('marketplace_sellers', { defaultValue: 'Seller Management' }), href: '/admin/marketplace/sellers', icon: Store as LucideIcon },
        ],
      }] as NavSection[] : []),
      {
        key: 'marketing',
        label: t('marketing'),
        icon: Megaphone,
        items: [
          { label: t('sidebar.newsletters'), href: '/admin/newsletters', icon: Megaphone },
          { label: t('subscribers'), href: '/admin/newsletters/subscribers', icon: Users },
          { label: t('templates'), href: '/admin/newsletters/templates', icon: FileText },
          { label: t('bounces'), href: '/admin/newsletters/bounces', icon: AlertTriangle },
          { label: t('send_time_optimizer'), href: '/admin/newsletters/send-time-optimizer', icon: Clock },
          { label: t('sidebar.diagnostics'), href: '/admin/newsletters/diagnostics', icon: Stethoscope },
          { label: t('sidebar.deliverability'), href: '/admin/deliverability', icon: Mail },
        ],
      },
      {
        key: 'analytics',
        label: t('sidebar.analytics_reporting'),
        icon: BarChart3,
        items: [
          { label: t('community_analytics'), href: '/admin/community-analytics', icon: BarChart3 },
          { label: t('impact_report'), href: '/admin/impact-report', icon: FileText },
          { label: t('social_value'), href: '/admin/reports/social-value', icon: DollarSign },
          { label: t('member_reports'), href: '/admin/reports/members', icon: Users },
          { label: t('hours_reports'), href: '/admin/reports/hours', icon: Clock },
          { label: t('inactive_members'), href: '/admin/reports/inactive-members', icon: UserX },
        ],
      },
      {
        key: 'advanced',
        label: t('sidebar.advanced'),
        icon: Sparkles,
        items: [
          { label: t('ai_settings'), href: '/admin/ai-settings', icon: Brain },
          { label: t('email_settings'), href: '/admin/email-settings', icon: Mail },
          { label: t('algorithm_settings'), href: '/admin/algorithm-settings', icon: Cpu },
          { label: t('seo_overview'), href: '/admin/seo', icon: Search },
          { label: t('error_404_tracking'), href: '/admin/404-errors', icon: AlertTriangle },
          { label: t('sidebar.diagnostics'), href: '/admin/matching-diagnostic', icon: Stethoscope },
          { label: t('match_debug_panel'), href: '/admin/match-debug', icon: Target },
        ],
      },
      {
        key: 'financial',
        label: t('financial'),
        icon: Coins,
        items: [
          { label: t('sidebar.timebanking'), href: '/admin/timebanking', icon: Clock },
          { label: t('fraud_alerts'), href: '/admin/timebanking/alerts', icon: AlertTriangle },
          { label: t('org_wallets'), href: '/admin/timebanking/org-wallets', icon: Wallet },
          { label: t('starting_balances'), href: '/admin/timebanking/starting-balances', icon: Wallet },
          { label: t('plans_pricing'), href: '/admin/plans', icon: CreditCard },
          { label: t('sidebar.billing'), href: '/admin/billing', icon: CreditCard },
        ],
      },
      {
        key: 'enterprise',
        label: t('sidebar.enterprise'),
        icon: Building2,
        items: [
          { label: t('enterprise_dashboard'), href: '/admin/enterprise', icon: Building2 },
          { label: t('roles_permissions'), href: '/admin/enterprise/roles', icon: Key },
          { label: t('gdpr_dashboard'), href: '/admin/enterprise/gdpr', icon: ShieldCheck },
          { label: t('legal_documents'), href: '/admin/legal-documents', icon: FileText },
          { label: t('compliance_dashboard'), href: '/admin/legal-documents/compliance', icon: ShieldCheck },
          { label: t('monitoring'), href: '/admin/enterprise/monitoring', icon: Heart },
          { label: t('system_config'), href: '/admin/enterprise/config', icon: Cog },
          { label: t('feature_flags') || 'Feature Flags', href: '/admin/enterprise/config/features', icon: Settings },
          { label: t('secrets_vault') || 'Secrets Vault', href: '/admin/enterprise/config/secrets', icon: Key },
        ],
      },
      {
        key: 'system',
        label: t('sidebar.system'),
        icon: Settings,
        items: [
          { label: t('settings'), href: '/admin/settings', icon: Settings },
          { label: t('onboarding_settings'), href: '/admin/onboarding-settings', icon: Sparkles },
          { label: t('tenant_features'), href: '/admin/tenant-features', icon: Cog },
          { label: t('sidebar.module_configuration'), href: '/admin/module-configuration', icon: Puzzle, badge: 'BETA' },
          { label: t('translation_config', { defaultValue: 'Translation' }), href: '/admin/translation-config', icon: Languages },
          { label: t('cron_jobs'), href: '/admin/cron-jobs', icon: Timer },
          { label: t('cron_logs'), href: '/admin/cron-jobs/logs', icon: FileText },
          { label: t('cron_settings'), href: '/admin/cron-jobs/settings', icon: Settings },
          { label: t('cron_setup'), href: '/admin/cron-jobs/setup', icon: Wrench },
          { label: t('activity_log'), href: '/admin/activity-log', icon: Activity },
          { label: t('tools'), href: '/admin/seed-generator', icon: Wrench },
        ],
      },
    ];

    // Conditionally add federation
    if (hasFeature('federation')) {
      sections.splice(sections.length - 1, 0, {
        key: 'federation',
        label: t('partner_timebanks'),
        icon: Globe,
        items: [
          { label: t('federation_settings'), href: '/admin/federation', icon: Settings },
          { label: t('federation_partnerships'), href: '/admin/federation/partnerships', icon: ArrowLeftRight },
          { label: t('federation_directory'), href: '/admin/federation/directory', icon: Globe },
          { label: t('federation_credit_agreements'), href: '/admin/federation/credit-agreements', icon: Handshake },
          { label: t('federation_neighborhoods'), href: '/admin/federation/neighborhoods', icon: MapPin },
          { label: t('federation_analytics'), href: '/admin/federation/analytics', icon: BarChart3 },
          { label: t('federation_api_keys'), href: '/admin/federation/api-keys', icon: Key },
          { label: t('federation_api_docs'), href: '/admin/federation/api-docs', icon: BookOpen },
          { label: t('federation_external_partners'), href: '/admin/federation/external-partners', icon: Globe },
          { label: t('federation_webhooks'), href: '/admin/federation/webhooks', icon: Webhook },
          { label: t('federation_activity'), href: '/admin/federation/activity', icon: Activity },
          { label: t('federation_data_management'), href: '/admin/federation/data', icon: Database },
        ],
      });
    }

    // Super Admin section — only visible to super admins
    if (isSuperAdmin) {
      sections.push({
        key: 'super-admin',
        label: t('super_admin'),
        icon: Crown,
        items: [
          { label: t('super_dashboard'), href: '/admin/super', icon: Crown },
          { label: t('super_tenants'), href: '/admin/super/tenants', icon: Building2 },
          { label: t('super_hierarchy'), href: '/admin/super/tenants/hierarchy', icon: Network },
          { label: t('super_cross_tenant_users'), href: '/admin/super/users', icon: Users },
          { label: t('super_bulk_operations'), href: '/admin/super/bulk', icon: ListChecks },
          { label: t('super_audit_log'), href: '/admin/super/audit', icon: ScrollText },
          { label: t('super_federation_controls'), href: '/admin/super/federation', icon: Globe },
          { label: t('super_federation_system_controls'), href: '/admin/super/federation/system-controls', icon: Settings },
          { label: t('super_federation_whitelist'), href: '/admin/super/federation/whitelist', icon: Shield },
          { label: t('super_federation_partnerships'), href: '/admin/super/federation/partnerships', icon: Handshake },
          { label: t('super_federation_audit'), href: '/admin/super/federation/audit', icon: FileSearch },
        ],
      });
    }

    return sections;
  }, [hasFeature, isSuperAdmin]);
}

// ─────────────────────────────────────────────────────────────────────────────
// Sidebar Component
// ─────────────────────────────────────────────────────────────────────────────

interface AdminSidebarProps {
  collapsed: boolean;
  onToggle: () => void;
}

export function AdminSidebar({ collapsed, onToggle }: AdminSidebarProps) {
  const { t } = useTranslation('admin_nav');
  const sections = useAdminNav();
  const location = useLocation();
  const { tenantPath } = useTenant();
  const [expandedSections, setExpandedSections] = useState<Set<string>>(
    () => {
      // Auto-expand the active section
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

  // Dynamic unreviewed message count for sidebar badge
  const [unreviewedCount, setUnreviewedCount] = useState(0);

  const fetchUnreviewedCount = useCallback(async () => {
    try {
      const res = await adminBroker.getUnreviewedCount();
      if (res.success && res.data) {
        setUnreviewedCount(res.data.count);
      }
    } catch {
      // Silently fail — sidebar badge is non-critical
    }
  }, []);

  useEffect(() => {
    fetchUnreviewedCount();
    const interval = setInterval(fetchUnreviewedCount, 60000);
    return () => clearInterval(interval);
  }, [fetchUnreviewedCount]);

  const toggleSection = (key: string) => {
    setExpandedSections((prev) => {
      const next = new Set(prev);
      if (next.has(key)) {
        next.delete(key);
      } else {
        next.add(key);
      }
      return next;
    });
  };

  const isActive = (href: string) => {
    const cleanHref = href.split('?')[0] ?? '';
    const fullPath = tenantPath(cleanHref);
    if (cleanHref === '/admin') {
      return location.pathname === fullPath;
    }
    return location.pathname.startsWith(fullPath);
  };

  return (
    <aside
      className={`fixed left-0 top-0 z-40 h-screen border-r border-divider bg-content1 transition-all duration-300 ${
        collapsed ? 'w-16' : 'w-64'
      }`}
    >
      {/* Header */}
      <div className="flex h-16 items-center justify-between border-b border-divider px-4">
        {!collapsed && (
          <Link to={tenantPath('/admin')} className="text-lg font-bold text-foreground">
            {t('sidebar.admin')}
          </Link>
        )}
        <Button
          variant="light"
          isIconOnly
          onPress={onToggle}
          className="rounded-lg p-2 text-default-500 hover:bg-default-100 hover:text-foreground min-w-0 h-auto"
          aria-label={collapsed ? t('sidebar.expand_sidebar') : t('sidebar.collapse_sidebar')}
        >
          {collapsed ? <PanelLeft size={20} /> : <PanelLeftClose size={20} />}
        </Button>
      </div>

      {/* Navigation */}
      <nav className="h-[calc(100vh-4rem)] overflow-y-auto px-2 py-3">
        <ul className="space-y-1">
          {sections.map((section) => {
            const Icon = section.icon;
            const isExpanded = expandedSections.has(section.key);
            const sectionActive = section.href
              ? isActive(section.href)
              : section.items?.some((item) => isActive(item.href));
            const isSuperSection = section.key === 'super-admin';

            // Single-link section (like Dashboard)
            if (section.href && !section.items) {
              return (
                <li key={section.key}>
                  <Link
                    to={tenantPath(section.href)}
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

            // Collapsible section (with super-admin visual distinction)
            return (
              <li key={section.key}>
                {isSuperSection && !collapsed && (
                  <div className="my-2 border-t border-warning/30" />
                )}
                <div className={isSuperSection ? 'rounded-lg bg-primary/5 py-1 px-1' : ''}>
                  <Button
                    variant="light"
                    onPress={() => toggleSection(section.key)}
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
                      {section.items.map((item) => {
                        const ItemIcon = item.icon;
                        return (
                          <li key={item.href}>
                            <Link
                              to={tenantPath(item.href)}
                              className={`flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm transition-colors ${
                                isActive(item.href)
                                  ? 'bg-primary/10 text-primary font-medium'
                                  : 'text-default-500 hover:bg-default-100 hover:text-foreground'
                              }`}
                            >
                              <ItemIcon size={16} className="shrink-0" />
                              <span>{item.label}</span>
                              {(() => {
                                const isMessageReview = item.href === '/admin/broker-controls/messages';
                                const dynamicBadge = isMessageReview && unreviewedCount > 0
                                  ? String(unreviewedCount)
                                  : item.badge;
                                if (!dynamicBadge) return null;
                                return (
                                  <span className={`ml-auto rounded-full px-1.5 py-0.5 text-[10px] font-bold ${
                                    isMessageReview && unreviewedCount > 0
                                      ? 'bg-danger text-danger-foreground'
                                      : 'bg-primary text-primary-foreground'
                                  }`}>
                                    {dynamicBadge}
                                  </span>
                                );
                              })()}
                            </Link>
                          </li>
                        );
                      })}
                    </ul>
                  )}
                </div>
              </li>
            );
          })}
        </ul>

        {/* Quick link to simplified Broker Panel */}
        <div className="mt-4 border-t border-divider pt-3">
          {collapsed ? (
            <Link
              to={tenantPath('/broker')}
              className="flex items-center justify-center rounded-lg px-2 py-2 text-default-400 hover:bg-default-100 hover:text-foreground transition-colors"
              title={t('sidebar.broker_panel')}
            >
              <ShieldCheck size={18} />
            </Link>
          ) : (
            <Link
              to={tenantPath('/broker')}
              className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-default-400 hover:bg-default-100 hover:text-foreground transition-colors"
            >
              <ShieldCheck size={18} />
              <span>{t('sidebar.broker_panel')}</span>
            </Link>
          )}
        </div>
      </nav>
    </aside>
  );
}

export default AdminSidebar;
