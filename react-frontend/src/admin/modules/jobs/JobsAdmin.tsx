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
import { Briefcase, Star, StarOff, Trash2, Eye, RefreshCw, ChevronDown, ChevronUp, Users, ClipboardList, CheckCircle2, Save } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { resolveAvatarUrl } from '@/lib/helpers';
import { PageHeader, DataTable, ConfirmModal, EmptyState, type Column } from '../../components';

interface Job { id: number; title: string; organization_name?: string; poster_name?: string; type?: string; applications_count: number; views_count: number; is_featured: boolean; status: string; deadline?: string; created_at: string; }
interface JobsMeta { page: number; per_page: number; total: number; total_pages: number; }
interface Applicant { id: number; name: string; avatar_url?: string; email?: string; }
interface Application { id: number; vacancy_id: number; user_id: number; applicant: Applicant; status: string; message?: string; reviewer_notes?: string; created_at: string; updated_at?: string; }

const STATUS_TABS = [{ key: 'all', label: 'All' }, { key: 'open', label: 'Open' }, { key: 'closed', label: 'Closed' }, { key: 'expired', label: 'Expired' }] as const;
const statusColorMap: Record<string, 'success' | 'default' | 'warning'> = { open: 'success', closed: 'default', expired: 'warning' };
const typeLabel: Record<string, string> = { paid: 'Paid', volunteer: 'Volunteer', timebank: 'Timebank' };
const appStatusColor: Record<string, 'default' | 'primary' | 'warning' | 'secondary' | 'success' | 'danger'> = { applied: 'default', screening: 'primary', reviewed: 'primary', pending: 'default', interview: 'warning', offer: 'secondary', accepted: 'success', rejected: 'danger', withdrawn: 'default' };
const APPLICATION_STAGES = [{ key: 'applied', label: 'Applied' }, { key: 'screening', label: 'Screening' }, { key: 'interview', label: 'Interview' }, { key: 'offer', label: 'Offer' }, { key: 'accepted', label: 'Accepted' }, { key: 'rejected', label: 'Rejected' }, { key: 'withdrawn', label: 'Withdrawn' }] as const;

interface ApplicationCardProps { application: Application; onStatusUpdate: (appId: number, status: string, notes: string) => Promise<void>; }

function ApplicationCard({ application, onStatusUpdate }: ApplicationCardProps) {
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
          <p className='text-xs text-default-400 mt-0.5'>Applied{' '}{new Date(application.created_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })}</p>
        </div>
      </div>
      {application.message && (<div><Button variant="light" size="sm" className='flex items-center gap-1 text-xs text-default-500 hover:text-default-700 h-auto p-0' onPress={() => setExpanded((v) => !v)} aria-expanded={expanded}>
        {expanded ? <ChevronUp size={12} /> : <ChevronDown size={12} />} Cover message</Button>
        {expanded && <p className='mt-2 text-sm text-default-700 bg-default-50 rounded-lg p-3 whitespace-pre-wrap leading-relaxed'>{application.message}</p>}
      </div>)}
      <div className='flex items-end gap-2 flex-wrap'>
        <div className='flex-1 min-w-[160px]'>
          <Select size='sm' label='Status' selectedKeys={new Set([selectedStatus])} onSelectionChange={(keys) => { const val = Array.from(keys)[0] as string; if (val) setSelectedStatus(val); }} aria-label='Update application status' classNames={{ trigger: 'min-h-unit-10 h-10' }}>
            {APPLICATION_STAGES.map((stage) => <SelectItem key={stage.key}>{stage.label}</SelectItem>)}
          </Select>
        </div>
        <Tooltip content={notesOpen ? 'Hide notes' : 'Add / edit notes'}>
          <Button isIconOnly size='sm' variant='flat' onPress={() => setNotesOpen((v) => !v)} aria-label='Toggle notes'><ClipboardList size={14} /></Button>
        </Tooltip>
        <Button size='sm' color={isDirty ? 'primary' : 'default'} variant={isDirty ? 'solid' : 'flat'} isDisabled={!isDirty || saving} isLoading={saving} startContent={!saving && <Save size={13} />} onPress={handleSave}>Update</Button>
      </div>
      {notesOpen && <Textarea size='sm' label='Internal notes' placeholder='Notes visible only to admins…' value={notes} onValueChange={setNotes} minRows={2} maxRows={6} classNames={{ inputWrapper: 'bg-default-50' }} />}
    </CardBody></Card>
  );
}

export function JobsAdmin() {
  usePageTitle('Admin - Jobs');
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

  const loadJobs = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ page: String(page), limit: '50' });
      if (search) params.set('search', search);
      if (status !== 'all') params.set('status', status);
      const res = await api.get<Job[]>(`/v2/admin/jobs?${params.toString()}`);
      if (res.success) {
        setItems(Array.isArray(res.data) ? res.data : []);
        const paginationMeta = res.meta as unknown as JobsMeta | undefined;
        setMeta(paginationMeta ?? { page: 1, per_page: 50, total: 0, total_pages: 1 });
      }
    } catch { toast.error('Failed to load jobs'); }
    finally { setLoading(false); }
  }, [page, status, search, toast]);

  useEffect(() => { loadJobs(); }, [loadJobs]);

  const loadApplications = useCallback(async (job: Job) => {
    setAppsLoading(true); setAppsError(null); setApplications([]);
    try {
      const res = await api.get<Application[]>(`/v2/admin/jobs/${job.id}/applications`);
      if (res.success) { setApplications(Array.isArray(res.data) ? res.data : []); }
      else { setAppsError((res as { error?: string }).error ?? 'Failed to load applications'); }
    } catch { setAppsError('Failed to load applications'); }
    finally { setAppsLoading(false); }
  }, []);

  const handleSelectJob = useCallback((job: Job) => { setSelectedJob(job); loadApplications(job); }, [loadApplications]);

  const handleStatusUpdate = useCallback(async (appId: number, newStatus: string, notes: string) => {
    try {
      const res = await api.put(`/v2/admin/jobs/applications/${appId}`, { status: newStatus, notes });
      if (res && res.success) {
        toast.success('Application updated');
        setApplications((prev) => prev.map((appl) => appl.id === appId ? { ...appl, status: newStatus, reviewer_notes: notes } : appl));
        loadJobs();
      } else { toast.error((res as { error?: string }).error ?? 'Failed to update application'); }
    } catch { toast.error('An unexpected error occurred'); }
  }, [toast, loadJobs]);

  const handleFeatureToggle = async (job: Job) => {
    try {
      const endpoint = job.is_featured ? `/v2/admin/jobs/${job.id}/unfeature` : `/v2/admin/jobs/${job.id}/feature`;
      const res = await api.post(endpoint);
      if (res && res.success) {
        toast.success(job.is_featured ? `"${job.title}" removed from featured` : `"${job.title}" is now featured`);
        loadJobs();
      } else { toast.error((res && (res as { error?: string }).error) || 'Failed to update featured status'); }
    } catch { toast.error('An unexpected error occurred'); }
  };

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);
    try {
      const res = await api.delete(`/v2/admin/jobs/${confirmDelete.id}`);
      if (res && res.success) { toast.success('Job deleted successfully'); loadJobs(); }
      else { toast.error((res && (res as { error?: string }).error) || 'Failed to delete job'); }
    } catch { toast.error('An unexpected error occurred'); }
    finally { setActionLoading(false); setConfirmDelete(null); }
  };

  const columns: Column<Job>[] = [
    { key: 'title', label: 'Title', sortable: true, render: (item) => <span className='font-medium text-foreground'>{item.title}</span> },
    { key: 'organization_name', label: 'Organization / Poster', sortable: true, render: (item) => <span className='text-sm text-default-600'>{item.organization_name || item.poster_name || '--'}</span> },
    { key: 'type', label: 'Type', sortable: true, render: (item) => <span className='text-sm text-default-600'>{typeLabel[item.type ?? ''] ?? item.type ?? '--'}</span> },
    { key: 'applications_count', label: 'Applications', sortable: true, render: (item) => <span className='text-sm text-default-600'>{item.applications_count}</span> },
    { key: 'views_count', label: 'Views', sortable: true, render: (item) => <span className='text-sm text-default-500'>{item.views_count}</span> },
    {
      key: 'is_featured', label: 'Featured',
      render: (item) => (
        <Tooltip content={item.is_featured ? 'Featured' : 'Not featured'}>
          <Button isIconOnly size='sm' variant='light' onPress={() => handleFeatureToggle(item)} aria-label={item.is_featured ? 'Unfeature job' : 'Feature job'}>
            {item.is_featured ? <Star size={16} className='text-warning fill-warning' /> : <StarOff size={16} className='text-default-400' />}
          </Button>
        </Tooltip>
      ),
    },
    { key: 'status', label: 'Status', sortable: true, render: (item) => <Chip size='sm' variant='flat' color={statusColorMap[item.status] || 'default'} className='capitalize'>{item.status}</Chip> },
    { key: 'deadline', label: 'Deadline', sortable: true, render: (item) => <span className='text-sm text-default-500'>{item.deadline ? new Date(item.deadline).toLocaleDateString() : '--'}</span> },
    {
      key: 'actions', label: 'Actions',
      render: (item) => (
        <div className='flex gap-1'>
          <Tooltip content='View job'>
            <Button isIconOnly size='sm' variant='flat' color='primary' as='a' href={tenantPath(`/jobs/${item.id}`)} target='_blank' rel='noopener noreferrer' aria-label='View job'><Eye size={14} /></Button>
          </Tooltip>
          <Tooltip content={item.is_featured ? 'Unfeature' : 'Feature'}>
            <Button isIconOnly size='sm' variant='flat' color='warning' onPress={() => handleFeatureToggle(item)} aria-label={item.is_featured ? 'Unfeature job' : 'Feature job'}>
              {item.is_featured ? <StarOff size={14} /> : <Star size={14} />}
            </Button>
          </Tooltip>
          <Tooltip content='Delete'>
            <Button isIconOnly size='sm' variant='flat' color='danger' onPress={() => setConfirmDelete(item)} aria-label='Delete job'><Trash2 size={14} /></Button>
          </Tooltip>
        </div>
      ),
    },
  ];

  function ApplicationsPanel() {
    return (
      <div className='flex gap-4 min-h-[480px]'>
        <div className='w-64 shrink-0 flex flex-col gap-1 border-r border-divider pr-4'>
          <p className='text-xs font-semibold text-default-500 uppercase tracking-wide mb-2 px-1'>Jobs ({items.length})</p>
          {loading ? (
            <div className='flex justify-center pt-8'><Spinner size='sm' /></div>
          ) : items.length === 0 ? (
            <p className='text-sm text-default-400 px-1'>No jobs found.</p>
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
                        <span className='text-xs text-default-500'>{job.applications_count}{' '}{job.applications_count === 1 ? 'application' : 'applications'}</span>
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
                <p className='text-sm font-medium text-default-600'>Select a job</p>
                <p className='text-xs text-default-400 mt-1'>Choose a job from the left to review its applications.</p>
              </div>
            </div>
          ) : (
            <div>
              <div className='flex items-start justify-between gap-3 mb-4'>
                <div className='min-w-0'>
                  <h3 className='font-semibold text-foreground truncate'>{selectedJob.title}</h3>
                  <p className='text-sm text-default-500 mt-0.5'>{selectedJob.organization_name || selectedJob.poster_name || 'No organization'}{' '}&middot;{' '}<span className='capitalize'>{selectedJob.status}</span></p>
                </div>
                <Button size='sm' variant='flat' startContent={<RefreshCw size={13} />} onPress={() => loadApplications(selectedJob)} isDisabled={appsLoading}>Refresh</Button>
              </div>
              {appsLoading && <div className='flex justify-center py-12'><Spinner label='Loading applications…' /></div>}
              {!appsLoading && appsError && (
                <div className='flex flex-col items-center gap-3 py-12 text-center'>
                  <p className='text-sm text-danger'>{appsError}</p>
                  <Button size='sm' variant='flat' color='danger' onPress={() => loadApplications(selectedJob)}>Try again</Button>
                </div>
              )}
              {!appsLoading && !appsError && applications.length === 0 && (
                <div className='flex flex-col items-center justify-center py-12 text-center gap-3'>
                  <div className='w-12 h-12 rounded-full bg-default-100 flex items-center justify-center'><CheckCircle2 size={20} className='text-default-400' /></div>
                  <div><p className='text-sm font-medium text-default-600'>No applications yet</p><p className='text-xs text-default-400 mt-1'>Applications for this job will appear here.</p></div>
                </div>
              )}
              {!appsLoading && !appsError && applications.length > 0 && (
                <div>
                  <p className='text-xs text-default-500 mb-3'>{applications.length}{' '}{applications.length === 1 ? 'application' : 'applications'}</p>
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
      <PageHeader title='Job Vacancies' description='Manage job listings, featured jobs, and applications'
        actions={<Button variant='flat' startContent={<RefreshCw size={16} />} onPress={loadJobs}>Refresh</Button>}
      />
      <div className='mb-6'>
        <Tabs selectedKey={panelTab} onSelectionChange={(key) => setPanelTab(key as 'listings' | 'applications')}
          variant='underlined' size='md' aria-label='Jobs admin panels'>
          <Tab key='listings' title={<div className='flex items-center gap-2'><Briefcase size={15} /><span>Job Listings</span></div>} />
          <Tab key='applications' title={<div className='flex items-center gap-2'><Users size={15} /><span>Applications</span></div>} />
        </Tabs>
      </div>
      {panelTab === 'listings' && (
        <>
          <div className='mb-4'>
            <Tabs selectedKey={status} onSelectionChange={(_key) => { setStatus(_key as string); setPage(1); }}
              variant='underlined' size='sm' aria-label='Filter jobs by status'>
              {STATUS_TABS.map((tab) => <Tab key={tab.key} title={tab.label} />)}
            </Tabs>
          </div>
          <DataTable columns={columns} data={items} isLoading={loading}
            searchPlaceholder='Search jobs by title or organization...'
            onSearch={(sq) => { setSearch(sq); setPage(1); }}
            onRefresh={loadJobs} totalItems={meta.total} page={page} pageSize={50} onPageChange={setPage}
            emptyContent={<EmptyState icon={Briefcase} title='No jobs found' description={search || status !== 'all' ? 'Try adjusting your search or filters' : 'No job vacancies have been posted yet'} />}
          />
        </>
      )}
      {panelTab === 'applications' && <ApplicationsPanel />}
      {confirmDelete && (
        <ConfirmModal isOpen={!!confirmDelete} onClose={() => setConfirmDelete(null)} onConfirm={handleDelete}
          title='Delete Job' message={`Are you sure you want to delete "${confirmDelete.title}"? This action cannot be undone.`}
          confirmLabel='Delete' confirmColor='danger' isLoading={actionLoading} />
      )}
    </div>
  );
}

export default JobsAdmin;

