// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Jobs Management
 * List, search, feature/unfeature, and delete job vacancies.
 * Includes Applications review panel for per-job applicant management.
 */

import { useState, useEffect, useCallback } from 'react';
import { Tabs, Tab, Chip, Button, Tooltip, Avatar, Spinner, Textarea, Select, SelectItem, Card, CardBody } from '@heroui/react';
import Briefcase from 'lucide-react/icons/briefcase';
import Star from 'lucide-react/icons/star';
import StarOff from 'lucide-react/icons/star-off';
import Trash2 from 'lucide-react/icons/trash-2';
import Eye from 'lucide-react/icons/eye';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import ChevronDown from 'lucide-react/icons/chevron-down';
import ChevronUp from 'lucide-react/icons/chevron-up';
import Users from 'lucide-react/icons/users';
import ClipboardList from 'lucide-react/icons/clipboard-list';
import CheckCircle2 from 'lucide-react/icons/circle-check';
import Save from 'lucide-react/icons/save';
import FileText from 'lucide-react/icons/file-text';
import Calendar from 'lucide-react/icons/calendar';
import Gift from 'lucide-react/icons/gift';
import TrendingUp from 'lucide-react/icons/trending-up';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { resolveAvatarUrl } from '@/lib/helpers';
import { PageHeader, DataTable, ConfirmModal, EmptyState, StatCard, type Column } from '../../components';

interface Job { id: number; title: string; organization_name?: string; poster_name?: string; type?: string; applications_count: number; views_count: number; is_featured: boolean; status: string; deadline?: string; created_at: string; }
interface JobsMeta { page: number; per_page: number; total: number; total_pages: number; }
interface Applicant { id: number; name: string; avatar_url?: string; email?: string; }
interface Application { id: number; vacancy_id: number; user_id: number; applicant: Applicant; status: string; message?: string; reviewer_notes?: string; cv_url?: string; has_interview?: boolean; interview_status?: string; has_offer?: boolean; offer_status?: string; created_at: string; updated_at?: string; }
interface JobStats { total_jobs: number; open_jobs: number; total_applications: number; total_views: number; conversion_rate: number; avg_time_to_fill: number | null; active_interviews: number; pending_offers: number; stage_breakdown: Record<string, number>; }

const STATUS_TAB_KEYS = ['all', 'open', 'closed', 'expired'] as const;
const statusColorMap: Record<string, 'success' | 'default' | 'warning'> = { open: 'success', closed: 'default', expired: 'warning' };
const typeLabel: Record<string, string> = { paid: 'Paid', volunteer: 'Volunteer', timebank: 'Timebank' };
const appStatusColor: Record<string, 'default' | 'primary' | 'warning' | 'secondary' | 'success' | 'danger'> = { applied: 'default', screening: 'primary', reviewed: 'primary', pending: 'default', interview: 'warning', offer: 'secondary', accepted: 'success', rejected: 'danger', withdrawn: 'default' };
const APPLICATION_STAGE_KEYS = ['applied', 'screening', 'interview', 'offer', 'accepted', 'rejected', 'withdrawn'] as const;

interface ApplicationCardProps { application: Application; onStatusUpdate: (appId: number, status: string, notes: string) => Promise<void>; }

function ApplicationCard({ application, onStatusUpdate }: ApplicationCardProps) {
  const { t } = useTranslation('admin');
  const [expanded, setExpanded] = useState(false);
  const [notesOpen, setNotesOpen] = useState(false);
  const [selectedStatus, setSelectedStatus] = useState(application.status);
  const [notes, setNotes] = useState(application.reviewer_notes ?? '');
  const [saving, setSaving] = useState(false);
  const handleSave = async () => { setSaving(true); try { await onStatusUpdate(application.id, selectedStatus, notes); } finally { setSaving(false); } };
  const isDirty = selectedStatus !== application.status || notes !== (application.reviewer_notes ?? '');
  return (
    <Card className='mb-3'><CardBody className='gap-3 p-4'>
      <div className='flex items-start gap-3'>
        <Avatar src={resolveAvatarUrl(application.applicant.avatar_url) || undefined} name={application.applicant.name} size='sm' className='shrink-0 mt-0.5' />
        <div className='flex-1 min-w-0'>
          <div className='flex items-center gap-2 flex-wrap'>
            <span className='font-medium text-sm text-foreground truncate'>{application.applicant.name}</span>
            <Chip size='sm' variant='flat' color={appStatusColor[application.status] ?? 'default'} className='capitalize shrink-0'>{application.status}</Chip>
          </div>
          {application.applicant.email && <p className='text-xs text-default-500 truncate mt-0.5'>{application.applicant.email}</p>}
          <p className='text-xs text-default-400 mt-0.5'>{"Applied:"}{' '}{new Date(application.created_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })}</p>
          <div className='flex items-center gap-1.5 mt-1 flex-wrap'>
            {application.cv_url && (
              <Chip size='sm' variant='flat' color='primary' startContent={<FileText size={10} />} className='h-5 text-[10px]'>CV</Chip>
            )}
            {application.has_interview && (
              <Chip size='sm' variant='flat' color='warning' startContent={<Calendar size={10} />} className='h-5 text-[10px] capitalize'>{application.interview_status ?? 'Interview'}</Chip>
            )}
            {application.has_offer && (
              <Chip size='sm' variant='flat' color='secondary' startContent={<Gift size={10} />} className='h-5 text-[10px] capitalize'>{application.offer_status ?? 'Offer'}</Chip>
            )}
          </div>
        </div>
      </div>
      {application.message && (<div><Button variant="light" size="sm" className='flex items-center gap-1 text-xs text-default-500 hover:text-default-700 h-auto p-0' onPress={() => setExpanded((v) => !v)} aria-expanded={expanded}>
        {expanded ? <ChevronUp size={12} /> : <ChevronDown size={12} />} {"Cover Letter"}</Button>
        {expanded && <p className='mt-2 text-sm text-default-700 bg-default-50 rounded-lg p-3 whitespace-pre-wrap leading-relaxed'>{application.message}</p>}
      </div>)}
      <div className='flex items-end gap-2 flex-wrap'>
        <div className='flex-1 min-w-[160px]'>
          <Select size='sm' label={"Update Status"} selectedKeys={new Set([selectedStatus])} onSelectionChange={(keys) => { const val = Array.from(keys)[0] as string; if (val) setSelectedStatus(val); }} aria-label={"Update application status"} classNames={{ trigger: 'min-h-unit-10 h-10' }}>
            {APPLICATION_STAGE_KEYS.map((stage) => <SelectItem key={stage}>{t(`jobs.stage_${stage}`)}</SelectItem>)}
          </Select>
        </div>
        <Tooltip content={notesOpen ? "Hide notes" : "Add / Edit Notes"}>
          <Button isIconOnly size='sm' variant='flat' onPress={() => setNotesOpen((v) => !v)} aria-label={"Toggle notes"}><ClipboardList size={14} /></Button>
        </Tooltip>
        <Button size='sm' color={isDirty ? 'primary' : 'default'} variant={isDirty ? 'solid' : 'flat'} isDisabled={!isDirty || saving} isLoading={saving} startContent={!saving && <Save size={13} />} onPress={handleSave}>{"Update"}</Button>
      </div>
      {notesOpen && <Textarea size='sm' label={"Internal Notes"} placeholder={"Add internal notes about this application..."} value={notes} onValueChange={setNotes} minRows={2} maxRows={6} classNames={{ inputWrapper: 'bg-default-50' }} />}
    </CardBody></Card>
  );
}

export function JobsAdmin() {
  const { t } = useTranslation('admin');
  usePageTitle("Jobs");
  const toast = useToast();
  const { tenantPath } = useTenant();
  const [panelTab, setPanelTab] = useState<'listings' | 'applications'>('listings');
  const [items, setItems] = useState<Job[]>([]);
  const [meta, setMeta] = useState<JobsMeta>({ page: 1, per_page: 50, total: 0, total_pages: 1 });
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('all');
  const [search, setSearch] = useState('');
  const [confirmDelete, setConfirmDelete] = useState<Job | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [selectedJob, setSelectedJob] = useState<Job | null>(null);
  const [applications, setApplications] = useState<Application[]>([]);
  const [appsLoading, setAppsLoading] = useState(false);
  const [appsError, setAppsError] = useState<string | null>(null);
  const [stats, setStats] = useState<JobStats | null>(null);
  const [statsLoading, setStatsLoading] = useState(true);

  const loadStats = useCallback(async () => {
    setStatsLoading(true);
    try {
      const res = await api.get<JobStats>('/v2/admin/jobs/stats');
      if (res.success) setStats(res.data as JobStats);
    } catch { /* silent */ }
    finally { setStatsLoading(false); }
  }, []);

  useEffect(() => { loadStats(); }, [loadStats]);

  const loadJobs = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ page: String(page), limit: '50' });
      if (search) params.set('search', search);
      if (status !== 'all') params.set('status', status);
      const res = await api.get<Job[]>(`/v2/admin/jobs?${params.toString()}`);
      if (res.success) {
        setItems(Array.isArray(res.data) ? res.data : []);
        const m = res.meta as unknown as Record<string, unknown> | undefined;
        const paginationMeta: JobsMeta | undefined = m ? { page: Number(m.page) || 1, per_page: Number(m.per_page) || 50, total: Number(m.total) || 0, total_pages: Number(m.total_pages) || 1 } : undefined;
        setMeta(paginationMeta ?? { page: 1, per_page: 50, total: 0, total_pages: 1 });
      }
    } catch { toast.error("Failed to load jobs"); }
    finally { setLoading(false); }
  }, [page, status, search, toast]);


  useEffect(() => { loadJobs(); }, [loadJobs]);

  const loadApplications = useCallback(async (job: Job) => {
    setAppsLoading(true); setAppsError(null); setApplications([]);
    try {
      const res = await api.get<Application[]>(`/v2/admin/jobs/${job.id}/applications`);
      if (res.success) { setApplications(Array.isArray(res.data) ? res.data : []); }
      else { setAppsError((res as { error?: string }).error ?? "Failed to load applications"); }
    } catch { setAppsError("Failed to load applications"); }
    finally { setAppsLoading(false); }
  }, []);

  const handleSelectJob = useCallback((job: Job) => { setSelectedJob(job); loadApplications(job); }, [loadApplications]);

  const handleStatusUpdate = useCallback(async (appId: number, newStatus: string, notes: string) => {
    try {
      const res = await api.put(`/v2/admin/jobs/applications/${appId}`, { status: newStatus, notes });
      if (res && res.success) {
        toast.success("Application updated");
        setApplications((prev) => prev.map((appl) => appl.id === appId ? { ...appl, status: newStatus, reviewer_notes: notes } : appl));
        loadJobs();
      } else { toast.error((res as { error?: string }).error ?? "Failed to update application"); }
    } catch { toast.error("Unexpected error"); }
  }, [toast, loadJobs]);


  const handleFeatureToggle = async (job: Job) => {
    try {
      const endpoint = job.is_featured ? `/v2/admin/jobs/${job.id}/unfeature` : `/v2/admin/jobs/${job.id}/feature`;
      const res = await api.post(endpoint);
      if (res && res.success) {
        toast.success(job.is_featured ? `${job.title} removed from featured` : `${job.title} is now featured`);
        loadJobs();
      } else { toast.error((res && (res as { error?: string }).error) || "Failed to update featured status"); }
    } catch { toast.error("Unexpected error"); }
  };

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);
    try {
      const res = await api.delete(`/v2/admin/jobs/${confirmDelete.id}`);
      if (res && res.success) { toast.success("Job deleted"); loadJobs(); }
      else { toast.error((res && (res as { error?: string }).error) || "Failed to delete job"); }
    } catch { toast.error("Unexpected error"); }
    finally { setActionLoading(false); setConfirmDelete(null); }
  };

  const columns: Column<Job>[] = [
    { key: 'title', label: "Title", sortable: true, render: (item) => <span className='font-medium text-foreground'>{item.title}</span> },
    { key: 'organization_name', label: "Organization", sortable: true, render: (item) => <span className='text-sm text-default-600'>{item.organization_name || item.poster_name || '--'}</span> },
    { key: 'type', label: "Type", sortable: true, render: (item) => <span className='text-sm text-default-600'>{typeLabel[item.type ?? ''] ?? item.type ?? '--'}</span> },
    { key: 'applications_count', label: "Applications", sortable: true, render: (item) => <span className='text-sm text-default-600'>{item.applications_count}</span> },
    { key: 'views_count', label: "Views", sortable: true, render: (item) => <span className='text-sm text-default-500'>{item.views_count}</span> },
    {
      key: 'is_featured', label: "Featured",
      render: (item) => (
        <Tooltip content={item.is_featured ? "This job is featured - click to remove" : "Click to feature this job"}>
          <Button isIconOnly size='sm' variant='light' onPress={() => handleFeatureToggle(item)} aria-label={item.is_featured ? "Remove from featured" : "Feature this job"}>
            {item.is_featured ? <Star size={16} className='text-warning fill-warning' /> : <StarOff size={16} className='text-default-400' />}
          </Button>
        </Tooltip>
      ),
    },
    { key: 'status', label: "Status", sortable: true, render: (item) => <Chip size='sm' variant='flat' color={statusColorMap[item.status] || 'default'} className='capitalize'>{item.status}</Chip> },
    { key: 'deadline', label: "Deadline", sortable: true, render: (item) => <span className='text-sm text-default-500'>{item.deadline ? new Date(item.deadline).toLocaleDateString() : '--'}</span> },
    {
      key: 'actions', label: "Actions",
      render: (item) => (
        <div className='flex gap-1'>
          <Tooltip content={"View job"}>
            <Button isIconOnly size='sm' variant='flat' color='primary' as='a' href={tenantPath(`/jobs/${item.id}`)} target='_blank' rel='noopener noreferrer' aria-label={"View job"}><Eye size={14} /></Button>
          </Tooltip>
          <Tooltip content={item.is_featured ? "Unfeature" : "Feature"}>
            <Button isIconOnly size='sm' variant='flat' color='warning' onPress={() => handleFeatureToggle(item)} aria-label={item.is_featured ? "Remove from featured" : "Feature this job"}>
              {item.is_featured ? <StarOff size={14} /> : <Star size={14} />}
            </Button>
          </Tooltip>
          <Tooltip content={"Delete"}>
            <Button isIconOnly size='sm' variant='flat' color='danger' onPress={() => setConfirmDelete(item)} aria-label={"Delete"}><Trash2 size={14} /></Button>
          </Tooltip>
        </div>
      ),
    },
  ];

  function ApplicationsPanel() {
    return (
      <div className='flex gap-4 min-h-[480px]'>
        <div className='w-64 shrink-0 flex flex-col gap-1 border-r border-divider pr-4'>
          <p className='text-xs font-semibold text-default-500 uppercase tracking-wide mb-2 px-1'>{`${items.length} jobs`}</p>
          {loading ? (
            <div className='flex justify-center pt-8'><Spinner size='sm' /></div>
          ) : items.length === 0 ? (
            <p className='text-sm text-default-400 px-1'>{"No jobs in this list"}</p>
          ) : (
            <div className='flex flex-col gap-1 overflow-y-auto max-h-[600px] pr-1'>
              {items.map((job) => {
                const isSelected = selectedJob?.id === job.id;
                return (
                  <Button key={job.id} onPress={() => handleSelectJob(job)}
                    variant="light"
                    className={['text-left rounded-lg px-3 py-2.5 w-full justify-start h-auto',
                      isSelected ? 'bg-primary-50 border border-primary-200 dark:bg-primary-950 dark:border-primary-800' : 'border border-transparent',
                    ].join(' ')} aria-pressed={isSelected}>
                    <div className="text-left w-full">
                      <p className={['text-sm font-medium leading-snug truncate',
                        isSelected ? 'text-primary-700 dark:text-primary-300' : 'text-foreground'].join(' ')}>{job.title}</p>
                      <div className='flex items-center gap-1.5 mt-1'>
                        <Users size={11} className='text-default-400 shrink-0' />
                        <span className='text-xs text-default-500'>{job.applications_count === 1 ? `${job.applications_count} application` : `${job.applications_count} applications`}</span>
                      </div>
                      {(job.organization_name || job.poster_name) && <p className='text-xs text-default-400 truncate mt-0.5'>{job.organization_name || job.poster_name}</p>}
                    </div>
                  </Button>
                );
              })}
            </div>
          )}
        </div>
        <div className='flex-1 min-w-0'>
          {!selectedJob ? (
            <div className='flex flex-col items-center justify-center h-64 text-center gap-3'>
              <div className='w-14 h-14 rounded-full bg-default-100 flex items-center justify-center'><ClipboardList size={24} className='text-default-400' /></div>
              <div>
                <p className='text-sm font-medium text-default-600'>{"Select a job"}</p>
                <p className='text-xs text-default-400 mt-1'>{"Choose a job from the list to view its applications"}</p>
              </div>
            </div>
          ) : (
            <div>
              <div className='flex items-start justify-between gap-3 mb-4'>
                <div className='min-w-0'>
                  <h3 className='font-semibold text-foreground truncate'>{selectedJob.title}</h3>
                  <p className='text-sm text-default-500 mt-0.5'>{selectedJob.organization_name || selectedJob.poster_name || "-"}{' '}&middot;{' '}<span className='capitalize'>{selectedJob.status}</span></p>
                </div>
                <Button size='sm' variant='flat' startContent={<RefreshCw size={13} />} onPress={() => loadApplications(selectedJob)} isDisabled={appsLoading}>{"Refresh"}</Button>
              </div>
              {appsLoading && <div className='flex justify-center py-12'><Spinner label={"Loading applications..."} /></div>}
              {!appsLoading && appsError && (
                <div className='flex flex-col items-center gap-3 py-12 text-center'>
                  <p className='text-sm text-danger'>{appsError}</p>
                  <Button size='sm' variant='flat' color='danger' onPress={() => loadApplications(selectedJob)}>{"Try Again"}</Button>
                </div>
              )}
              {!appsLoading && !appsError && applications.length === 0 && (
                <div className='flex flex-col items-center justify-center py-12 text-center gap-3'>
                  <div className='w-12 h-12 rounded-full bg-default-100 flex items-center justify-center'><CheckCircle2 size={20} className='text-default-400' /></div>
                  <div><p className='text-sm font-medium text-default-600'>{"No applications yet"}</p><p className='text-xs text-default-400 mt-1'>{"When candidates apply, they will appear here"}</p></div>
                </div>
              )}
              {!appsLoading && !appsError && applications.length > 0 && (
                <div>
                  <p className='text-xs text-default-500 mb-3'>{applications.length === 1 ? `${applications.length} application` : `${applications.length} applications`}</p>
                  <div className='overflow-y-auto max-h-[520px] pr-1'>
                    {applications.map((appl) => <ApplicationCard key={appl.id} application={appl} onStatusUpdate={handleStatusUpdate} />)}
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title={"Jobs"} description={"Manage job listings and applications"}
        actions={<Button variant='flat' startContent={<RefreshCw size={16} />} onPress={loadJobs}>{"Refresh"}</Button>}
      />
      <div className='grid grid-cols-2 md:grid-cols-4 gap-4 mb-6'>
        <StatCard label={"Total Jobs"} value={stats?.total_jobs ?? 0} icon={Briefcase} color='primary' loading={statsLoading} />
        <StatCard label={"Total Applications"} value={stats?.total_applications ?? 0} icon={Users} color='success' loading={statsLoading} />
        <StatCard label={"Conversion Rate"} value={`${stats?.conversion_rate ?? 0}%`} icon={TrendingUp} color='warning' loading={statsLoading} />
        <StatCard label={"Active Interviews"} value={stats?.active_interviews ?? 0} icon={Calendar} color='secondary' loading={statsLoading} description={stats?.pending_offers ? `${stats.pending_offers} pending offers` : undefined} />
      </div>
      <div className='mb-6'>
        <Tabs selectedKey={panelTab} onSelectionChange={(key) => setPanelTab(key as 'listings' | 'applications')}
          variant='underlined' size='md' aria-label={"Job listing panels"}>
          <Tab key='listings' title={<div className='flex items-center gap-2'><Briefcase size={15} /><span>{"Listings"}</span></div>} />
          <Tab key='applications' title={<div className='flex items-center gap-2'><Users size={15} /><span>{"Applications"}</span></div>} />
        </Tabs>
      </div>
      {panelTab === 'listings' && (
        <>
          <div className='mb-4'>
            <Tabs selectedKey={status} onSelectionChange={(_key) => { setStatus(_key as string); setPage(1); }}
              variant='underlined' size='sm' aria-label={"Filter by status"}>
              {STATUS_TAB_KEYS.map((key) => <Tab key={key} title={t(`jobs.status_${key}`)} />)}
            </Tabs>
          </div>
          <DataTable columns={columns} data={items} isLoading={loading}
            searchPlaceholder={"Search jobs..."}
            onSearch={(sq) => { setSearch(sq); setPage(1); }}
            onRefresh={loadJobs} totalItems={meta.total} page={page} pageSize={50} onPageChange={setPage}
            emptyContent={<EmptyState icon={Briefcase} title={"No jobs found"} description={search || status !== 'all' ? "Try adjusting your search or filters" : "No job listings have been posted yet"} />}
          />
        </>
      )}
      {panelTab === 'applications' && <ApplicationsPanel />}
      {confirmDelete && (
        <ConfirmModal isOpen={!!confirmDelete} onClose={() => setConfirmDelete(null)} onConfirm={handleDelete}
          title={"Delete Job"} message={`Are you sure you want to delete this job? This cannot be undone.`}
          confirmLabel={"Delete"} confirmColor='danger' isLoading={actionLoading} />
      )}
    </div>
  );
}

export default JobsAdmin;

