// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button, Chip, Spinner } from '@heroui/react';
import {
  Users,
  ClipboardList,
  Clock,
  Wallet,
  Briefcase,
  ArrowRight,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { useTranslation } from 'react-i18next';

interface OrgStats {
  total_volunteers: number;
  pending_applications: number;
  pending_hours: number;
  total_approved_hours: number;
  active_opportunities: number;
  wallet_balance: number;
  auto_pay_enabled: boolean;
  org_name: string;
}

interface OrgOverviewTabProps {
  orgId: number;
  onTabChange: (tab: string) => void;
}

export default function OrgOverviewTab({ orgId, onTabChange }: OrgOverviewTabProps) {
  const { t } = useTranslation('volunteering');
  const { tenantPath } = useTenant();
  const navigate = useNavigate();
  const [stats, setStats] = useState<OrgStats | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const abortRef = useRef<AbortController | null>(null);

  useEffect(() => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setIsLoading(true);
    api.get<OrgStats>(`/v2/volunteering/organisations/${orgId}/stats`)
      .then((res) => {
        if (controller.signal.aborted) return;
        if (res.success && res.data) setStats(res.data);
      })
      .catch((err) => {
        if (controller.signal.aborted) return;
        logError('Failed to load org stats', err);
      })
      .finally(() => {
        if (!controller.signal.aborted) setIsLoading(false);
      });

    return () => { abortRef.current?.abort(); };
  }, [orgId]);

  if (isLoading) {
    return (
      <div className="flex justify-center py-16">
        <Spinner size="lg" />
      </div>
    );
  }

  if (!stats) {
    return (
      <GlassCard className="p-8 text-center">
        <p className="text-theme-muted">{t('org_dashboard.stats_unavailable', 'Unable to load organization stats.')}</p>
      </GlassCard>
    );
  }

  const statCards = [
    {
      label: t('org_dashboard.total_volunteers', 'Volunteers'),
      value: stats.total_volunteers,
      icon: Users,
      color: 'from-blue-500 to-cyan-500',
      tab: 'volunteers',
    },
    {
      label: t('org_dashboard.pending_applications', 'Pending Applications'),
      value: stats.pending_applications,
      icon: ClipboardList,
      color: 'from-amber-500 to-orange-500',
      tab: 'applications',
      badge: stats.pending_applications > 0,
    },
    {
      label: t('org_dashboard.pending_hours', 'Hours Pending Review'),
      value: stats.pending_hours,
      icon: Clock,
      color: 'from-violet-500 to-purple-500',
      tab: 'hours-review',
      badge: stats.pending_hours > 0,
    },
    {
      label: t('org_dashboard.wallet_balance', 'Wallet Balance'),
      value: `${stats.wallet_balance}h`,
      icon: Wallet,
      color: 'from-emerald-500 to-teal-500',
      tab: 'wallet',
    },
    {
      label: t('org_dashboard.total_approved_hours', 'Total Approved Hours'),
      value: stats.total_approved_hours,
      icon: Clock,
      color: 'from-rose-500 to-pink-500',
    },
    {
      label: t('org_dashboard.active_opportunities', 'Active Opportunities'),
      value: stats.active_opportunities,
      icon: Briefcase,
      color: 'from-indigo-500 to-blue-500',
    },
  ];

  return (
    <div className="space-y-6">
      {/* Stats Grid */}
      <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
        {statCards.map((card) => {
          const Icon = card.icon;
          return (
            <GlassCard
              key={card.label}
              hoverable
              className="p-4 cursor-pointer"
              onClick={() => card.tab && onTabChange(card.tab)}
            >
              <div className="flex items-start justify-between">
                <div className={`w-10 h-10 rounded-xl bg-gradient-to-br ${card.color} flex items-center justify-center`}>
                  <Icon className="w-5 h-5 text-white" aria-hidden="true" />
                </div>
                {card.badge && (
                  <Chip size="sm" color="warning" variant="flat">
                    {t('org_dashboard.needs_review', 'Needs Review')}
                  </Chip>
                )}
              </div>
              <p className="mt-3 text-2xl font-bold text-theme-primary">{card.value}</p>
              <p className="text-sm text-theme-muted">{card.label}</p>
            </GlassCard>
          );
        })}
      </div>

      {/* Quick Actions */}
      <GlassCard className="p-6">
        <h3 className="text-lg font-semibold text-theme-primary mb-4">
          {t('org_dashboard.quick_actions', 'Quick Actions')}
        </h3>
        <div className="flex flex-wrap gap-3">
          {stats.pending_applications > 0 && (
            <Button
              color="warning"
              variant="flat"
              startContent={<ClipboardList className="w-4 h-4" />}
              endContent={<ArrowRight className="w-4 h-4" />}
              onPress={() => onTabChange('applications')}
            >
              {t('org_dashboard.review_applications', 'Review {{count}} Applications', { count: stats.pending_applications })}
            </Button>
          )}
          {stats.pending_hours > 0 && (
            <Button
              color="secondary"
              variant="flat"
              startContent={<Clock className="w-4 h-4" />}
              endContent={<ArrowRight className="w-4 h-4" />}
              onPress={() => onTabChange('hours-review')}
            >
              {t('org_dashboard.review_hours', 'Review {{count}} Hours', { count: stats.pending_hours })}
            </Button>
          )}
          <Button
            variant="flat"
            className="bg-gradient-to-r from-emerald-500 to-teal-600 text-white"
            startContent={<Wallet className="w-4 h-4" />}
            onPress={() => onTabChange('wallet')}
          >
            {t('org_dashboard.fund_wallet', 'Fund Wallet')}
          </Button>
          <Button
            variant="flat"
            className="bg-theme-elevated text-theme-muted"
            startContent={<Briefcase className="w-4 h-4" />}
            onPress={() => navigate(tenantPath('/volunteering/create'))}
          >
            {t('org_dashboard.post_opportunity', 'Post Opportunity')}
          </Button>
        </div>
      </GlassCard>

      {/* Auto-pay Status */}
      <GlassCard className={`p-4 ${stats.auto_pay_enabled ? 'border-emerald-500/30' : 'border-amber-500/30'}`}>
        <div className="flex items-center gap-3">
          <Wallet className={`w-5 h-5 ${stats.auto_pay_enabled ? 'text-emerald-400' : 'text-amber-400'}`} />
          <div>
            <p className="text-sm font-medium text-theme-primary">
              {stats.auto_pay_enabled
                ? t('org_dashboard.autopay_on', 'Auto-pay is enabled')
                : t('org_dashboard.autopay_off', 'Auto-pay is disabled')}
            </p>
            <p className="text-xs text-theme-muted">
              {stats.auto_pay_enabled
                ? t('org_dashboard.autopay_on_desc', 'Volunteers are automatically paid time credits when their hours are approved.')
                : t('org_dashboard.autopay_off_desc', 'Hours are approved but volunteers are not automatically paid. Enable auto-pay in the Wallet tab.')}
            </p>
          </div>
          <Button
            size="sm"
            variant="flat"
            className="ml-auto"
            onPress={() => onTabChange('wallet')}
          >
            {t('org_dashboard.configure', 'Configure')}
          </Button>
        </div>
      </GlassCard>
    </div>
  );
}
