// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Analytics
 * Overview of federation activity metrics: partnerships, transactions, messages.
 */

import { useState, useCallback, useEffect } from 'react';
import { Card, CardBody, Button } from '@heroui/react';
import { BarChart3, Handshake, ArrowRightLeft, MessageSquare, Clock, RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { adminFederation } from '../../api/adminApi';
import { PageHeader, StatCard } from '../../components';

interface FedAnalytics {
  total_partnerships: number;
  active_partnerships: number;
  pending_requests: number;
  cross_community_transactions: number;
  cross_community_messages: number;
}

export function FederationAnalytics() {
  usePageTitle('Admin - Federation Analytics');
  const [data, setData] = useState<FedAnalytics | null>(null);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminFederation.getAnalytics();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (payload && typeof payload === 'object' && 'data' in payload) {
          setData((payload as { data: FedAnalytics }).data);
        } else {
          setData(payload as FedAnalytics);
        }
      }
    } catch {
      setData(null);
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  return (
    <div>
      <PageHeader
        title="Federation Analytics"
        description="Cross-community activity and partnership metrics"
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>Refresh</Button>}
      />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5 mb-6">
        <StatCard label="Total Partnerships" value={data?.total_partnerships ?? 0} icon={Handshake} color="primary" loading={loading} />
        <StatCard label="Active" value={data?.active_partnerships ?? 0} icon={Handshake} color="success" loading={loading} />
        <StatCard label="Pending Requests" value={data?.pending_requests ?? 0} icon={Clock} color="warning" loading={loading} />
        <StatCard label="Cross-Transactions" value={data?.cross_community_transactions ?? 0} icon={ArrowRightLeft} color="secondary" loading={loading} />
        <StatCard label="Cross-Messages" value={data?.cross_community_messages ?? 0} icon={MessageSquare} color="primary" loading={loading} />
      </div>

      <Card shadow="sm">
        <CardBody className="flex flex-col items-center justify-center py-12 text-center">
          <BarChart3 size={48} className="text-default-300 mb-3" />
          <p className="text-default-500">Detailed federation analytics and charts will appear here as partnership activity grows.</p>
        </CardBody>
      </Card>
    </div>
  );
}

export default FederationAnalytics;
