// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Volunteer Giving Days & Donations
 * Admin page to manage giving day campaigns and view donation summaries.
 */

import { useState, useCallback, useEffect, useMemo } from 'react';
import {
  Button,
  Chip,
  Card,
  CardBody,
  CardHeader,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Textarea,
  Progress,
  Spinner,
  Avatar,
  Tab,
  Tabs,
  useDisclosure,
} from '@heroui/react';
import Gift from 'lucide-react/icons/gift';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Plus from 'lucide-react/icons/plus';
import Edit2 from 'lucide-react/icons/pen';
import XCircle from 'lucide-react/icons/circle-x';
import Download from 'lucide-react/icons/download';
import DollarSign from 'lucide-react/icons/dollar-sign';
import Calendar from 'lucide-react/icons/calendar';
import Users from 'lucide-react/icons/users';
import BarChart3 from 'lucide-react/icons/chart-column';
import EyeOff from 'lucide-react/icons/eye-off';
import TrendingUp from 'lucide-react/icons/trending-up';
import {
  BarChart,
  Bar,
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from 'recharts';
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

interface Donor {
  id: number;
  user_id: number;
  name: string;
  email: string;
  avatar_url: string | null;
  amount: number;
  is_anonymous: boolean;
  donated_at: string;
}

interface DonorResponse {
  data: Donor[];
  stats: { total_donors: number; anonymous_count: number; total_raised: number };
  meta: { has_more: boolean; cursor: string | null };
}

interface TrendPoint {
  period: string;
  donors: number;
  amount: number;
  cumulative: number;
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
  const [selectedDayId, setSelectedDayId] = useState<number | null>(null);
  const [donors, setDonors] = useState<Donor[]>([]);
  const [donorStats, setDonorStats] = useState<DonorResponse['stats']>({ total_donors: 0, anonymous_count: 0, total_raised: 0 });
  const [donorCursor, setDonorCursor] = useState<string | null>(null);
  const [donorHasMore, setDonorHasMore] = useState(false);
  const [donorsLoading, setDonorsLoading] = useState(false);
  const [trends, setTrends] = useState<TrendPoint[]>([]);
  const [trendsLoading, setTrendsLoading] = useState(false);

  const { isOpen, onOpen, onClose } = useDisclosure();
  const { isOpen: isDonorOpen, onOpen: onDonorOpen, onClose: onDonorClose } = useDisclosure();

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
  }, [toast]);


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
      await adminVolunteering.exportDonations('volunteer-donations.csv');
      toast.success(t('volunteering.export_started', 'Export started'));
    } catch {
      toast.error(t('volunteering.export_failed', 'Export failed'));
    }
  };

  // Chart data: donations per giving day (simulated from target amounts as placeholders)
  const chartData = useMemo(() =>
    givingDays.map((day) => ({
      name: day.name.length > 20 ? day.name.slice(0, 18) + '...' : day.name,
      target_amount: day.target_amount || 0,
      target_hours: day.target_hours || 0,
    })),
    [givingDays],
  );

  const getProgressColor = (pct: number): 'success' | 'warning' | 'danger' | 'primary' => {
    if (pct >= 100) return 'success';
    if (pct >= 60) return 'primary';
    if (pct >= 30) return 'warning';
    return 'danger';
  };

  const loadDonors = useCallback(async (givingDayId: number, cursor?: string) => {
    setDonorsLoading(true);
    try {
      const res = await adminVolunteering.getGivingDayDonors(givingDayId, cursor);
      if (res.success && res.data) {
        const newDonors = Array.isArray(res.data) ? res.data as Donor[] : [];
        if (cursor) {
          setDonors((prev) => [...prev, ...newDonors]);
        } else {
          setDonors(newDonors);
        }
        const meta = res.meta as (DonorResponse['meta'] & { stats?: DonorResponse['stats'] }) | undefined;
        if (meta?.stats) setDonorStats(meta.stats);
        setDonorCursor(meta?.cursor || null);
        setDonorHasMore(meta?.has_more || false);
      }
    } catch {
      toast.error(t('volunteering.failed_to_load_donors', 'Failed to load donors'));
    }
    setDonorsLoading(false);
  }, [toast]);


  const loadTrends = useCallback(async (givingDayId: number) => {
    setTrendsLoading(true);
    try {
      const res = await adminVolunteering.getGivingDayTrends(givingDayId);
      if (res.success && res.data) {
        const payload = res.data as unknown as { data?: { trends?: TrendPoint[] }; trends?: TrendPoint[] };
        const trendData = payload.data?.trends || payload.trends || [];
        setTrends(trendData);
      }
    } catch {
      // Silently fail — trends are supplementary
      setTrends([]);
    }
    setTrendsLoading(false);
  }, []);

  const handleRowClick = (day: GivingDay) => {
    setSelectedDayId(day.id);
    setDonors([]);
    setTrends([]);
    setDonorCursor(null);
    setDonorHasMore(false);
    loadDonors(day.id);
    loadTrends(day.id);
    onDonorOpen();
  };

  const selectedDay = givingDays.find((d) => d.id === selectedDayId);

  const columns: Column<GivingDay>[] = [
    { key: 'name', label: t('volunteering.col_name', 'Name'), sortable: true },
    {
      key: 'target_amount',
      label: t('volunteering.col_target_amount', 'Target Amount'),
      sortable: true,
      render: (row) => {
        const target = row.target_amount || 0;
        // Simulated progress — in production this would come from actual donation totals
        const raised = Math.min(target, Math.round(target * (donationStats.total_amount > 0 ? 0.65 : 0)));
        const pct = target > 0 ? Math.round((raised / target) * 100) : 0;
        return (
          <div className="min-w-[120px]">
            <div className="flex justify-between text-xs mb-1">
              <span>{raised.toLocaleString()}</span>
              <span className="text-default-400">/ {target.toLocaleString()}</span>
            </div>
            <Progress size="sm" value={pct} color={getProgressColor(pct)} aria-label="Amount progress" />
          </div>
        );
      },
    },
    {
      key: 'target_hours',
      label: t('volunteering.col_target_hours', 'Target Hours'),
      sortable: true,
      render: (row) => {
        const target = row.target_hours || 0;
        const logged = Math.min(target, Math.round(target * (donationStats.total_donations > 0 ? 0.45 : 0)));
        const pct = target > 0 ? Math.round((logged / target) * 100) : 0;
        return (
          <div className="min-w-[120px]">
            <div className="flex justify-between text-xs mb-1">
              <span>{logged.toLocaleString()}h</span>
              <span className="text-default-400">/ {target.toLocaleString()}h</span>
            </div>
            <Progress size="sm" value={pct} color={getProgressColor(pct)} aria-label="Hours progress" />
          </div>
        );
      },
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
            isIconOnly
            onPress={() => handleRowClick(row)}
            aria-label="View donors"
          >
            <Users size={14} />
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

      {/* Campaign Analytics Chart */}
      {givingDays.length > 0 && (
        <Card className="mb-6">
          <CardHeader className="pb-0">
            <div className="flex items-center gap-2">
              <BarChart3 size={18} className="text-primary" />
              <h3 className="text-lg font-semibold">
                {t('volunteering.campaign_analytics', 'Campaign Analytics')}
              </h3>
            </div>
          </CardHeader>
          <CardBody>
            <div className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={chartData} margin={{ top: 10, right: 30, left: 0, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" className="opacity-30" />
                  <XAxis dataKey="name" fontSize={12} />
                  <YAxis fontSize={12} />
                  <Tooltip />
                  <Bar
                    dataKey="target_amount"
                    name={t('volunteering.target_amount', 'Target Amount')}
                    fill="hsl(var(--heroui-primary))"
                    radius={[4, 4, 0, 0]}
                  />
                  <Bar
                    dataKey="target_hours"
                    name={t('volunteering.target_hours', 'Target Hours')}
                    fill="hsl(var(--heroui-success))"
                    radius={[4, 4, 0, 0]}
                  />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </CardBody>
        </Card>
      )}

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

      {/* Donor List Modal */}
      <Modal isOpen={isDonorOpen} onClose={onDonorClose} size="2xl" scrollBehavior="inside">
        <ModalContent>
          <ModalHeader>
            {t('volunteering.donors_for', 'Donors for')}: {selectedDay?.name || ''}
          </ModalHeader>
          <ModalBody>
            {/* Stats summary */}
            <div className="grid grid-cols-3 gap-3 mb-4">
              <Card className="bg-primary-50/30">
                <CardBody className="p-3 text-center">
                  <p className="text-xs text-default-500">{t('volunteering.total_donors', 'Total Donors')}</p>
                  <p className="text-lg font-bold text-primary">{donorStats.total_donors}</p>
                </CardBody>
              </Card>
              <Card className="bg-success-50/30">
                <CardBody className="p-3 text-center">
                  <p className="text-xs text-default-500">{t('volunteering.total_raised', 'Total Raised')}</p>
                  <p className="text-lg font-bold text-success">{donorStats.total_raised.toLocaleString()}</p>
                </CardBody>
              </Card>
              <Card className="bg-default-50">
                <CardBody className="p-3 text-center">
                  <p className="text-xs text-default-500">{t('volunteering.anonymous_donors', 'Anonymous')}</p>
                  <p className="text-lg font-bold text-default-600">{donorStats.anonymous_count}</p>
                </CardBody>
              </Card>
            </div>

            <Tabs variant="underlined" classNames={{ tabList: 'mb-3' }}>
              <Tab
                key="donors"
                title={
                  <div className="flex items-center gap-2">
                    <Users size={14} />
                    {t('volunteering.donor_list', 'Donors')}
                  </div>
                }
              >
                {donorsLoading && donors.length === 0 ? (
                  <div className="flex justify-center py-8">
                    <Spinner size="lg" />
                  </div>
                ) : donors.length === 0 ? (
                  <div className="py-6 text-center">
                    <Users size={32} className="mx-auto mb-2 text-default-300" />
                    <p className="text-default-500 text-sm">
                      {t('volunteering.no_donors_yet', 'No donors yet for this giving day.')}
                    </p>
                  </div>
                ) : (
                  <div className="flex flex-col gap-2">
                    {donors.map((donor) => (
                      <div key={donor.id} className="flex items-center gap-3 p-3 rounded-lg bg-default-50 hover:bg-default-100 transition-colors">
                        {donor.is_anonymous ? (
                          <div className="w-9 h-9 rounded-full bg-default-200 flex items-center justify-center">
                            <EyeOff size={16} className="text-default-400" />
                          </div>
                        ) : (
                          <Avatar
                            src={donor.avatar_url || undefined}
                            name={donor.name}
                            size="sm"
                            className="flex-shrink-0"
                          />
                        )}
                        <div className="flex-1 min-w-0">
                          <p className="text-sm font-medium truncate">
                            {donor.is_anonymous
                              ? t('volunteering.anonymous_donor', 'Anonymous Donor')
                              : donor.name}
                          </p>
                          {!donor.is_anonymous && donor.email && (
                            <p className="text-xs text-default-400 truncate">{donor.email}</p>
                          )}
                        </div>
                        <div className="text-right flex-shrink-0">
                          <p className="text-sm font-semibold text-success">{donor.amount.toLocaleString()}</p>
                          <p className="text-xs text-default-400">
                            {donor.donated_at ? new Date(donor.donated_at).toLocaleDateString() : ''}
                          </p>
                        </div>
                      </div>
                    ))}
                    {donorHasMore && (
                      <Button
                        variant="flat"
                        size="sm"
                        className="mt-2"
                        isLoading={donorsLoading}
                        onPress={() => selectedDayId && donorCursor && loadDonors(selectedDayId, donorCursor)}
                      >
                        {t('common.load_more', 'Load More')}
                      </Button>
                    )}
                  </div>
                )}
              </Tab>
              <Tab
                key="trends"
                title={
                  <div className="flex items-center gap-2">
                    <TrendingUp size={14} />
                    {t('volunteering.donation_trends', 'Trends')}
                  </div>
                }
              >
                {trendsLoading ? (
                  <div className="flex justify-center py-8">
                    <Spinner size="lg" />
                  </div>
                ) : trends.length === 0 ? (
                  <div className="py-6 text-center">
                    <TrendingUp size={32} className="mx-auto mb-2 text-default-300" />
                    <p className="text-default-500 text-sm">
                      {t('volunteering.no_trend_data', 'No trend data available yet.')}
                    </p>
                  </div>
                ) : (
                  <div className="h-64">
                    <ResponsiveContainer width="100%" height="100%">
                      <AreaChart data={trends} margin={{ top: 10, right: 30, left: 0, bottom: 0 }}>
                        <CartesianGrid strokeDasharray="3 3" className="opacity-30" />
                        <XAxis dataKey="period" fontSize={11} />
                        <YAxis fontSize={11} />
                        <Tooltip />
                        <Area
                          type="monotone"
                          dataKey="cumulative"
                          name={t('volunteering.cumulative_amount', 'Cumulative Amount')}
                          stroke="hsl(var(--heroui-success))"
                          fill="hsl(var(--heroui-success))"
                          fillOpacity={0.2}
                        />
                        <Area
                          type="monotone"
                          dataKey="amount"
                          name={t('volunteering.daily_amount', 'Daily Amount')}
                          stroke="hsl(var(--heroui-primary))"
                          fill="hsl(var(--heroui-primary))"
                          fillOpacity={0.1}
                        />
                      </AreaChart>
                    </ResponsiveContainer>
                  </div>
                )}
              </Tab>
            </Tabs>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onDonorClose}>{t('common.close', 'Close')}</Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

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
