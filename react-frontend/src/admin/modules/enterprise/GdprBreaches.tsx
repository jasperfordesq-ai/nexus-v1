// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GDPR Breaches
 * DataTable of data breaches with report functionality.
 * Parity: PHP GdprBreachController::index() + create/store
 */

import { useEffect, useState, useCallback } from 'react';
import {
  Button, Chip, Modal, ModalContent, ModalHeader, ModalBody, ModalFooter,
  Input, Textarea, Select, SelectItem,
} from '@heroui/react';
import { RefreshCw, Plus, AlertTriangle } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader, DataTable, StatusBadge } from '../../components';
import type { Column } from '../../components';
import type { GdprBreach } from '../../api/types';

import { useTranslation } from 'react-i18next';
const severityColorMap: Record<string, 'default' | 'primary' | 'warning' | 'danger'> = {
  low: 'default',
  medium: 'primary',
  high: 'warning',
  critical: 'danger',
};

const SEVERITY_OPTIONS = [
  { value: 'low', label: 'Low' },
  { value: 'medium', label: 'Medium' },
  { value: 'high', label: 'High' },
  { value: 'critical', label: 'Critical' },
];

export function GdprBreaches() {
  const { t } = useTranslation('admin');
  usePageTitle(t('enterprise.page_title'));
  const toast = useToast();

  const [breaches, setBreaches] = useState<GdprBreach[]>([]);
  const [loading, setLoading] = useState(true);

  // Report breach modal
  const [reportOpen, setReportOpen] = useState(false);
  const [reportLoading, setReportLoading] = useState(false);
  const [breachTitle, setBreachTitle] = useState('');
  const [breachDescription, setBreachDescription] = useState('');
  const [breachSeverity, setBreachSeverity] = useState('medium');
  const [affectedUsers, setAffectedUsers] = useState('');

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getGdprBreaches();
      if (res.success && res.data) {
        const data = res.data as unknown;
        setBreaches(Array.isArray(data) ? data : []);
      }
    } catch {
      toast.error(t('enterprise.failed_to_load_breaches'));
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const openReportModal = () => {
    setBreachTitle('');
    setBreachDescription('');
    setBreachSeverity('medium');
    setAffectedUsers('');
    setReportOpen(true);
  };

  const handleReportBreach = async () => {
    if (!breachTitle.trim()) {
      toast.error(t('enterprise.title_is_required'));
      return;
    }
    setReportLoading(true);
    try {
      const res = await adminEnterprise.createBreach({
        title: breachTitle.trim(),
        description: breachDescription.trim(),
        severity: breachSeverity,
        affected_users: affectedUsers ? parseInt(affectedUsers, 10) : 0,
      });
      if (res.success) {
        toast.success(t('enterprise.breach_reported_successfully'));
        setReportOpen(false);
        loadData();
      } else {
        toast.error(t('enterprise.failed_to_report_breach'));
      }
    } catch {
      toast.error(t('enterprise.failed_to_report_breach'));
    } finally {
      setReportLoading(false);
    }
  };

  const columns: Column<GdprBreach>[] = [
    { key: 'id', label: 'ID', sortable: true },
    { key: 'title', label: 'Title', sortable: true },
    {
      key: 'severity',
      label: 'Severity',
      sortable: true,
      render: (b) => (
        <Chip size="sm" variant="flat" color={severityColorMap[b.severity] || 'default'} className="capitalize">
          {b.severity}
        </Chip>
      ),
    },
    {
      key: 'status',
      label: 'Status',
      sortable: true,
      render: (b) => <StatusBadge status={b.status} />,
    },
    { key: 'description', label: 'Description' },
    {
      key: 'reported_at',
      label: 'Reported',
      sortable: true,
      render: (b) => b.reported_at ? new Date(b.reported_at).toLocaleDateString() : '---',
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('enterprise.gdpr_breaches_title')}
        description={t('enterprise.gdpr_breaches_desc')}
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              isLoading={loading}
              size="sm"
            >
              Refresh
            </Button>
            <Button
              color="danger"
              startContent={<Plus size={16} />}
              onPress={openReportModal}
              size="sm"
            >
              Report Breach
            </Button>
          </div>
        }
      />

      <DataTable
        columns={columns}
        data={breaches}
        isLoading={loading}
        searchable={false}
        emptyContent="No data breaches recorded"
      />

      <Modal isOpen={reportOpen} onClose={() => setReportOpen(false)} size="lg">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <AlertTriangle size={20} className="text-danger" />
            Report Data Breach
          </ModalHeader>
          <ModalBody className="gap-4">
            <Input
              label={t('enterprise.label_title')}
              placeholder={t('enterprise.placeholder_brief_description_of_the_breach')}
              value={breachTitle}
              onValueChange={setBreachTitle}
              variant="bordered"
              isRequired
            />
            <Textarea
              label={t('enterprise.label_description')}
              placeholder="Detailed description of what happened, what data was affected..."
              value={breachDescription}
              onValueChange={setBreachDescription}
              variant="bordered"
              minRows={3}
            />
            <div className="grid grid-cols-2 gap-4">
              <Select
                label={t('enterprise.label_severity')}
                selectedKeys={[breachSeverity]}
                onSelectionChange={(keys) => {
                  const val = Array.from(keys)[0] as string;
                  if (val) setBreachSeverity(val);
                }}
                variant="bordered"
              >
                {SEVERITY_OPTIONS.map((opt) => (
                  <SelectItem key={opt.value}>{opt.label}</SelectItem>
                ))}
              </Select>
              <Input
                label={t('enterprise.label_affected_users')}
                placeholder="0"
                type="number"
                value={affectedUsers}
                onValueChange={setAffectedUsers}
                variant="bordered"
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setReportOpen(false)} isDisabled={reportLoading}>
              Cancel
            </Button>
            <Button color="danger" onPress={handleReportBreach} isLoading={reportLoading}>
              Report Breach
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default GdprBreaches;
