/**
 * Risk Tags
 * View and filter listing risk tags.
 * Parity: PHP BrokerControlsController::riskTags()
 */

import { useState, useCallback, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Tabs, Tab, Button, Chip } from '@heroui/react';
import { ArrowLeft, ShieldCheck, ShieldAlert } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';
import { adminBroker } from '../../api/adminApi';
import { DataTable, PageHeader, type Column } from '../../components';
import type { RiskTag } from '../../api/types';

const riskColorMap: Record<string, 'success' | 'warning' | 'danger' | 'default'> = {
  low: 'success',
  medium: 'warning',
  high: 'danger',
  critical: 'danger',
};

export function RiskTagsPage() {
  usePageTitle('Admin - Risk Tags');
  const { tenantPath } = useTenant();

  const [items, setItems] = useState<RiskTag[]>([]);
  const [loading, setLoading] = useState(true);
  const [riskLevel, setRiskLevel] = useState('all');

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminBroker.getRiskTags({
        risk_level: riskLevel === 'all' ? undefined : riskLevel,
      });
      if (res.success && res.data) {
        const data = res.data as unknown;
        if (Array.isArray(data)) {
          setItems(data);
        } else if (data && typeof data === 'object' && 'data' in (data as Record<string, unknown>)) {
          setItems((data as { data: RiskTag[] }).data || []);
        }
      }
    } catch {
      // Silently handle
    } finally {
      setLoading(false);
    }
  }, [riskLevel]);

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const columns: Column<RiskTag>[] = [
    {
      key: 'listing_title',
      label: 'Listing',
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">
          {item.listing_title || '—'}
        </span>
      ),
    },
    {
      key: 'owner_name',
      label: 'Owner',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">
          {item.owner_name || '—'}
        </span>
      ),
    },
    {
      key: 'risk_level',
      label: 'Risk Level',
      sortable: true,
      render: (item) => (
        <Chip
          size="sm"
          variant="flat"
          color={riskColorMap[item.risk_level] || 'default'}
          startContent={item.risk_level === 'critical' || item.risk_level === 'high'
            ? <ShieldAlert size={12} />
            : <ShieldCheck size={12} />
          }
          className="capitalize"
        >
          {item.risk_level}
        </Chip>
      ),
    },
    {
      key: 'risk_category',
      label: 'Category',
      sortable: true,
      render: (item) => (
        <span className="text-sm capitalize">{item.risk_category || '—'}</span>
      ),
    },
    {
      key: 'requires_approval',
      label: 'Approval Req.',
      render: (item) => (
        <Chip size="sm" variant="dot" color={item.requires_approval ? 'warning' : 'default'}>
          {item.requires_approval ? 'Yes' : 'No'}
        </Chip>
      ),
    },
    {
      key: 'insurance_required',
      label: 'Insurance',
      render: (item) => (
        <Chip size="sm" variant="dot" color={item.insurance_required ? 'warning' : 'default'}>
          {item.insurance_required ? 'Yes' : 'No'}
        </Chip>
      ),
    },
    {
      key: 'dbs_required',
      label: 'DBS',
      render: (item) => (
        <Chip size="sm" variant="dot" color={item.dbs_required ? 'warning' : 'default'}>
          {item.dbs_required ? 'Yes' : 'No'}
        </Chip>
      ),
    },
    {
      key: 'created_at',
      label: 'Date',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {new Date(item.created_at).toLocaleDateString()}
        </span>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Risk Tags"
        description="Listings flagged with risk assessments"
        actions={
          <Button
            as={Link}
            to={tenantPath('/admin/broker-controls')}
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            size="sm"
          >
            Back
          </Button>
        }
      />

      <div className="mb-4">
        <Tabs
          selectedKey={riskLevel}
          onSelectionChange={(key) => setRiskLevel(key as string)}
          variant="underlined"
          size="sm"
        >
          <Tab key="all" title="All" />
          <Tab key="critical" title="Critical" />
          <Tab key="high" title="High" />
          <Tab key="medium" title="Medium" />
          <Tab key="low" title="Low" />
        </Tabs>
      </div>

      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchable={false}
        onRefresh={loadItems}
      />
    </div>
  );
}

export default RiskTagsPage;
