// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * VolOrgDashboardPage — Organization management dashboard for volunteer orgs.
 *
 * Tabs: Overview | Applications | Hours Review | Volunteers | Wallet | Settings
 *
 * API: GET /api/v2/volunteering/organisations/{id}/stats
 *      GET /api/v2/volunteering/organisations/{id} (org details)
 */

import React, { Suspense, useState, useEffect, useCallback, useRef } from 'react';
import { useParams, useSearchParams, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Spinner, Chip } from '@heroui/react';
import LayoutDashboard from 'lucide-react/icons/layout-dashboard';
import ClipboardList from 'lucide-react/icons/clipboard-list';
import Clock from 'lucide-react/icons/clock';
import Users from 'lucide-react/icons/users';
import Wallet from 'lucide-react/icons/wallet';
import Settings from 'lucide-react/icons/settings';
import Building2 from 'lucide-react/icons/building-2';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { LoadingScreen } from '@/components/feedback';
import { useAuth, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks/usePageTitle';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { useTranslation } from 'react-i18next';

// Lazy-loaded tab components
const OrgOverviewTab = React.lazy(() => import('./OrgOverviewTab'));
const OrgApplicationsTab = React.lazy(() => import('./OrgApplicationsTab'));
const OrgHoursReviewTab = React.lazy(() => import('./OrgHoursReviewTab'));
const OrgVolunteersTab = React.lazy(() => import('./OrgVolunteersTab'));
const OrgWalletTab = React.lazy(() => import('./OrgWalletTab'));
const OrgSettingsTab = React.lazy(() => import('./OrgSettingsTab'));

type OrgDashTab = 'overview' | 'applications' | 'hours-review' | 'volunteers' | 'wallet' | 'settings';

interface OrgDetails {
  id: number;
  name: string;
  description: string | null;
  contact_email: string | null;
  website: string | null;
  status: string;
  balance: number;
  auto_pay_enabled: boolean;
}

const TAB_DEFS: { key: OrgDashTab; icon: typeof LayoutDashboard; label: string }[] = [
  { key: 'overview', icon: LayoutDashboard, label: 'Overview' },
  { key: 'applications', icon: ClipboardList, label: 'Applications' },
  { key: 'hours-review', icon: Clock, label: 'Hours Review' },
  { key: 'volunteers', icon: Users, label: 'Volunteers' },
  { key: 'wallet', icon: Wallet, label: 'Wallet' },
  { key: 'settings', icon: Settings, label: 'Settings' },
];

const ORG_DASH_TABS = TAB_DEFS.map((tab) => tab.key);

export default function VolOrgDashboardPage() {
  const { orgId: orgIdParam } = useParams<{ orgId: string }>();
  const orgId = parseInt(orgIdParam || '0', 10);
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();
  const { t } = useTranslation('volunteering');
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();

  const requestedTab = searchParams.get('tab') as OrgDashTab | null;
  const initialTab = requestedTab && ORG_DASH_TABS.includes(requestedTab) ? requestedTab : 'overview';
  const [tab, setTabState] = useState<OrgDashTab>(initialTab);
  const [org, setOrg] = useState<OrgDetails | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [accessDenied, setAccessDenied] = useState(false);
  const abortRef = useRef<AbortController | null>(null);

  usePageTitle(org ? `${org.name} ${t('dashboard')}` : t('org_dashboard.title', 'Organization Dashboard'));

  const setTab = useCallback((newTab: OrgDashTab) => {
    setTabState(newTab);
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev);
      if (newTab === 'overview') {
        next.delete('tab');
      } else {
        next.set('tab', newTab);
      }
      return next;
    }, { replace: true });
  }, [setSearchParams]);

  useEffect(() => {
    if (!ORG_DASH_TABS.includes(tab)) {
      setTab('overview');
    }
  }, [tab, setTab]);

  const loadOrg = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setIsLoading(true);
    try {
      // Load org details AND stats in parallel to get balance/autopay
      const [orgRes, statsRes] = await Promise.all([
        api.get<OrgDetails>(`/v2/volunteering/organisations/${orgId}`),
        api.get<{ wallet_balance: number; auto_pay_enabled: boolean }>(`/v2/volunteering/organisations/${orgId}/stats`),
      ]);

      if (controller.signal.aborted) return;

      if (!orgRes.success || !orgRes.data) {
        setAccessDenied(true);
        return;
      }

      if (!statsRes.success) {
        // Stats endpoint requires ownership — if it fails, access is denied
        setAccessDenied(true);
        return;
      }

      setOrg({
        ...orgRes.data,
        balance: statsRes.data?.wallet_balance ?? orgRes.data.balance ?? 0,
        auto_pay_enabled: statsRes.data?.auto_pay_enabled ?? orgRes.data.auto_pay_enabled ?? false,
      });
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load org', err);
      setAccessDenied(true);
    } finally {
      if (!controller.signal.aborted) setIsLoading(false);
    }
  }, [orgId]);

  useEffect(() => {
    if (!isAuthenticated || !orgId) return;
    loadOrg();
    return () => { abortRef.current?.abort(); };
  }, [isAuthenticated, orgId, loadOrg]);

  if (isLoading) return <LoadingScreen />;

  if (accessDenied || !org) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[60vh] px-6 py-16 text-center">
        <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-red-100 to-orange-100 dark:from-red-900/30 dark:to-orange-900/30 flex items-center justify-center mb-4">
          <Building2 className="w-8 h-8 text-[var(--color-error)]" aria-hidden="true" />
        </div>
        <h2 className="text-xl font-semibold text-theme-primary mb-2">
          {t('org_dashboard.access_denied', 'Access Denied')}
        </h2>
        <p className="text-theme-muted max-w-sm mb-4">
          {t('org_dashboard.access_denied_desc', 'You do not have permission to manage this organization, or it does not exist.')}
        </p>
        <Button
          variant="flat"
          startContent={<ArrowLeft className="w-4 h-4" />}
          onPress={() => navigate(tenantPath('/volunteering'))}
        >
          {t('org_dashboard.back_to_volunteering', 'Back to Volunteering')}
        </Button>
      </div>
    );
  }

  const tabLabels: Record<OrgDashTab, string> = {
    overview: t('org_dashboard.tab_overview', 'Overview'),
    applications: t('org_dashboard.tab_applications', 'Applications'),
    'hours-review': t('org_dashboard.tab_hours_review', 'Hours Review'),
    volunteers: t('org_dashboard.tab_volunteers', 'Volunteers'),
    wallet: t('org_dashboard.tab_wallet', 'Wallet'),
    settings: t('org_dashboard.tab_settings', 'Settings'),
  };

  return (
    <div className="max-w-6xl mx-auto px-4 py-6 space-y-6">
      {/* Breadcrumbs */}
      <Breadcrumbs
        items={[
          { label: t('breadcrumb_volunteering', 'Volunteering'), href: tenantPath('/volunteering') },
          { label: org.name },
        ]}
      />

      {/* Header */}
      <motion.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }}>
        <GlassCard className="p-6">
          <div className="flex flex-col sm:flex-row sm:items-center gap-4">
            <div className="w-14 h-14 rounded-2xl bg-gradient-to-br from-rose-500 to-pink-600 flex items-center justify-center flex-shrink-0">
              <Building2 className="w-7 h-7 text-white" aria-hidden="true" />
            </div>
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2 flex-wrap">
                <h1 className="text-2xl font-bold text-theme-primary">{org.name}</h1>
                <Chip size="sm" color={org.status === 'active' ? 'success' : 'warning'} variant="flat">
                  {org.status}
                </Chip>
              </div>
              {org.description && (
                <p className="text-sm text-theme-muted mt-1 line-clamp-2">{org.description}</p>
              )}
            </div>
            <div className="text-right flex-shrink-0">
              <p className="text-2xl font-bold text-emerald-500">{t('hours_abbrev', { hours: org.balance })}</p>
              <p className="text-xs text-theme-muted">{t('org_dashboard.wallet_balance', 'Wallet Balance')}</p>
            </div>
          </div>
        </GlassCard>
      </motion.div>

      {/* Tab Navigation */}
      <div className="flex flex-wrap gap-2">
        {TAB_DEFS.map((td) => {
          const Icon = td.icon;
          const isActive = tab === td.key;
          return (
            <Button
              key={td.key}
              size="sm"
              variant={isActive ? 'solid' : 'flat'}
              className={isActive
                ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white'
                : 'bg-theme-elevated text-theme-muted'
              }
              startContent={<Icon className="w-4 h-4" />}
              onPress={() => setTab(td.key)}
            >
              {tabLabels[td.key]}
            </Button>
          );
        })}
      </div>

      {/* Tab Content */}
      <Suspense fallback={<div className="flex justify-center py-16"><Spinner size="lg" /></div>}>
        {tab === 'overview' && (
          <OrgOverviewTab orgId={orgId} onTabChange={(t) => setTab(t as OrgDashTab)} />
        )}
        {tab === 'applications' && (
          <OrgApplicationsTab orgId={orgId} />
        )}
        {tab === 'hours-review' && (
          <OrgHoursReviewTab
            orgId={orgId}
            balance={org.balance}
            autoPay={org.auto_pay_enabled}
            onBalanceChange={loadOrg}
          />
        )}
        {tab === 'volunteers' && (
          <OrgVolunteersTab orgId={orgId} />
        )}
        {tab === 'wallet' && (
          <OrgWalletTab
            orgId={orgId}
            balance={org.balance}
            autoPay={org.auto_pay_enabled}
            onBalanceChange={loadOrg}
          />
        )}
        {tab === 'settings' && (
          <OrgSettingsTab
            orgId={orgId}
            orgData={{
              name: org.name,
              description: org.description,
              contact_email: org.contact_email,
              website: org.website,
            }}
            onOrgUpdate={loadOrg}
          />
        )}
      </Suspense>
    </div>
  );
}
