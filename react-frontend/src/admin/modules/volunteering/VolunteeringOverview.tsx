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
import { Heart, Users, Clock, Briefcase, RefreshCw } from 'lucide-react';
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

export function VolunteeringOverview() {
  const { t } = useTranslation('admin');
  usePageTitle(t('volunteering.page_title'));
  const toast = useToast();
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
  }, [toast]);

  useEffect(() => { loadData(); }, [loadData]);

  return (
    <div>
      <PageHeader
        title={t('volunteering.volunteering_overview_title')}
        description={t('volunteering.volunteering_overview_desc')}
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>{t('common.refresh')}</Button>}
      />

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

      <Card shadow="sm">
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
    </div>
  );
}

export default VolunteeringOverview;
