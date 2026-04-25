// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Sidebar Navigation
 * Sectioned sidebar covering daily workflow, compliance, records, and
 * settings. Live badge counts are fetched from the broker dashboard.
 */

import { useState, useEffect, useCallback } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Button, Chip, Tooltip } from '@heroui/react';
import { useAuth, useTenant } from '@/contexts';
import { adminBroker, adminUsers } from '@/admin/api/adminApi';
import LayoutDashboard from 'lucide-react/icons/layout-dashboard';
import Users from 'lucide-react/icons/users';
import UserPlus from 'lucide-react/icons/user-plus';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import ShieldCheck from 'lucide-react/icons/shield-check';
import ArrowLeftRight from 'lucide-react/icons/arrow-left-right';
import MessageSquareWarning from 'lucide-react/icons/message-square-warning';
import Eye from 'lucide-react/icons/eye';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import FileText from 'lucide-react/icons/file-text';
import Archive from 'lucide-react/icons/archive';
import SlidersHorizontal from 'lucide-react/icons/sliders-horizontal';
import HelpCircle from 'lucide-react/icons/circle-help';
import PanelLeftClose from 'lucide-react/icons/panel-left-close';
import PanelLeft from 'lucide-react/icons/panel-left';
import Settings from 'lucide-react/icons/settings';

interface BrokerSidebarProps {
  collapsed: boolean;
  onToggle: () => void;
}

interface NavItem {
  key: string;
  label: string;
  icon: React.ElementType;
  path: string;
  badgeKey?: keyof BadgeCounts;
}

interface NavSection {
  key: string;
  title: string;
  items: NavItem[];
}

interface BadgeCounts {
  pending_members: number;
  safeguarding_alerts: number;
  vetting_expiring: number;
  pending_exchanges: number;
  unreviewed_messages: number;
  monitored_users: number;
  high_risk_listings: number;
}

export function BrokerSidebar({ collapsed, onToggle }: BrokerSidebarProps) {
  const { t } = useTranslation('broker');
  const location = useLocation();
  const { tenantPath, tenant } = useTenant();
  const { user } = useAuth();
  const [badges, setBadges] = useState<BadgeCounts>({
    pending_members: 0,
    safeguarding_alerts: 0,
    vetting_expiring: 0,
    pending_exchanges: 0,
    unreviewed_messages: 0,
    monitored_users: 0,
    high_risk_listings: 0,
  });

  // Check if user also has admin access for the "Full Admin" link
  const role = (user?.role as string) || '';
  const userRecord = user as Record<string, unknown> | null;
  const hasAdminAccess =
    role === 'admin' ||
    role === 'tenant_admin' ||
    role === 'super_admin' ||
    userRecord?.is_admin === true ||
    userRecord?.is_super_admin === true ||
    userRecord?.is_tenant_super_admin === true;

  const sections: NavSection[] = [
    {
      key: 'overview',
      title: t('sidebar.section_overview'),
      items: [
        { key: 'dashboard', label: t('nav.dashboard'), icon: LayoutDashboard, path: '/broker' },
      ],
    },
    {
      key: 'daily',
      title: t('sidebar.section_daily'),
      items: [
        { key: 'members', label: t('nav.members'), icon: Users, path: '/broker/members', badgeKey: 'pending_members' },
        { key: 'onboarding', label: t('nav.onboarding'), icon: UserPlus, path: '/broker/onboarding' },
        { key: 'exchanges', label: t('nav.exchanges'), icon: ArrowLeftRight, path: '/broker/exchanges', badgeKey: 'pending_exchanges' },
        { key: 'messages', label: t('nav.messages'), icon: MessageSquareWarning, path: '/broker/messages', badgeKey: 'unreviewed_messages' },
      ],
    },
    {
      key: 'compliance',
      title: t('sidebar.section_compliance'),
      items: [
        { key: 'safeguarding', label: t('nav.safeguarding'), icon: ShieldAlert, path: '/broker/safeguarding', badgeKey: 'safeguarding_alerts' },
        { key: 'vetting', label: t('nav.vetting'), icon: ShieldCheck, path: '/broker/vetting', badgeKey: 'vetting_expiring' },
        { key: 'monitoring', label: t('nav.monitoring'), icon: Eye, path: '/broker/monitoring', badgeKey: 'monitored_users' },
        { key: 'risk-tags', label: t('nav.risk_tags'), icon: AlertTriangle, path: '/broker/risk-tags', badgeKey: 'high_risk_listings' },
        { key: 'insurance', label: t('nav.insurance'), icon: FileText, path: '/broker/insurance' },
      ],
    },
    {
      key: 'records',
      title: t('sidebar.section_records'),
      items: [
        { key: 'archives', label: t('nav.archives'), icon: Archive, path: '/broker/archives' },
      ],
    },
    {
      key: 'settings',
      title: t('sidebar.section_settings'),
      items: [
        { key: 'configuration', label: t('nav.configuration'), icon: SlidersHorizontal, path: '/broker/configuration' },
        { key: 'help', label: t('nav.help'), icon: HelpCircle, path: '/broker/help' },
      ],
    },
  ];

  // Fetch badge counts from broker dashboard + pending users count
  const fetchBadges = useCallback(async () => {
    try {
      const [dashRes, usersRes] = await Promise.all([
        adminBroker.getDashboard(),
        adminUsers.list({ status: 'pending', limit: 1 }),
      ]);

      let pendingMembers = 0;
      if (usersRes.success && usersRes.data) {
        const payload = usersRes.data as unknown;
        if (Array.isArray(payload)) {
          pendingMembers = payload.length;
        } else if (payload && typeof payload === 'object') {
          const paged = payload as { data: unknown[]; meta?: { total: number } };
          pendingMembers = paged.meta?.total ?? paged.data?.length ?? 0;
        }
      }

      if (dashRes.success && dashRes.data) {
        const d = dashRes.data as unknown as Record<string, unknown>;
        setBadges({
          pending_members: pendingMembers,
          safeguarding_alerts: Number(d.safeguarding_alerts ?? 0),
          vetting_expiring: Number(d.vetting_expiring ?? 0),
          pending_exchanges: Number(d.pending_exchanges ?? 0),
          unreviewed_messages: Number(d.unreviewed_messages ?? 0),
          monitored_users: Number(d.monitored_users ?? 0),
          high_risk_listings: Number(d.high_risk_listings ?? 0),
        });
      }
    } catch {
      // Silently fail — badges are non-critical
    }
  }, []);

  useEffect(() => {
    fetchBadges();
    const interval = setInterval(fetchBadges, 60_000);
    return () => clearInterval(interval);
  }, [fetchBadges]);

  const isActive = (path: string) => {
    const current = location.pathname;
    if (path === '/broker' || path === `${tenant?.slug ? `/${tenant.slug}` : ''}/broker`) {
      return current === tenantPath('/broker') || current === tenantPath('/broker/');
    }
    return current.startsWith(tenantPath(path));
  };

  const renderItem = (item: NavItem) => {
    const active = isActive(item.path);
    const badgeCount = item.badgeKey ? badges[item.badgeKey] : 0;
    const Icon = item.icon;

    const link = (
      <li key={item.key}>
        <Link
          to={tenantPath(item.path)}
          className={`relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${
            active
              ? 'bg-primary/10 text-primary'
              : 'text-default-600 hover:bg-default-100 hover:text-foreground'
          } ${collapsed ? 'justify-center px-2' : ''}`}
        >
          <Icon size={20} className={active ? 'text-primary' : 'text-default-400'} />
          {!collapsed && (
            <>
              <span className="flex-1 truncate">{item.label}</span>
              {badgeCount > 0 && (
                <Chip size="sm" color={active ? 'primary' : 'danger'} variant="flat" className="min-w-[24px] h-5 text-xs">
                  {badgeCount > 99 ? '99+' : badgeCount}
                </Chip>
              )}
            </>
          )}
          {collapsed && badgeCount > 0 && (
            <span className="absolute top-1 right-1 h-2 w-2 rounded-full bg-danger" />
          )}
        </Link>
      </li>
    );

    return collapsed ? (
      <Tooltip key={item.key} content={item.label} placement="right">
        {link}
      </Tooltip>
    ) : (
      link
    );
  };

  return (
    <aside
      className={`fixed left-0 top-0 z-40 h-screen border-r border-divider bg-content1 transition-all duration-300 flex flex-col ${
        collapsed ? 'w-16' : 'w-64'
      }`}
    >
      {/* Header */}
      <div className="flex h-16 items-center justify-between border-b border-divider px-3">
        {!collapsed && (
          <Link to={tenantPath('/broker')} className="flex items-center gap-2">
            <ShieldCheck size={22} className="text-primary" />
            <span className="text-base font-semibold text-foreground">
              {t('sidebar.title')}
            </span>
          </Link>
        )}
        <Button
          isIconOnly
          variant="light"
          size="sm"
          onPress={onToggle}
          className="text-default-500"
          aria-label={collapsed ? t('sidebar.expand') : t('sidebar.collapse')}
        >
          {collapsed ? <PanelLeft size={18} /> : <PanelLeftClose size={18} />}
        </Button>
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto px-2 py-3">
        {sections.map((section, idx) => (
          <div key={section.key} className={idx > 0 ? 'mt-4' : ''}>
            {!collapsed && section.key !== 'overview' && (
              <p className="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-default-400">
                {section.title}
              </p>
            )}
            <ul className="flex flex-col gap-1">
              {section.items.map(renderItem)}
            </ul>
          </div>
        ))}
      </nav>

      {/* Footer — Admin panel link for admins */}
      {hasAdminAccess && (
        <div className="border-t border-divider px-2 py-3">
          {collapsed ? (
            <Tooltip content={t('sidebar.full_admin')} placement="right">
              <Link
                to={tenantPath('/admin')}
                className="flex items-center justify-center rounded-lg px-2 py-2 text-default-400 hover:bg-default-100 hover:text-foreground transition-colors"
              >
                <Settings size={18} />
              </Link>
            </Tooltip>
          ) : (
            <Link
              to={tenantPath('/admin')}
              className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-default-400 hover:bg-default-100 hover:text-foreground transition-colors"
            >
              <Settings size={18} />
              <span>{t('sidebar.full_admin')}</span>
            </Link>
          )}
        </div>
      )}
    </aside>
  );
}

export default BrokerSidebar;
