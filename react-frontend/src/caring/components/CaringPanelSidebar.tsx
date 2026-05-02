// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Link, useLocation } from 'react-router-dom';
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
  label: string;
  icon: React.ElementType;
  path: string;
}

interface NavSection {
  key: string;
  title: string;
  items: NavItem[];
}

const SECTIONS: NavSection[] = [
  {
    key: 'overview',
    title: 'Overview',
    items: [
      { key: 'dashboard', label: 'Caring Community', icon: LayoutDashboard, path: '/caring' },
      { key: 'workflow', label: 'Caring Workflow', icon: ClipboardCheck, path: '/caring/workflow' },
      { key: 'projects', label: 'Caring Projects', icon: Megaphone, path: '/caring/projects' },
    ],
  },
  {
    key: 'operations',
    title: 'Operations',
    items: [
      { key: 'loyalty', label: 'Loyalty Programme', icon: Coins, path: '/caring/loyalty' },
      { key: 'hour-transfers', label: 'Hour Transfers', icon: ArrowRightLeft, path: '/caring/hour-transfers' },
      { key: 'regional-points', label: 'Regional Points', icon: Coins, path: '/caring/regional-points' },
      { key: 'sub-regions', label: 'Sub-Regions', icon: MapPin, path: '/caring/sub-regions' },
      { key: 'federation-peers', label: 'Federation Peers', icon: Network, path: '/caring/federation-peers' },
      { key: 'sla-dashboard', label: 'SLA Dashboard', icon: Timer, path: '/caring/sla-dashboard' },
      { key: 'providers', label: 'Providers', icon: Users2, path: '/caring/providers' },
      { key: 'warmth-pass', label: 'Warmth Pass', icon: Star, path: '/caring/warmth-pass' },
      { key: 'recipient-circle', label: 'Care Recipient Circle', icon: Heart, path: '/caring/recipient-circle' },
    ],
  },
  {
    key: 'engagement',
    title: 'Engagement',
    items: [
      { key: 'nudges', label: 'Smart Nudges', icon: Bell, path: '/caring/nudges' },
      { key: 'emergency-alerts', label: 'Emergency Alerts', icon: AlertTriangle, path: '/caring/emergency-alerts' },
      { key: 'surveys', label: 'Municipal Surveys', icon: ClipboardList, path: '/caring/surveys' },
      { key: 'copilot', label: 'Communication Copilot', icon: Bot, path: '/caring/copilot' },
      { key: 'civic-digest', label: 'Civic Digest', icon: Newspaper, path: '/caring/civic-digest' },
      { key: 'lead-nurture', label: 'Lead Nurture', icon: Filter, path: '/caring/lead-nurture' },
      { key: 'success-stories', label: 'Success Stories', icon: Star, path: '/caring/success-stories' },
      { key: 'feedback', label: 'Feedback Inbox', icon: MessageSquare, path: '/caring/feedback' },
    ],
  },
  {
    key: 'trust_safety',
    title: 'Trust & Safety',
    items: [
      { key: 'verification', label: 'Municipal Verification', icon: ShieldCheck, path: '/caring/verification' },
      { key: 'safeguarding', label: 'Safeguarding Reports', icon: ShieldAlert, path: '/caring/safeguarding' },
      { key: 'trust-tier', label: 'Trust Tiers', icon: Shield, path: '/caring/trust-tier' },
    ],
  },
  {
    key: 'pilot_governance',
    title: 'Pilot Governance',
    items: [
      { key: 'launch-readiness', label: 'Launch Readiness', icon: Rocket, path: '/caring/launch-readiness' },
      { key: 'pilot-scoreboard', label: 'Pilot Scoreboard', icon: Flag, path: '/caring/pilot-scoreboard' },
      { key: 'data-quality', label: 'Pilot Data Quality', icon: ClipboardCheck, path: '/caring/data-quality' },
      { key: 'operating-policy', label: 'Operating Policy', icon: ScrollText, path: '/caring/operating-policy' },
      { key: 'disclosure-pack', label: 'Disclosure Pack', icon: ShieldCheck, path: '/caring/disclosure-pack' },
      { key: 'commercial-boundary', label: 'Commercial Boundary', icon: Scale, path: '/caring/commercial-boundary' },
      { key: 'isolated-node', label: 'Isolated Node Gate', icon: Server, path: '/caring/isolated-node' },
    ],
  },
  {
    key: 'partnerships',
    title: 'Partnerships',
    items: [
      { key: 'research', label: 'Research Partnerships', icon: FlaskConical, path: '/caring/research' },
      { key: 'external-integrations', label: 'External Integrations', icon: PlugZap, path: '/caring/external-integrations' },
      { key: 'integration-showcase', label: 'Integration Showcase', icon: Layers, path: '/caring/integration-showcase' },
    ],
  },
  {
    key: 'reporting',
    title: 'Reporting',
    items: [
      { key: 'municipal-impact', label: 'Municipal Impact Reports', icon: BarChart3, path: '/caring/municipal-impact' },
      { key: 'kpi-baselines', label: 'KPI Baselines', icon: BarChart3, path: '/caring/kpi-baselines' },
      { key: 'municipal-roi', label: 'Municipal ROI', icon: TrendingUp, path: '/caring/municipal-roi' },
      { key: 'category-coefficients', label: 'Category Coefficients', icon: Sliders, path: '/caring/category-coefficients' },
    ],
  },
];

export function CaringPanelSidebar({ collapsed, onToggle }: CaringPanelSidebarProps) {
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
        {SECTIONS.map((section, idx) => (
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

      {/* Footer — help centre + back to full admin */}
      <div className="border-t border-divider px-2 py-3 space-y-1">
        {collapsed ? (
          <Tooltip content="Help Centre" placement="right">
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
            <span>Help Centre</span>
          </Link>
        )}
        {hasAdminAccess && (
          <>
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
          </>
        )}
      </div>
    </aside>
  );
}

export default CaringPanelSidebar;
