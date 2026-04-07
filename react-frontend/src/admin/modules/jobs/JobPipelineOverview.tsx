// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Job Pipeline Overview
 *
 * Interview and offer oversight for admins, with filterable DataTables
 * and status-coded chips for each pipeline stage.
 */

import { useState, useEffect, useCallback, useMemo } from 'react';
import {
  Tabs,
  Tab,
  Chip,
  Card,
  CardBody,
  Spinner,
  Select,
  SelectItem,
} from '@heroui/react';
import { CalendarClock, Handshake } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader, EmptyState, DataTable, type Column } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface Interview {
  id: number;
  vacancy_id: number;
  application_id: number;
  interview_type: string;
  scheduled_at: string;
  duration_mins: number;
  location_notes?: string;
  status: string;
  candidate_notes?: string;
  candidate_name: string | null;
  candidate_email: string | null;
  job_title: string | null;
  created_at: string;
}

interface Offer {
  id: number;
  vacancy_id: number;
  application_id: number;
  salary_offered?: number;
  start_date?: string;
  details?: string;
  status: string;
  expires_at?: string;
  responded_at?: string;
  candidate_name: string | null;
  candidate_email: string | null;
  job_title: string | null;
  created_at: string;
}

interface PaginatedResponse<T> {
  items: T[];
  total: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Status color map
// ─────────────────────────────────────────────────────────────────────────────

const STATUS_COLOR_MAP: Record<string, 'warning' | 'success' | 'danger' | 'default'> = {
  proposed: 'warning',
  pending: 'warning',
  accepted: 'success',
  declined: 'danger',
  rejected: 'danger',
  cancelled: 'default',
  withdrawn: 'default',
};

function getStatusColor(status: string): 'warning' | 'success' | 'danger' | 'default' {
  return STATUS_COLOR_MAP[status.toLowerCase()] ?? 'default';
}

// ─────────────────────────────────────────────────────────────────────────────
// Filter options
// ─────────────────────────────────────────────────────────────────────────────

const INTERVIEW_STATUSES = ['all', 'proposed', 'accepted', 'declined', 'cancelled'] as const;
const OFFER_STATUSES = ['all', 'pending', 'accepted', 'rejected', 'withdrawn'] as const;

const PAGE_SIZE = 20;

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function JobPipelineOverview() {
  const { t } = useTranslation('admin');
  usePageTitle(t('jobs.pipeline_title', 'Job Pipeline'));
  const toast = useToast();

  // Tab
  const [activeTab, setActiveTab] = useState<string>('interviews');

  // ── Interviews state ────────────────────────────────────────────────────
  const [interviews, setInterviews] = useState<Interview[]>([]);
  const [interviewsTotal, setInterviewsTotal] = useState(0);
  const [interviewsPage, setInterviewsPage] = useState(1);
  const [interviewsStatus, setInterviewsStatus] = useState('all');
  const [interviewsLoading, setInterviewsLoading] = useState(true);

  // ── Offers state ────────────────────────────────────────────────────────
  const [offers, setOffers] = useState<Offer[]>([]);
  const [offersTotal, setOffersTotal] = useState(0);
  const [offersPage, setOffersPage] = useState(1);
  const [offersStatus, setOffersStatus] = useState('all');
  const [offersLoading, setOffersLoading] = useState(true);

  // ── Fetch interviews ───────────────────────────────────────────────────
  const loadInterviews = useCallback(async () => {
    setInterviewsLoading(true);
    try {
      const params = new URLSearchParams({
        page: String(interviewsPage),
        limit: String(PAGE_SIZE),
      });
      if (interviewsStatus !== 'all') {
        params.set('status', interviewsStatus);
      }
      const res = await api.get<PaginatedResponse<Interview>>(
        `/v2/admin/jobs/interviews?${params.toString()}`
      );
      if (res.success && res.data) {
        setInterviews(res.data.items ?? []);
        setInterviewsTotal(res.data.total ?? 0);
      }
    } catch {
      toast.error(t('jobs.pipeline_load_error', 'Failed to load interviews'));
    } finally {
      setInterviewsLoading(false);
    }
  }, [interviewsPage, interviewsStatus, toast, t]);

  // ── Fetch offers ────────────────────────────────────────────────────────
  const loadOffers = useCallback(async () => {
    setOffersLoading(true);
    try {
      const params = new URLSearchParams({
        page: String(offersPage),
        limit: String(PAGE_SIZE),
      });
      if (offersStatus !== 'all') {
        params.set('status', offersStatus);
      }
      const res = await api.get<PaginatedResponse<Offer>>(
        `/v2/admin/jobs/offers?${params.toString()}`
      );
      if (res.success && res.data) {
        setOffers(res.data.items ?? []);
        setOffersTotal(res.data.total ?? 0);
      }
    } catch {
      toast.error(t('jobs.pipeline_load_error_offers', 'Failed to load offers'));
    } finally {
      setOffersLoading(false);
    }
  }, [offersPage, offersStatus, toast, t]);

  // ── Effects ─────────────────────────────────────────────────────────────
  useEffect(() => {
    loadInterviews();
  }, [loadInterviews]);

  useEffect(() => {
    loadOffers();
  }, [loadOffers]);

  // Reset page when filter changes
  useEffect(() => {
    setInterviewsPage(1);
  }, [interviewsStatus]);

  useEffect(() => {
    setOffersPage(1);
  }, [offersStatus]);

  // ── Interview columns ──────────────────────────────────────────────────
  const interviewColumns: Column<Interview>[] = useMemo(
    () => [
      {
        key: 'job_title',
        label: t('jobs.pipeline_job_title', 'Job Title'),
        sortable: true,
        render: (item) => item.job_title ?? '—',
      },
      {
        key: 'candidate_name',
        label: t('jobs.pipeline_candidate', 'Candidate'),
        sortable: true,
        render: (item) => (
          <div className="min-w-0">
            <p className="text-sm font-medium truncate">
              {item.candidate_name ?? '—'}
            </p>
            {item.candidate_email && (
              <p className="text-xs text-default-500 truncate">
                {item.candidate_email}
              </p>
            )}
          </div>
        ),
      },
      {
        key: 'interview_type',
        label: t('jobs.pipeline_type', 'Type'),
        render: (item) => (
          <Chip size="sm" variant="flat" color="primary" className="capitalize">
            {item.interview_type}
          </Chip>
        ),
      },
      {
        key: 'scheduled_at',
        label: t('jobs.pipeline_scheduled_at', 'Scheduled At'),
        sortable: true,
        render: (item) =>
          new Date(item.scheduled_at).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
          }),
      },
      {
        key: 'duration_mins',
        label: t('jobs.pipeline_duration', 'Duration'),
        render: (item) =>
          t('jobs.pipeline_minutes', '{{count}} min', { count: item.duration_mins }),
      },
      {
        key: 'status',
        label: t('jobs.pipeline_status', 'Status'),
        render: (item) => (
          <Chip size="sm" variant="flat" color={getStatusColor(item.status)} className="capitalize">
            {item.status}
          </Chip>
        ),
      },
    ],
    [t]
  );

  // ── Offer columns ─────────────────────────────────────────────────────
  const offerColumns: Column<Offer>[] = useMemo(
    () => [
      {
        key: 'job_title',
        label: t('jobs.pipeline_job_title', 'Job Title'),
        sortable: true,
        render: (item) => item.job_title ?? '—',
      },
      {
        key: 'candidate_name',
        label: t('jobs.pipeline_candidate', 'Candidate'),
        sortable: true,
        render: (item) => (
          <div className="min-w-0">
            <p className="text-sm font-medium truncate">
              {item.candidate_name ?? '—'}
            </p>
            {item.candidate_email && (
              <p className="text-xs text-default-500 truncate">
                {item.candidate_email}
              </p>
            )}
          </div>
        ),
      },
      {
        key: 'salary_offered',
        label: t('jobs.pipeline_salary', 'Salary'),
        sortable: true,
        render: (item) =>
          item.salary_offered != null
            ? new Intl.NumberFormat(undefined, {
                style: 'currency',
                currency: 'EUR',
                maximumFractionDigits: 0,
              }).format(item.salary_offered)
            : '—',
      },
      {
        key: 'start_date',
        label: t('jobs.pipeline_start_date', 'Start Date'),
        sortable: true,
        render: (item) =>
          item.start_date
            ? new Date(item.start_date).toLocaleDateString(undefined, {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
              })
            : '—',
      },
      {
        key: 'status',
        label: t('jobs.pipeline_status', 'Status'),
        render: (item) => (
          <Chip size="sm" variant="flat" color={getStatusColor(item.status)} className="capitalize">
            {item.status}
          </Chip>
        ),
      },
      {
        key: 'expires_at',
        label: t('jobs.pipeline_expires_at', 'Expires At'),
        sortable: true,
        render: (item) =>
          item.expires_at
            ? new Date(item.expires_at).toLocaleDateString(undefined, {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
              })
            : '—',
      },
    ],
    [t]
  );

  // ── Render ──────────────────────────────────────────────────────────────
  return (
    <div>
      <PageHeader
        title={t('jobs.pipeline_title', 'Job Pipeline')}
        description={t(
          'jobs.pipeline_description',
          'Overview of interviews and offers across all job vacancies'
        )}
      />

      <Tabs
        aria-label={t('jobs.pipeline_tabs', 'Pipeline Tabs')}
        selectedKey={activeTab}
        onSelectionChange={(key) => setActiveTab(String(key))}
        color="primary"
        variant="underlined"
        classNames={{ tabList: 'mb-4' }}
      >
        {/* ── Interviews Tab ─────────────────────────────────────────────── */}
        <Tab
          key="interviews"
          title={
            <div className="flex items-center gap-2">
              <CalendarClock size={16} />
              {t('jobs.pipeline_interviews', 'Interviews')}
              {interviewsTotal > 0 && (
                <Chip size="sm" variant="flat" color="primary">
                  {interviewsTotal}
                </Chip>
              )}
            </div>
          }
        >
          <Card shadow="sm">
            <CardBody>
              <div className="mb-4">
                <Select
                  label={t('jobs.pipeline_filter_status', 'Filter by Status')}
                  selectedKeys={new Set([interviewsStatus])}
                  onSelectionChange={(keys) => {
                    const selected = Array.from(keys)[0];
                    if (selected) setInterviewsStatus(String(selected));
                  }}
                  size="sm"
                  variant="bordered"
                  className="max-w-xs"
                >
                  {INTERVIEW_STATUSES.map((s) => (
                    <SelectItem key={s}>
                      {s === 'all'
                        ? t('jobs.pipeline_all', 'All')
                        : t(`jobs.pipeline_status_${s}`, s.charAt(0).toUpperCase() + s.slice(1))}
                    </SelectItem>
                  ))}
                </Select>
              </div>

              {interviewsLoading && interviews.length === 0 ? (
                <div className="flex justify-center py-16">
                  <Spinner label={t('jobs.pipeline_loading', 'Loading...')} />
                </div>
              ) : interviews.length === 0 ? (
                <EmptyState
                  icon={CalendarClock}
                  title={t('jobs.pipeline_no_interviews', 'No interviews found')}
                  description={t(
                    'jobs.pipeline_no_interviews_desc',
                    'There are no interviews matching the current filter.'
                  )}
                />
              ) : (
                <DataTable<Interview>
                  columns={interviewColumns}
                  data={interviews}
                  keyField="id"
                  isLoading={interviewsLoading}
                  searchable={false}
                  totalItems={interviewsTotal}
                  page={interviewsPage}
                  pageSize={PAGE_SIZE}
                  onPageChange={setInterviewsPage}
                />
              )}
            </CardBody>
          </Card>
        </Tab>

        {/* ── Offers Tab ─────────────────────────────────────────────────── */}
        <Tab
          key="offers"
          title={
            <div className="flex items-center gap-2">
              <Handshake size={16} />
              {t('jobs.pipeline_offers', 'Offers')}
              {offersTotal > 0 && (
                <Chip size="sm" variant="flat" color="primary">
                  {offersTotal}
                </Chip>
              )}
            </div>
          }
        >
          <Card shadow="sm">
            <CardBody>
              <div className="mb-4">
                <Select
                  label={t('jobs.pipeline_filter_status', 'Filter by Status')}
                  selectedKeys={new Set([offersStatus])}
                  onSelectionChange={(keys) => {
                    const selected = Array.from(keys)[0];
                    if (selected) setOffersStatus(String(selected));
                  }}
                  size="sm"
                  variant="bordered"
                  className="max-w-xs"
                >
                  {OFFER_STATUSES.map((s) => (
                    <SelectItem key={s}>
                      {s === 'all'
                        ? t('jobs.pipeline_all', 'All')
                        : t(`jobs.pipeline_status_${s}`, s.charAt(0).toUpperCase() + s.slice(1))}
                    </SelectItem>
                  ))}
                </Select>
              </div>

              {offersLoading && offers.length === 0 ? (
                <div className="flex justify-center py-16">
                  <Spinner label={t('jobs.pipeline_loading', 'Loading...')} />
                </div>
              ) : offers.length === 0 ? (
                <EmptyState
                  icon={Handshake}
                  title={t('jobs.pipeline_no_offers', 'No offers found')}
                  description={t(
                    'jobs.pipeline_no_offers_desc',
                    'There are no offers matching the current filter.'
                  )}
                />
              ) : (
                <DataTable<Offer>
                  columns={offerColumns}
                  data={offers}
                  keyField="id"
                  isLoading={offersLoading}
                  searchable={false}
                  totalItems={offersTotal}
                  page={offersPage}
                  pageSize={PAGE_SIZE}
                  onPageChange={setOffersPage}
                />
              )}
            </CardBody>
          </Card>
        </Tab>
      </Tabs>
    </div>
  );
}

export default JobPipelineOverview;
