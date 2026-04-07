// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Volunteering Overview
 * Admin dashboard for volunteering module with stats and recent opportunities.
 */

import { useState, useCallback, useEffect } from 'react';
import { Card, CardBody, CardHeader, Button, Chip } from '@heroui/react';
import {
  Heart, Users, Clock, Briefcase, RefreshCw, AlertTriangle,
  ClipboardCheck, Building2, DollarSign, ChevronRight, Circle,
} from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminVolunteering } from '../../api/adminApi';
import { PageHeader, StatCard } from '../../components';

import { useTranslation } from 'react-i18next';
interface VolStats {
  total_opportunities: number;
  active_opportunities: number;
  total_applications: number;
  pending_applications: number;
  total_hours_logged: number;
  active_volunteers: number;
}

interface Opportunity {
  id: number;
  title: string;
  status: string;
  first_name: string;
  last_name: string;
  created_at: string;
}

interface QuickAction {
  label: string;
  description: string;
  icon: typeof ClipboardCheck;
  path: string;
  color: 'primary' | 'warning' | 'secondary' | 'success';
}

export function VolunteeringOverview() {
  const { t } = useTranslation('admin');
  usePageTitle(t('volunteering.page_title'));
  const toast = useToast();
  const navigate = useNavigate();
  const [stats, setStats] = useState<VolStats | null>(null);
  const [opportunities, setOpportunities] = useState<Opportunity[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminVolunteering.getOverview();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        let d: { stats?: VolStats; recent_opportunities?: Opportunity[] };
        if (payload && typeof payload === 'object' && 'data' in payload) {
          d = (payload as { data: typeof d }).data;
        } else {
          d = payload as typeof d;
        }
        setStats(d.stats || null);
        setOpportunities(d.recent_opportunities || []);
      }
    } catch {
      toast.error(t('volunteering.failed_to_load_volunteering_data'));
      setStats(null);
      setOpportunities([]);
    }
    setLoading(false);
  }, [toast, t]);

  useEffect(() => { loadData(); }, [loadData]);

  const quickActions: QuickAction[] = [
    { label: t('volunteering.review_applications', 'Review Applications'), description: t('volunteering.review_applications_desc', 'Review pending volunteer applications'), icon: ClipboardCheck, path: '/admin/volunteering/approvals', color: 'warning' },
    { label: t('volunteering.verify_hours', 'Verify Hours'), description: t('volunteering.verify_hours_desc', 'Approve or decline logged hours'), icon: Clock, path: '/admin/volunteering/hours', color: 'success' },
    { label: t('volunteering.manage_organizations', 'Manage Organizations'), description: t('volunteering.manage_organizations_desc', 'View and manage volunteer orgs'), icon: Building2, path: '/admin/volunteering/organizations', color: 'secondary' },
    { label: t('volunteering.view_expenses', 'View Expenses'), description: t('volunteering.view_expenses_desc', 'Review expense submissions'), icon: DollarSign, path: '/admin/volunteering/expenses', color: 'primary' },
  ];

  // Build alert banners for urgent items
  const alerts: { message: string; path?: string }[] = [];
  if (stats && stats.pending_applications > 0) {
    alerts.push({ message: t('volunteering.alert_pending_applications', '{{count}} applications pending review', { count: stats.pending_applications }), path: '/admin/volunteering/approvals' });
  }

  return (
    <div>
      <PageHeader
        title={t('volunteering.volunteering_overview_title')}
        description={t('volunteering.volunteering_overview_desc')}
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>{t('common.refresh')}</Button>}
      />

      {/* Alert Banners */}
      {!loading && alerts.length > 0 && (
        <div className="flex flex-col gap-2 mb-6">
          {alerts.map((alert, idx) => (
            <div
              key={idx}
              className="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-center gap-3 cursor-pointer hover:bg-amber-500/15 transition-colors"
              onClick={() => alert.path && navigate(alert.path)}
              role="button"
              tabIndex={0}
              onKeyDown={(e) => { if (e.key === 'Enter' && alert.path) navigate(alert.path); }}
            >
              <AlertTriangle size={18} className="text-amber-500 shrink-0" />
              <span className="text-sm font-medium flex-1">{alert.message}</span>
              {alert.path && <ChevronRight size={16} className="text-amber-500/60" />}
            </div>
          ))}
        </div>
      )}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 mb-6">
        <StatCard label={t('volunteering.label_active_opportunities')} value={stats?.active_opportunities ?? 0} icon={Briefcase} color="primary" loading={loading} />
        <StatCard label={t('volunteering.label_pending_applications')} value={stats?.pending_applications ?? 0} icon={Users} color="warning" loading={loading} />
        <StatCard label={t('volunteering.label_total_hours_logged')} value={stats?.total_hours_logged ?? 0} icon={Clock} color="success" loading={loading} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
        <StatCard label={t('volunteering.label_total_opportunities')} value={stats?.total_opportunities ?? 0} icon={Heart} color="secondary" loading={loading} />
        <StatCard label={t('volunteering.label_total_applications')} value={stats?.total_applications ?? 0} icon={Users} color="primary" loading={loading} />
        <StatCard label={t('volunteering.label_active_volunteers')} value={stats?.active_volunteers ?? 0} icon={Users} color="success" loading={loading} />
      </div>

      {/* Quick Action Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        {quickActions.map((action) => (
          <Card
            key={action.path}
            shadow="sm"
            isPressable
            onPress={() => navigate(action.path)}
            className="hover:scale-[1.02] transition-transform"
          >
            <CardBody className="flex flex-row items-center gap-3 p-4">
              <div className={`p-2 rounded-lg bg-${action.color}/10`}>
                <action.icon size={20} className={`text-${action.color}`} />
              </div>
              <div className="flex-1 min-w-0">
                <p className="font-semibold text-sm">{action.label}</p>
                <p className="text-xs text-default-400 truncate">{action.description}</p>
              </div>
              <ChevronRight size={16} className="text-default-300 shrink-0" />
            </CardBody>
          </Card>
        ))}
      </div>

      {/* Recent Opportunities */}
      <Card shadow="sm" className="mb-6">
        <CardHeader><h3 className="text-lg font-semibold">{t('volunteering.recent_opportunities')}</h3></CardHeader>
        <CardBody>
          {opportunities.length === 0 ? (
            <div className="flex flex-col items-center py-8 text-default-400">
              <Heart size={40} className="mb-2" />
              <p>{t('volunteering.no_opportunities_yet')}</p>
            </div>
          ) : (
            <div className="space-y-3">
              {opportunities.map((opp) => (
                <div key={opp.id} className="flex items-center justify-between rounded-lg border border-default-200 p-3">
                  <div>
                    <p className="font-medium">{opp.title}</p>
                    <p className="text-xs text-default-400">{t('volunteering.by_name', { name: `${opp.first_name} ${opp.last_name}` })}</p>
                  </div>
                  <Chip size="sm" variant="flat" color={['active', 'open'].includes(opp.status) ? 'success' : 'default'} className="capitalize">{opp.status}</Chip>
                </div>
              ))}
            </div>
          )}
        </CardBody>
      </Card>

      {/* Recent Activity Timeline */}
      <Card shadow="sm">
        <CardHeader><h3 className="text-lg font-semibold">{t('volunteering.recent_activity', 'Recent Activity')}</h3></CardHeader>
        <CardBody>
          {opportunities.length === 0 ? (
            <div className="flex flex-col items-center py-8 text-default-400">
              <Clock size={40} className="mb-2" />
              <p>{t('volunteering.no_recent_activity', 'No recent activity')}</p>
            </div>
          ) : (
            <div className="relative pl-6">
              {/* Vertical timeline line */}
              <div className="absolute left-[9px] top-2 bottom-2 w-px bg-default-200" />
              <div className="space-y-4">
                {opportunities.slice(0, 10).map((opp, idx) => {
                  const date = opp.created_at ? new Date(opp.created_at) : null;
                  return (
                    <div key={opp.id} className="relative flex items-start gap-3">
                      {/* Timeline dot */}
                      <div className="absolute -left-6 top-1">
                        <Circle
                          size={12}
                          className={idx === 0 ? 'text-primary fill-primary' : 'text-default-300 fill-default-300'}
                        />
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium">
                          {opp.title}
                        </p>
                        <p className="text-xs text-default-400">
                          {t('volunteering.created_by', 'Created by {{name}}', { name: `${opp.first_name} ${opp.last_name}` })}
                          {date && (
                            <span className="ml-2">
                              {date.toLocaleDateString()} {date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                            </span>
                          )}
                        </p>
                      </div>
                      <Chip size="sm" variant="flat" color={['active', 'open'].includes(opp.status) ? 'success' : 'default'} className="capitalize shrink-0">
                        {opp.status}
                      </Chip>
                    </div>
                  );
                })}
              </div>
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );
}

export default VolunteeringOverview;
