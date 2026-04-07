// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Volunteer Giving Days & Donations
 * Admin page to manage giving day campaigns and view donation summaries.
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Button,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Textarea,
  useDisclosure,
} from '@heroui/react';
import {
  Gift,
  RefreshCw,
  Plus,
  Edit2,
  XCircle,
  Download,
  DollarSign,
  Calendar,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminVolunteering } from '../../api/adminApi';
import { DataTable, PageHeader, StatCard, EmptyState, type Column } from '../../components';
import { useTranslation } from 'react-i18next';

interface GivingDay {
  id: number;
  name: string;
  description?: string;
  target_amount: number;
  target_hours: number;
  start_date: string;
  end_date: string;
  is_active: boolean;
  created_at: string;
}

interface DonationStats {
  total_donations: number;
  total_amount: number;
}

const emptyForm = {
  name: '',
  description: '',
  target_amount: '',
  target_hours: '',
  start_date: '',
  end_date: '',
};

export default function VolunteerGivingDays() {
  const { t } = useTranslation('admin');
  usePageTitle(t('volunteering.giving_days_title', 'Giving Days'));
  const toast = useToast();

  const [givingDays, setGivingDays] = useState<GivingDay[]>([]);
  const [donationStats, setDonationStats] = useState<DonationStats>({ total_donations: 0, total_amount: 0 });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState(emptyForm);

  const { isOpen, onOpen, onClose } = useDisclosure();

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminVolunteering.getGivingDays();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        let d: { giving_days?: GivingDay[]; donation_stats?: DonationStats };
        if (payload && typeof payload === 'object' && 'data' in payload) {
          d = (payload as { data: typeof d }).data;
        } else {
          d = payload as typeof d;
        }
        setGivingDays(d.giving_days || (Array.isArray(d) ? d as unknown as GivingDay[] : []));
        if (d.donation_stats) setDonationStats(d.donation_stats);
      }
    } catch {
      toast.error(t('volunteering.failed_to_load_giving_days', 'Failed to load giving days'));
      setGivingDays([]);
    }
    setLoading(false);
  }, [toast, t]);

  useEffect(() => { loadData(); }, [loadData]);

  const openCreate = () => {
    setEditingId(null);
    setForm(emptyForm);
    onOpen();
  };

  const openEdit = (day: GivingDay) => {
    setEditingId(day.id);
    setForm({
      name: day.name,
      description: day.description || '',
      target_amount: String(day.target_amount),
      target_hours: String(day.target_hours),
      start_date: day.start_date?.slice(0, 10) || '',
      end_date: day.end_date?.slice(0, 10) || '',
    });
    onOpen();
  };

  const handleSave = async () => {
    if (!form.name.trim()) {
      toast.error(t('volunteering.name_required', 'Name is required'));
      return;
    }
    setSaving(true);
    try {
      const payload = {
        name: form.name.trim(),
        description: form.description.trim(),
        target_amount: Number(form.target_amount) || 0,
        target_hours: Number(form.target_hours) || 0,
        start_date: form.start_date,
        end_date: form.end_date,
      };
      if (editingId) {
        await adminVolunteering.updateGivingDay(editingId, payload);
        toast.success(t('volunteering.giving_day_updated', 'Giving day updated'));
      } else {
        await adminVolunteering.createGivingDay(payload);
        toast.success(t('volunteering.giving_day_created', 'Giving day created'));
      }
      onClose();
      loadData();
    } catch {
      toast.error(t('volunteering.failed_to_save', 'Failed to save'));
    }
    setSaving(false);
  };

  const handleDeactivate = async (day: GivingDay) => {
    try {
      await adminVolunteering.updateGivingDay(day.id, { is_active: !day.is_active });
      toast.success(day.is_active
        ? t('volunteering.giving_day_deactivated', 'Giving day deactivated')
        : t('volunteering.giving_day_activated', 'Giving day activated'));
      loadData();
    } catch {
      toast.error(t('volunteering.failed_to_update_status', 'Failed to update status'));
    }
  };

  const handleExport = async () => {
    try {
      const res = await adminVolunteering.exportDonations();
      if (res.success && res.data) {
        // Trigger download if the response contains a URL or blob
        const payload = res.data as unknown;
        if (typeof payload === 'string') {
          window.open(payload, '_blank');
        } else if (payload && typeof payload === 'object' && 'url' in payload) {
          window.open((payload as { url: string }).url, '_blank');
        }
        toast.success(t('volunteering.export_started', 'Export started'));
      }
    } catch {
      toast.error(t('volunteering.export_failed', 'Export failed'));
    }
  };

  const columns: Column<GivingDay>[] = [
    { key: 'name', label: t('volunteering.col_name', 'Name'), sortable: true },
    {
      key: 'target_amount',
      label: t('volunteering.col_target_amount', 'Target Amount'),
      sortable: true,
      render: (row) => <span>{row.target_amount?.toLocaleString() ?? 0}</span>,
    },
    {
      key: 'target_hours',
      label: t('volunteering.col_target_hours', 'Target Hours'),
      sortable: true,
      render: (row) => <span>{row.target_hours?.toLocaleString() ?? 0}</span>,
    },
    {
      key: 'start_date',
      label: t('volunteering.col_start_date', 'Start Date'),
      sortable: true,
      render: (row) => <span>{row.start_date ? new Date(row.start_date).toLocaleDateString() : '-'}</span>,
    },
    {
      key: 'end_date',
      label: t('volunteering.col_end_date', 'End Date'),
      sortable: true,
      render: (row) => <span>{row.end_date ? new Date(row.end_date).toLocaleDateString() : '-'}</span>,
    },
    {
      key: 'is_active',
      label: t('volunteering.col_status', 'Status'),
      render: (row) => (
        <Chip size="sm" color={row.is_active ? 'success' : 'default'} variant="flat">
          {row.is_active ? t('volunteering.active', 'Active') : t('volunteering.inactive', 'Inactive')}
        </Chip>
      ),
    },
    {
      key: 'actions' as keyof GivingDay,
      label: t('common.actions', 'Actions'),
      render: (row) => (
        <div className="flex items-center gap-1">
          <Button size="sm" variant="flat" isIconOnly onPress={() => openEdit(row)} aria-label="Edit">
            <Edit2 size={14} />
          </Button>
          <Button
            size="sm"
            variant="flat"
            color={row.is_active ? 'danger' : 'success'}
            isIconOnly
            onPress={() => handleDeactivate(row)}
            aria-label={row.is_active ? 'Deactivate' : 'Activate'}
          >
            <XCircle size={14} />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('volunteering.giving_days_title', 'Giving Days & Donations')}
        description={t('volunteering.giving_days_desc', 'Manage giving day campaigns and view donation summaries')}
        actions={
          <div className="flex items-center gap-2">
            <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>
              {t('common.refresh', 'Refresh')}
            </Button>
            <Button color="primary" startContent={<Plus size={16} />} onPress={openCreate}>
              {t('volunteering.create_giving_day', 'Create Giving Day')}
            </Button>
          </div>
        }
      />

      {givingDays.length === 0 && !loading ? (
        <EmptyState
          icon={Gift}
          title={t('volunteering.no_giving_days', 'No giving days yet')}
          description={t('volunteering.no_giving_days_desc', 'Create your first giving day campaign to get started.')}
        />
      ) : (
        <DataTable columns={columns} data={givingDays} isLoading={loading} />
      )}

      {/* Donations Summary */}
      <div className="mt-8">
        <h3 className="text-lg font-semibold mb-4">{t('volunteering.donations_summary', 'Donations Summary')}</h3>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 mb-4">
          <StatCard
            label={t('volunteering.total_donations', 'Total Donations')}
            value={donationStats.total_donations}
            icon={Gift}
            color="primary"
            loading={loading}
          />
          <StatCard
            label={t('volunteering.total_amount', 'Total Amount')}
            value={donationStats.total_amount}
            icon={DollarSign}
            color="success"
            loading={loading}
          />
        </div>
        <Button variant="flat" startContent={<Download size={16} />} onPress={handleExport}>
          {t('volunteering.export_donations', 'Export Donations')}
        </Button>
      </div>

      {/* Create/Edit Modal */}
      <Modal isOpen={isOpen} onClose={onClose} size="lg">
        <ModalContent>
          <ModalHeader>
            {editingId
              ? t('volunteering.edit_giving_day', 'Edit Giving Day')
              : t('volunteering.create_giving_day', 'Create Giving Day')}
          </ModalHeader>
          <ModalBody>
            <div className="flex flex-col gap-4">
              <Input
                label={t('volunteering.field_name', 'Name')}
                value={form.name}
                onValueChange={(v) => setForm((f) => ({ ...f, name: v }))}
                isRequired
                variant="bordered"
              />
              <Textarea
                label={t('volunteering.field_description', 'Description')}
                value={form.description}
                onValueChange={(v) => setForm((f) => ({ ...f, description: v }))}
                variant="bordered"
              />
              <div className="grid grid-cols-2 gap-4">
                <Input
                  label={t('volunteering.field_target_amount', 'Target Amount')}
                  type="number"
                  value={form.target_amount}
                  onValueChange={(v) => setForm((f) => ({ ...f, target_amount: v }))}
                  variant="bordered"
                  startContent={<DollarSign size={14} className="text-default-400" />}
                />
                <Input
                  label={t('volunteering.field_target_hours', 'Target Hours')}
                  type="number"
                  value={form.target_hours}
                  onValueChange={(v) => setForm((f) => ({ ...f, target_hours: v }))}
                  variant="bordered"
                  startContent={<Calendar size={14} className="text-default-400" />}
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <Input
                  label={t('volunteering.field_start_date', 'Start Date')}
                  type="date"
                  value={form.start_date}
                  onValueChange={(v) => setForm((f) => ({ ...f, start_date: v }))}
                  variant="bordered"
                />
                <Input
                  label={t('volunteering.field_end_date', 'End Date')}
                  type="date"
                  value={form.end_date}
                  onValueChange={(v) => setForm((f) => ({ ...f, end_date: v }))}
                  variant="bordered"
                />
              </div>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose}>{t('common.cancel', 'Cancel')}</Button>
            <Button color="primary" onPress={handleSave} isLoading={saving}>
              {editingId ? t('common.save', 'Save') : t('common.create', 'Create')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
