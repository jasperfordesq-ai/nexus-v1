// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Sidebar Navigation
 * Collapsible sidebar matching the PHP admin navigation structure.
 * Uses Lucide icons (consistent with React frontend).
 */

import { useState, useMemo } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth, useTenant } from '@/contexts';
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
  Activity,
  Crown,
  Network,
  ScrollText,
  Mail,
  Wrench,
  Stethoscope,
  MessageSquare,
  MessageCircle,
  Star,
  Flag,
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
        label: 'Dashboard',
        icon: LayoutDashboard,
        href: '/admin',
      },
      {
        key: 'users',
        label: 'Users',
        icon: Users,
        items: [
          { label: 'All Users', href: '/admin/users', icon: Users },
          { label: 'Pending Approvals', href: '/admin/users?filter=pending', icon: UserCheck },
        ],
      },
      {
        key: 'listings',
        label: 'Listings',
        icon: ListChecks,
        items: [
          { label: 'All Content', href: '/admin/listings', icon: ListChecks },
        ],
      },
      {
        key: 'content',
        label: 'Content',
        icon: Newspaper,
        items: [
          { label: 'Blog Posts', href: '/admin/blog', icon: FileText },
          { label: 'Pages', href: '/admin/pages', icon: FileText },
          { label: 'Menus', href: '/admin/menus', icon: Menu },
          { label: 'Categories', href: '/admin/categories', icon: FolderTree },
          { label: 'Attributes', href: '/admin/attributes', icon: Tags },
        ],
      },
      {
        key: 'engagement',
        label: 'Engagement',
        icon: Trophy,
        items: [
          { label: 'Gamification Hub', href: '/admin/gamification', icon: Gamepad2 },
          { label: 'Campaigns', href: '/admin/gamification/campaigns', icon: Target },
          { label: 'Custom Badges', href: '/admin/custom-badges', icon: Medal },
          { label: 'Analytics', href: '/admin/gamification/analytics', icon: BarChart3 },
        ],
      },
      {
        key: 'matching',
        label: 'Matching',
        icon: Zap,
        items: [
          { label: 'Smart Matching', href: '/admin/smart-matching', icon: Brain },
          { label: 'Match Approvals', href: '/admin/match-approvals', icon: UserCheck, badge: 'NEW' },
          { label: 'Broker Controls', href: '/admin/broker-controls', icon: Shield },
          { label: 'Vetting Records', href: '/admin/broker-controls/vetting', icon: ShieldCheck },
        ],
      },
      {
        key: 'moderation',
        label: 'Moderation',
        icon: Shield,
        items: [
          { label: 'Feed Posts', href: '/admin/moderation/feed', icon: MessageSquare },
          { label: 'Comments', href: '/admin/moderation/comments', icon: MessageCircle },
          { label: 'Reviews', href: '/admin/moderation/reviews', icon: Star },
          { label: 'Reports', href: '/admin/moderation/reports', icon: Flag, badge: 'NEW' },
        ],
      },
      {
        key: 'community',
        label: 'Community',
        icon: Users,
        items: [
          { label: 'Groups', href: '/admin/groups', icon: Users },
          { label: 'Group Types', href: '/admin/groups/types', icon: FolderTree },
          { label: 'Group Recommendations', href: '/admin/groups/recommendations', icon: Brain },
          { label: 'Group Ranking', href: '/admin/groups/ranking', icon: Trophy },
          { label: 'Volunteering', href: '/admin/volunteering', icon: Heart },
        ],
      },
      {
        key: 'marketing',
        label: 'Marketing',
        icon: Megaphone,
        items: [
          { label: 'Newsletters', href: '/admin/newsletters', icon: Megaphone },
          { label: 'Subscribers', href: '/admin/newsletters/subscribers', icon: Users },
          { label: 'Templates', href: '/admin/newsletters/templates', icon: FileText },
          { label: 'Bounces', href: '/admin/newsletters/bounces', icon: AlertTriangle },
          { label: 'Send-Time Optimizer', href: '/admin/newsletters/send-time-optimizer', icon: Clock },
          { label: 'Diagnostics', href: '/admin/newsletters/diagnostics', icon: Stethoscope },
          { label: 'Deliverability', href: '/admin/deliverability', icon: Mail },
        ],
      },
      {
        key: 'analytics',
        label: 'Analytics & Reporting',
        icon: BarChart3,
        items: [
          { label: 'Community Analytics', href: '/admin/community-analytics', icon: BarChart3 },
          { label: 'Impact Report', href: '/admin/impact-report', icon: FileText },
        ],
      },
      {
        key: 'advanced',
        label: 'Advanced',
        icon: Sparkles,
        items: [
          { label: 'AI Settings', href: '/admin/ai-settings', icon: Brain },
          { label: 'Feed Algorithm', href: '/admin/feed-algorithm', icon: Sparkles },
          { label: 'SEO Overview', href: '/admin/seo', icon: Search },
          { label: '404 Tracking', href: '/admin/404-errors', icon: AlertTriangle },
          { label: 'Diagnostics', href: '/admin/matching-diagnostic', icon: Stethoscope },
        ],
      },
      {
        key: 'financial',
        label: 'Financial',
        icon: Coins,
        items: [
          { label: 'Timebanking', href: '/admin/timebanking', icon: Clock },
          { label: 'Fraud Alerts', href: '/admin/timebanking/alerts', icon: AlertTriangle },
          { label: 'Org Wallets', href: '/admin/timebanking/org-wallets', icon: Wallet },
          { label: 'Plans & Pricing', href: '/admin/plans', icon: CreditCard },
        ],
      },
      {
        key: 'enterprise',
        label: 'Enterprise',
        icon: Building2,
        items: [
          { label: 'Enterprise Dashboard', href: '/admin/enterprise', icon: Building2 },
          { label: 'Roles & Permissions', href: '/admin/enterprise/roles', icon: Key },
          { label: 'GDPR Dashboard', href: '/admin/enterprise/gdpr', icon: ShieldCheck },
          { label: 'Legal Documents', href: '/admin/legal-documents', icon: FileText },
          { label: 'Compliance Dashboard', href: '/admin/legal-documents/compliance', icon: ShieldCheck },
          { label: 'Monitoring', href: '/admin/enterprise/monitoring', icon: Heart },
          { label: 'System Config', href: '/admin/enterprise/config', icon: Cog },
        ],
      },
      {
        key: 'system',
        label: 'System',
        icon: Settings,
        items: [
          { label: 'Settings', href: '/admin/settings', icon: Settings },
          { label: 'Tenant Features', href: '/admin/tenant-features', icon: Cog },
          { label: 'Cron Jobs', href: '/admin/cron-jobs', icon: Timer },
          { label: 'Cron Logs', href: '/admin/cron-jobs/logs', icon: FileText },
          { label: 'Cron Settings', href: '/admin/cron-jobs/settings', icon: Settings },
          { label: 'Cron Setup', href: '/admin/cron-jobs/setup', icon: Wrench },
          { label: 'Activity Log', href: '/admin/activity-log', icon: Activity },
          { label: 'Tools', href: '/admin/seed-generator', icon: Wrench },
        ],
      },
    ];

    // Conditionally add federation
    if (hasFeature('federation')) {
      sections.splice(sections.length - 1, 0, {
        key: 'federation',
        label: 'Partner Timebanks',
        icon: Globe,
        items: [
          { label: 'Settings', href: '/admin/federation', icon: Settings },
          { label: 'Partnerships', href: '/admin/federation/partnerships', icon: ArrowLeftRight },
          { label: 'Directory', href: '/admin/federation/directory', icon: Globe },
          { label: 'Analytics', href: '/admin/federation/analytics', icon: BarChart3 },
          { label: 'API Keys', href: '/admin/federation/api-keys', icon: Key },
        ],
      });
    }

    // Super Admin section — only visible to super admins
    if (isSuperAdmin) {
      sections.push({
        key: 'super-admin',
        label: 'Super Admin',
        icon: Crown,
        items: [
          { label: 'Dashboard', href: '/admin/super', icon: Crown },
          { label: 'Tenants', href: '/admin/super/tenants', icon: Building2 },
          { label: 'Hierarchy', href: '/admin/super/tenants/hierarchy', icon: Network },
          { label: 'Cross-Tenant Users', href: '/admin/super/users', icon: Users },
          { label: 'Bulk Operations', href: '/admin/super/bulk', icon: ListChecks },
          { label: 'Audit Log', href: '/admin/super/audit', icon: ScrollText },
          { label: 'Federation Controls', href: '/admin/super/federation', icon: Globe },
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
            if (location.pathname.startsWith(tenantPath(item.href.split('?')[0]))) {
              active.add(section.key);
            }
          }
        }
      }
      return active;
    }
  );

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
    const cleanHref = href.split('?')[0];
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
            Admin
          </Link>
        )}
        <button
          onClick={onToggle}
          className="rounded-lg p-2 text-default-500 hover:bg-default-100 hover:text-foreground"
          aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
        >
          {collapsed ? <PanelLeft size={20} /> : <PanelLeftClose size={20} />}
        </button>
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
                  <button
                    onClick={() => toggleSection(section.key)}
                    className={`flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
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
                  </button>
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
                              {item.badge && (
                                <span className="ml-auto rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-primary-foreground">
                                  {item.badge}
                                </span>
                              )}
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
      </nav>
    </aside>
  );
}

export default AdminSidebar;
