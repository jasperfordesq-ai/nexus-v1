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
import HelpCircle from 'lucide-react/icons/help-circle';

interface CaringPanelSidebarProps {
  collapsed: boolean;
  onToggle: () => void;
}

interface NavItem {
  key: string;
  labelKey: string;
  icon: React.ElementType;
  path: string;
}

interface NavSection {
  key: string;
  titleKey: string;
  items: NavItem[];
}

const SECTIONS: NavSection[] = [
  {
    key: 'overview',
    titleKey: 'panel.sidebar.sections.overview',
    items: [
      { key: 'dashboard', labelKey: 'panel.sidebar.items.dashboard', icon: LayoutDashboard, path: '/caring' },
      { key: 'workflow', labelKey: 'panel.sidebar.items.workflow', icon: ClipboardCheck, path: '/caring/workflow' },
      { key: 'projects', labelKey: 'panel.sidebar.items.projects', icon: Megaphone, path: '/caring/projects' },
    ],
  },
  {
    key: 'operations',
    titleKey: 'panel.sidebar.sections.operations',
    items: [
      { key: 'loyalty', labelKey: 'panel.sidebar.items.loyalty', icon: Coins, path: '/caring/loyalty' },
      { key: 'hour-transfers', labelKey: 'panel.sidebar.items.hour_transfers', icon: ArrowRightLeft, path: '/caring/hour-transfers' },
      { key: 'regional-points', labelKey: 'panel.sidebar.items.regional_points', icon: Coins, path: '/caring/regional-points' },
      { key: 'sub-regions', labelKey: 'panel.sidebar.items.sub_regions', icon: MapPin, path: '/caring/sub-regions' },
      { key: 'federation-peers', labelKey: 'panel.sidebar.items.federation_peers', icon: Network, path: '/caring/federation-peers' },
      { key: 'sla-dashboard', labelKey: 'panel.sidebar.items.sla_dashboard', icon: Timer, path: '/caring/sla-dashboard' },
      { key: 'providers', labelKey: 'panel.sidebar.items.providers', icon: Users2, path: '/caring/providers' },
      { key: 'warmth-pass', labelKey: 'panel.sidebar.items.warmth_pass', icon: Star, path: '/caring/warmth-pass' },
      { key: 'recipient-circle', labelKey: 'panel.sidebar.items.recipient_circle', icon: Heart, path: '/caring/recipient-circle' },
    ],
  },
  {
    key: 'engagement',
    titleKey: 'panel.sidebar.sections.engagement',
    items: [
      { key: 'nudges', labelKey: 'panel.sidebar.items.nudges', icon: Bell, path: '/caring/nudges' },
      { key: 'emergency-alerts', labelKey: 'panel.sidebar.items.emergency_alerts', icon: AlertTriangle, path: '/caring/emergency-alerts' },
      { key: 'surveys', labelKey: 'panel.sidebar.items.surveys', icon: ClipboardList, path: '/caring/surveys' },
      { key: 'copilot', labelKey: 'panel.sidebar.items.copilot', icon: Bot, path: '/caring/copilot' },
      { key: 'civic-digest', labelKey: 'panel.sidebar.items.civic_digest', icon: Newspaper, path: '/caring/civic-digest' },
      { key: 'lead-nurture', labelKey: 'panel.sidebar.items.lead_nurture', icon: Filter, path: '/caring/lead-nurture' },
      { key: 'success-stories', labelKey: 'panel.sidebar.items.success_stories', icon: Star, path: '/caring/success-stories' },
      { key: 'feedback', labelKey: 'panel.sidebar.items.feedback', icon: MessageSquare, path: '/caring/feedback' },
    ],
  },
  {
    key: 'trust_safety',
    titleKey: 'panel.sidebar.sections.trust_safety',
    items: [
      { key: 'verification', labelKey: 'panel.sidebar.items.verification', icon: ShieldCheck, path: '/caring/verification' },
      { key: 'safeguarding', labelKey: 'panel.sidebar.items.safeguarding', icon: ShieldAlert, path: '/caring/safeguarding' },
      { key: 'trust-tier', labelKey: 'panel.sidebar.items.trust_tier', icon: Shield, path: '/caring/trust-tier' },
    ],
  },
  {
    key: 'pilot_governance',
    titleKey: 'panel.sidebar.sections.pilot_governance',
    items: [
      { key: 'launch-readiness', labelKey: 'panel.sidebar.items.launch_readiness', icon: Rocket, path: '/caring/launch-readiness' },
      { key: 'pilot-scoreboard', labelKey: 'panel.sidebar.items.pilot_scoreboard', icon: Flag, path: '/caring/pilot-scoreboard' },
      { key: 'data-quality', labelKey: 'panel.sidebar.items.data_quality', icon: ClipboardCheck, path: '/caring/data-quality' },
      { key: 'operating-policy', labelKey: 'panel.sidebar.items.operating_policy', icon: ScrollText, path: '/caring/operating-policy' },
      { key: 'disclosure-pack', labelKey: 'panel.sidebar.items.disclosure_pack', icon: ShieldCheck, path: '/caring/disclosure-pack' },
      { key: 'commercial-boundary', labelKey: 'panel.sidebar.items.commercial_boundary', icon: Scale, path: '/caring/commercial-boundary' },
      { key: 'isolated-node', labelKey: 'panel.sidebar.items.isolated_node', icon: Server, path: '/caring/isolated-node' },
    ],
  },
  {
    key: 'partnerships',
    titleKey: 'panel.sidebar.sections.partnerships',
    items: [
      { key: 'research', labelKey: 'panel.sidebar.items.research', icon: FlaskConical, path: '/caring/research' },
      { key: 'external-integrations', labelKey: 'panel.sidebar.items.external_integrations', icon: PlugZap, path: '/caring/external-integrations' },
      { key: 'integration-showcase', labelKey: 'panel.sidebar.items.integration_showcase', icon: Layers, path: '/caring/integration-showcase' },
    ],
  },
  {
    key: 'reporting',
    titleKey: 'panel.sidebar.sections.reporting',
    items: [
      { key: 'municipal-impact', labelKey: 'panel.sidebar.items.municipal_impact', icon: BarChart3, path: '/caring/municipal-impact' },
      { key: 'kpi-baselines', labelKey: 'panel.sidebar.items.kpi_baselines', icon: BarChart3, path: '/caring/kpi-baselines' },
      { key: 'municipal-roi', labelKey: 'panel.sidebar.items.municipal_roi', icon: TrendingUp, path: '/caring/municipal-roi' },
      { key: 'category-coefficients', labelKey: 'panel.sidebar.items.category_coefficients', icon: Sliders, path: '/caring/category-coefficients' },
    ],
  },
];

export function CaringPanelSidebar({ collapsed, onToggle }: CaringPanelSidebarProps) {
  const location = useLocation();
  const { t } = useTranslation('caring_community');
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
    const label = t(item.labelKey);

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
          {!collapsed && <span className="flex-1 truncate">{label}</span>}
        </Link>
      </li>
    );

    return collapsed ? (
      <Tooltip key={item.key} content={label} placement="right">
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
              {t('panel.sidebar.brand')}
            </span>
          </Link>
        )}
        <Button
          isIconOnly
          variant="light"
          size="sm"
          onPress={onToggle}
          className="text-default-500"
          aria-label={t(collapsed ? 'panel.sidebar.expand' : 'panel.sidebar.collapse')}
        >
          {collapsed ? <PanelLeft size={18} /> : <PanelLeftClose size={18} />}
        </Button>
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto px-2 py-3">
        {SECTIONS.map((section, idx) => (
          <div key={section.key} className={idx > 0 ? 'mt-4' : ''}>
            {!collapsed && (
              <p className="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-default-400">
                {t(section.titleKey)}
              </p>
            )}
            <ul className="flex flex-col gap-0.5">
              {section.items.map(renderItem)}
            </ul>
          </div>
        ))}
      </nav>

      {/* Footer — help centre + back to full admin */}
      <div className="border-t border-divider px-2 py-3 space-y-1">
        {collapsed ? (
          <Tooltip content={t('panel.sidebar.help_centre')} placement="right">
            <Link
              to={tenantPath('/admin/help')}
              className={`flex items-center justify-center rounded-lg px-2 py-2 transition-colors hover:bg-default-100 ${
                location.pathname.includes('/admin/help') ? 'text-primary' : 'text-default-400 hover:text-foreground'
              }`}
            >
              <HelpCircle size={18} />
            </Link>
          </Tooltip>
        ) : (
          <Link
            to={tenantPath('/admin/help')}
            className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors hover:bg-default-100 ${
              location.pathname.includes('/admin/help') ? 'text-primary font-medium' : 'text-default-400 hover:text-foreground'
            }`}
          >
            <HelpCircle size={18} />
            <span>{t('panel.sidebar.help_centre')}</span>
          </Link>
        )}
        {hasAdminAccess && (
          <>
            {collapsed ? (
              <Tooltip content={t('panel.sidebar.full_admin')} placement="right">
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
                <span>{t('panel.sidebar.full_admin')}</span>
              </Link>
            )}
          </>
        )}
      </div>
    </aside>
  );
}

export default CaringPanelSidebar;
