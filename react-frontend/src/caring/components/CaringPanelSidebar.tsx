// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Link, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Button, Tooltip } from '@heroui/react';
import { useAuth, useTenant } from '@/contexts';
import Heart from 'lucide-react/icons/heart';
import LayoutDashboard from 'lucide-react/icons/layout-dashboard';
import ClipboardCheck from 'lucide-react/icons/clipboard-check';
import Megaphone from 'lucide-react/icons/megaphone';
import Coins from 'lucide-react/icons/coins';
import ArrowRightLeft from 'lucide-react/icons/arrow-right-left';
import MapPin from 'lucide-react/icons/map-pin';
import Network from 'lucide-react/icons/network';
import Timer from 'lucide-react/icons/timer';
import Users2 from 'lucide-react/icons/users-2';
import Star from 'lucide-react/icons/star';
import Bell from 'lucide-react/icons/bell';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import ClipboardList from 'lucide-react/icons/clipboard-list';
import Bot from 'lucide-react/icons/bot';
import Newspaper from 'lucide-react/icons/newspaper';
import Filter from 'lucide-react/icons/filter';
import MessageSquare from 'lucide-react/icons/message-square';
import ShieldCheck from 'lucide-react/icons/shield-check';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import Shield from 'lucide-react/icons/shield';
import Rocket from 'lucide-react/icons/rocket';
import Flag from 'lucide-react/icons/flag';
import ScrollText from 'lucide-react/icons/scroll-text';
import Scale from 'lucide-react/icons/scale';
import Server from 'lucide-react/icons/server';
import FlaskConical from 'lucide-react/icons/flask-conical';
import PlugZap from 'lucide-react/icons/plug-zap';
import Layers from 'lucide-react/icons/layers';
import BarChart3 from 'lucide-react/icons/bar-chart-3';
import TrendingUp from 'lucide-react/icons/trending-up';
import Sliders from 'lucide-react/icons/sliders-horizontal';
import PanelLeftClose from 'lucide-react/icons/panel-left-close';
import PanelLeft from 'lucide-react/icons/panel-left';
import Settings from 'lucide-react/icons/settings';

interface CaringPanelSidebarProps {
  collapsed: boolean;
  onToggle: () => void;
}

interface NavItem {
  key: string;
  label: string;
  icon: React.ElementType;
  path: string;
}

interface NavSection {
  key: string;
  title: string;
  items: NavItem[];
}

export function CaringPanelSidebar({ collapsed, onToggle }: CaringPanelSidebarProps) {
  const { t } = useTranslation('admin');
  const location = useLocation();
  const { tenantPath, tenant } = useTenant();
  const { user } = useAuth();

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
      title: t('caring_group_overview'),
      items: [
        { key: 'dashboard', label: t('caring_community'), icon: LayoutDashboard, path: '/caring' },
        { key: 'workflow', label: t('caring_workflow'), icon: ClipboardCheck, path: '/caring/workflow' },
        { key: 'projects', label: t('caring_projects'), icon: Megaphone, path: '/caring/projects' },
      ],
    },
    {
      key: 'operations',
      title: t('caring_group_operations'),
      items: [
        { key: 'loyalty', label: t('caring_loyalty_programme'), icon: Coins, path: '/caring/loyalty' },
        { key: 'hour-transfers', label: t('caring_hour_transfers'), icon: ArrowRightLeft, path: '/caring/hour-transfers' },
        { key: 'regional-points', label: t('caring_regional_points'), icon: Coins, path: '/caring/regional-points' },
        { key: 'sub-regions', label: t('caring_sub_regions'), icon: MapPin, path: '/caring/sub-regions' },
        { key: 'federation-peers', label: t('caring_federation_peers'), icon: Network, path: '/caring/federation-peers' },
        { key: 'sla-dashboard', label: t('caring_sla_dashboard'), icon: Timer, path: '/caring/sla-dashboard' },
        { key: 'providers', label: t('caring_providers'), icon: Users2, path: '/caring/providers' },
        { key: 'warmth-pass', label: t('warmth_pass'), icon: Star, path: '/caring/warmth-pass' },
        { key: 'recipient-circle', label: t('care_recipient_circle'), icon: Heart, path: '/caring/recipient-circle' },
      ],
    },
    {
      key: 'engagement',
      title: t('caring_group_engagement'),
      items: [
        { key: 'nudges', label: t('caring_smart_nudges'), icon: Bell, path: '/caring/nudges' },
        { key: 'emergency-alerts', label: t('emergency_alerts'), icon: AlertTriangle, path: '/caring/emergency-alerts' },
        { key: 'surveys', label: t('municipal_surveys'), icon: ClipboardList, path: '/caring/surveys' },
        { key: 'copilot', label: t('caring_communication_copilot'), icon: Bot, path: '/caring/copilot' },
        { key: 'civic-digest', label: t('caring_civic_digest'), icon: Newspaper, path: '/caring/civic-digest' },
        { key: 'lead-nurture', label: t('caring_lead_nurture'), icon: Filter, path: '/caring/lead-nurture' },
        { key: 'success-stories', label: t('caring_success_stories'), icon: Star, path: '/caring/success-stories' },
        { key: 'feedback', label: t('caring_feedback_inbox'), icon: MessageSquare, path: '/caring/feedback' },
      ],
    },
    {
      key: 'trust_safety',
      title: t('caring_group_trust_safety'),
      items: [
        { key: 'verification', label: t('caring_municipal_verification'), icon: ShieldCheck, path: '/caring/verification' },
        { key: 'safeguarding', label: t('caring_safeguarding_reports'), icon: ShieldAlert, path: '/caring/safeguarding' },
        { key: 'trust-tier', label: t('trust_tiers'), icon: Shield, path: '/caring/trust-tier' },
      ],
    },
    {
      key: 'pilot_governance',
      title: t('caring_group_pilot_governance'),
      items: [
        { key: 'launch-readiness', label: t('caring_launch_readiness'), icon: Rocket, path: '/caring/launch-readiness' },
        { key: 'pilot-scoreboard', label: t('caring_pilot_scoreboard'), icon: Flag, path: '/caring/pilot-scoreboard' },
        { key: 'data-quality', label: t('caring_pilot_data_quality'), icon: ClipboardCheck, path: '/caring/data-quality' },
        { key: 'operating-policy', label: t('caring_operating_policy'), icon: ScrollText, path: '/caring/operating-policy' },
        { key: 'disclosure-pack', label: t('caring_disclosure_pack'), icon: ShieldCheck, path: '/caring/disclosure-pack' },
        { key: 'commercial-boundary', label: t('caring_commercial_boundary'), icon: Scale, path: '/caring/commercial-boundary' },
        { key: 'isolated-node', label: t('caring_isolated_node_gate'), icon: Server, path: '/caring/isolated-node' },
      ],
    },
    {
      key: 'partnerships',
      title: t('caring_group_partnerships'),
      items: [
        { key: 'research', label: t('research_partnerships'), icon: FlaskConical, path: '/caring/research' },
        { key: 'external-integrations', label: t('caring_external_integrations'), icon: PlugZap, path: '/caring/external-integrations' },
        { key: 'integration-showcase', label: t('caring_integration_showcase'), icon: Layers, path: '/caring/integration-showcase' },
      ],
    },
    {
      key: 'reporting',
      title: t('caring_group_reporting'),
      items: [
        { key: 'municipal-impact', label: t('municipal_impact_reports'), icon: BarChart3, path: '/caring/municipal-impact' },
        { key: 'kpi-baselines', label: t('kpi_baselines'), icon: BarChart3, path: '/caring/kpi-baselines' },
        { key: 'municipal-roi', label: t('municipal_roi'), icon: TrendingUp, path: '/caring/municipal-roi' },
        { key: 'category-coefficients', label: t('caring_category_coefficients'), icon: Sliders, path: '/caring/category-coefficients' },
      ],
    },
  ];

  const isActive = (path: string) => {
    const current = location.pathname;
    const base = tenant?.slug ? `/${tenant.slug}` : '';
    if (path === '/caring') {
      return current === `${base}/caring` || current === `${base}/caring/`;
    }
    return current.startsWith(tenantPath(path));
  };

  const renderItem = (item: NavItem) => {
    const active = isActive(item.path);
    const Icon = item.icon;

    const link = (
      <li key={item.key}>
        <Link
          to={tenantPath(item.path)}
          className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
            active
              ? 'bg-primary/10 text-primary'
              : 'text-default-600 hover:bg-default-100 hover:text-foreground'
          } ${collapsed ? 'justify-center px-2' : ''}`}
        >
          <Icon size={18} className={`shrink-0 ${active ? 'text-primary' : 'text-default-400'}`} />
          {!collapsed && <span className="flex-1 truncate">{item.label}</span>}
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
          <Link to={tenantPath('/caring')} className="flex items-center gap-2">
            <Heart size={20} className="text-primary shrink-0" />
            <span className="text-sm font-semibold text-foreground leading-tight">
              Community Caring
            </span>
          </Link>
        )}
        <Button
          isIconOnly
          variant="light"
          size="sm"
          onPress={onToggle}
          className="text-default-500"
          aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
        >
          {collapsed ? <PanelLeft size={18} /> : <PanelLeftClose size={18} />}
        </Button>
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto px-2 py-3">
        {sections.map((section, idx) => (
          <div key={section.key} className={idx > 0 ? 'mt-4' : ''}>
            {!collapsed && (
              <p className="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-default-400">
                {section.title}
              </p>
            )}
            <ul className="flex flex-col gap-0.5">
              {section.items.map(renderItem)}
            </ul>
          </div>
        ))}
      </nav>

      {/* Footer — back to full admin panel */}
      {hasAdminAccess && (
        <div className="border-t border-divider px-2 py-3">
          {collapsed ? (
            <Tooltip content="Full Admin" placement="right">
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
              <span>Full Admin</span>
            </Link>
          )}
        </div>
      )}
    </aside>
  );
}

export default CaringPanelSidebar;
