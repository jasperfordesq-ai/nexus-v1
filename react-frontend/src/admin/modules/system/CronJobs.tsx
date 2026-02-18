/**
 * Admin Cron Jobs
 * Card-based view of all scheduled tasks with manual trigger and status tracking.
 * Parity: PHP CronJobController::index()
 */

import { useState, useCallback, useEffect } from 'react';
import { Card, CardBody, CardHeader, CardFooter, Button, Chip, Spinner, Divider } from '@heroui/react';
import { Clock, Play, RefreshCw, CheckCircle, XCircle, Terminal, Calendar, Tag, AlertTriangle, Activity } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminSystem, adminCron } from '../../api/adminApi';
import { PageHeader, StatusBadge } from '../../components';
import type { CronJob, CronHealthMetrics } from '../../api/types';

// ─────────────────────────────────────────────────────────────────────────────
// Extended type to include extra fields from the API
// ─────────────────────────────────────────────────────────────────────────────

interface CronJobExtended extends CronJob {
  slug?: string;
  category?: string;
  description?: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Category colour & label mapping
// ─────────────────────────────────────────────────────────────────────────────

const categoryColorMap: Record<string, 'primary' | 'secondary' | 'success' | 'warning' | 'danger' | 'default'> = {
  notifications: 'primary',
  newsletters: 'secondary',
  matching: 'success',
  geocoding: 'warning',
  maintenance: 'default',
  master: 'danger',
  gamification: 'secondary',
  groups: 'primary',
  security: 'danger',
};

// ─────────────────────────────────────────────────────────────────────────────
// Date formatter
// ─────────────────────────────────────────────────────────────────────────────

function formatDate(dateStr: string | null): string {
  if (!dateStr) return 'Never';
  const d = new Date(dateStr);
  return d.toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function timeAgo(dateStr: string | null): string {
  if (!dateStr) return 'Never';
  const d = new Date(dateStr);
  const now = new Date();
  const diffMs = now.getTime() - d.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  const diffHours = Math.floor(diffMins / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  const diffDays = Math.floor(diffHours / 24);
  return `${diffDays}d ago`;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function CronJobs() {
  usePageTitle('Admin - Cron Jobs');
  const toast = useToast();
  const [jobs, setJobs] = useState<CronJobExtended[]>([]);
  const [loading, setLoading] = useState(true);
  const [runningJob, setRunningJob] = useState<number | null>(null);
  const [healthMetrics, setHealthMetrics] = useState<CronHealthMetrics | null>(null);
  const [loadingHealth, setLoadingHealth] = useState(true);

  const loadJobs = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminSystem.getCronJobs();
      if (res.success && res.data) {
        setJobs(Array.isArray(res.data) ? res.data : []);
      }
    } catch {
      setJobs([]);
    }
    setLoading(false);
  }, []);

  const loadHealthMetrics = useCallback(async () => {
    setLoadingHealth(true);
    try {
      const res = await adminCron.getHealthMetrics();
      if (res.success && res.data) {
        setHealthMetrics(res.data);
      }
    } catch {
      setHealthMetrics(null);
    }
    setLoadingHealth(false);
  }, []);

  const handleRunJob = async (id: number, jobName: string) => {
    setRunningJob(id);
    try {
      const res = await adminSystem.runCronJob(id);
      if (res.success) {
        toast.success(`"${jobName}" triggered successfully`);
        loadJobs(); // Refresh to get updated status
      } else {
        toast.error(res.error || `Failed to run "${jobName}"`);
      }
    } catch {
      toast.error(`Failed to run "${jobName}"`);
    }
    setRunningJob(null);
  };

  useEffect(() => {
    loadJobs();
    loadHealthMetrics();
  }, [loadJobs, loadHealthMetrics]);

  // Group jobs by category
  const jobsByCategory = jobs.reduce<Record<string, CronJobExtended[]>>((acc, job) => {
    const cat = job.category || 'other';
    if (!acc[cat]) acc[cat] = [];
    acc[cat].push(job);
    return acc;
  }, {});

  // Summary stats
  const totalJobs = jobs.length;
  const activeJobs = jobs.filter((j) => j.status === 'active').length;
  const recentSuccesses = jobs.filter((j) => j.last_status === 'success').length;
  const recentFailures = jobs.filter((j) => j.last_status === 'failed').length;

  return (
    <div>
      <PageHeader
        title="Cron Jobs"
        description="Scheduled task management"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadJobs}
            isLoading={loading}
            size="sm"
          >
            Refresh
          </Button>
        }
      />

      {/* Loading state */}
      {loading && jobs.length === 0 && (
        <div className="flex items-center justify-center py-20">
          <Spinner size="lg" label="Loading cron jobs..." />
        </div>
      )}

      {/* Health Metrics Section */}
      {!loadingHealth && healthMetrics && (
        <div className="mb-6">
          {/* Alert Banner for Critical Status */}
          {healthMetrics.alert_status === 'critical' && (
            <Card shadow="sm" className="mb-4 border-2 border-danger">
              <CardBody className="flex flex-row items-center gap-3 p-4 bg-danger/10">
                <AlertTriangle size={24} className="text-danger shrink-0" />
                <div>
                  <p className="font-semibold text-danger">Critical: Cron Jobs Failing</p>
                  <p className="text-sm text-danger-700 dark:text-danger-300">
                    {healthMetrics.jobs_failed_24h} jobs failed in the last 24 hours. Immediate attention required.
                  </p>
                </div>
              </CardBody>
            </Card>
          )}

          <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            {/* Health Score Card */}
            <Card shadow="sm">
              <CardHeader className="flex items-center gap-2 pb-2">
                <Activity size={16} className="text-default-500" />
                <span className="text-sm font-medium">Health Score</span>
              </CardHeader>
              <CardBody className="pt-0">
                <div className="flex items-end gap-2">
                  <span className={`text-4xl font-bold ${
                    healthMetrics.health_score >= 80 ? 'text-success' :
                    healthMetrics.health_score >= 50 ? 'text-warning' :
                    'text-danger'
                  }`}>
                    {healthMetrics.health_score}
                  </span>
                  <span className="text-sm text-default-500 mb-1">/100</span>
                </div>
                <Chip
                  size="sm"
                  variant="flat"
                  color={
                    healthMetrics.alert_status === 'healthy' ? 'success' :
                    healthMetrics.alert_status === 'warning' ? 'warning' :
                    'danger'
                  }
                  className="mt-2"
                >
                  {healthMetrics.alert_status}
                </Chip>
              </CardBody>
            </Card>

            {/* Success Rate Card */}
            <Card shadow="sm">
              <CardHeader className="flex items-center gap-2 pb-2">
                <CheckCircle size={16} className="text-success" />
                <span className="text-sm font-medium">7-Day Success Rate</span>
              </CardHeader>
              <CardBody className="pt-0">
                <div className="flex items-end gap-2">
                  <span className="text-4xl font-bold text-success">
                    {Math.round(healthMetrics.avg_success_rate_7d * 100)}
                  </span>
                  <span className="text-sm text-default-500 mb-1">%</span>
                </div>
                <p className="text-xs text-default-400 mt-2">
                  Average across all jobs
                </p>
              </CardBody>
            </Card>

            {/* Recent Failures Card */}
            <Card shadow="sm">
              <CardHeader className="flex items-center gap-2 pb-2">
                <XCircle size={16} className="text-danger" />
                <span className="text-sm font-medium">24h Failures</span>
              </CardHeader>
              <CardBody className="pt-0">
                <div className="flex items-end gap-2">
                  <span className={`text-4xl font-bold ${
                    healthMetrics.jobs_failed_24h === 0 ? 'text-success' :
                    healthMetrics.jobs_failed_24h < 5 ? 'text-warning' :
                    'text-danger'
                  }`}>
                    {healthMetrics.jobs_failed_24h}
                  </span>
                </div>
                <p className="text-xs text-default-400 mt-2">
                  Jobs failed in last 24 hours
                </p>
              </CardBody>
            </Card>
          </div>

          {/* Recent Failures List */}
          {healthMetrics.recent_failures.length > 0 && (
            <Card shadow="sm" className="mt-4">
              <CardHeader>
                <span className="text-sm font-semibold">Recent Failures</span>
              </CardHeader>
              <CardBody className="p-0">
                <div className="divide-y divide-divider">
                  {healthMetrics.recent_failures.slice(0, 5).map((failure, idx) => (
                    <div key={idx} className="px-4 py-3">
                      <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0 flex-1">
                          <p className="text-sm font-medium truncate">{failure.job_name}</p>
                          <p className="text-xs text-default-500 line-clamp-1">
                            {failure.reason}
                          </p>
                        </div>
                        <span className="text-xs text-default-400 shrink-0">
                          {new Date(failure.failed_at).toLocaleDateString()}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              </CardBody>
            </Card>
          )}

          {/* Overdue Jobs */}
          {healthMetrics.jobs_overdue.length > 0 && (
            <Card shadow="sm" className="mt-4 border border-warning">
              <CardHeader className="flex items-center gap-2 bg-warning/10">
                <AlertTriangle size={16} className="text-warning" />
                <span className="text-sm font-semibold">Overdue Jobs</span>
              </CardHeader>
              <CardBody className="p-0">
                <div className="divide-y divide-divider">
                  {healthMetrics.jobs_overdue.map((job, idx) => (
                    <div key={idx} className="px-4 py-3">
                      <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0 flex-1">
                          <p className="text-sm font-medium truncate">{job.job_name}</p>
                          <p className="text-xs text-default-500">
                            Expected: {job.expected_interval} • Last run: {job.last_run || 'Never'}
                          </p>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </CardBody>
            </Card>
          )}
        </div>
      )}

      {/* Summary stats */}
      {!loading && jobs.length > 0 && (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-6">
          <Card shadow="sm">
            <CardBody className="flex flex-row items-center gap-3 p-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                <Clock size={20} className="text-primary" />
              </div>
              <div>
                <p className="text-xs text-default-500">Total Jobs</p>
                <p className="text-xl font-bold">{totalJobs}</p>
              </div>
            </CardBody>
          </Card>
          <Card shadow="sm">
            <CardBody className="flex flex-row items-center gap-3 p-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-success/10">
                <CheckCircle size={20} className="text-success" />
              </div>
              <div>
                <p className="text-xs text-default-500">Active</p>
                <p className="text-xl font-bold">{activeJobs}</p>
              </div>
            </CardBody>
          </Card>
          <Card shadow="sm">
            <CardBody className="flex flex-row items-center gap-3 p-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-success/10">
                <CheckCircle size={20} className="text-success" />
              </div>
              <div>
                <p className="text-xs text-default-500">Last Succeeded</p>
                <p className="text-xl font-bold">{recentSuccesses}</p>
              </div>
            </CardBody>
          </Card>
          <Card shadow="sm">
            <CardBody className="flex flex-row items-center gap-3 p-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-danger/10">
                <XCircle size={20} className="text-danger" />
              </div>
              <div>
                <p className="text-xs text-default-500">Last Failed</p>
                <p className="text-xl font-bold">{recentFailures}</p>
              </div>
            </CardBody>
          </Card>
        </div>
      )}

      {/* Empty state */}
      {!loading && jobs.length === 0 && (
        <Card shadow="sm">
          <CardBody className="flex flex-col items-center gap-3 py-16 text-default-400">
            <Clock size={48} />
            <p className="text-lg font-medium">No cron jobs found</p>
            <p className="text-sm">The cron job system may not be configured yet.</p>
          </CardBody>
        </Card>
      )}

      {/* Jobs grouped by category */}
      {!loading &&
        Object.entries(jobsByCategory).map(([category, categoryJobs]) => (
          <div key={category} className="mb-8">
            <div className="mb-3 flex items-center gap-2">
              <Chip
                size="sm"
                variant="flat"
                color={categoryColorMap[category] || 'default'}
                className="capitalize"
              >
                {category}
              </Chip>
              <span className="text-sm text-default-400">
                {categoryJobs.length} job{categoryJobs.length !== 1 ? 's' : ''}
              </span>
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
              {categoryJobs.map((job) => (
                <Card key={job.id} shadow="sm" className="border border-divider">
                  {/* Header: Name + Status */}
                  <CardHeader className="flex items-start justify-between gap-2 px-4 pt-4 pb-0">
                    <div className="min-w-0 flex-1">
                      <h3 className="font-semibold text-foreground truncate">{job.name}</h3>
                      {job.description && (
                        <p className="mt-0.5 text-xs text-default-400 line-clamp-2">
                          {job.description}
                        </p>
                      )}
                    </div>
                    <StatusBadge status={job.status} />
                  </CardHeader>

                  {/* Body: Details */}
                  <CardBody className="px-4 py-3 gap-2.5">
                    {/* Command */}
                    <div className="flex items-start gap-2">
                      <Terminal size={14} className="text-default-400 mt-0.5 shrink-0" />
                      <code className="text-xs text-default-600 bg-default-100 px-2 py-1 rounded break-all">
                        {job.command}
                      </code>
                    </div>

                    {/* Schedule */}
                    <div className="flex items-center gap-2">
                      <Clock size={14} className="text-default-400 shrink-0" />
                      <span className="text-xs text-default-600">
                        Schedule: <code className="bg-default-100 px-1.5 py-0.5 rounded">{job.schedule}</code>
                      </span>
                    </div>

                    <Divider className="my-1" />

                    {/* Last Run */}
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <Calendar size={14} className="text-default-400 shrink-0" />
                        <span className="text-xs text-default-500">Last run:</span>
                      </div>
                      <div className="flex items-center gap-1.5">
                        {job.last_status === 'success' && (
                          <CheckCircle size={12} className="text-success" />
                        )}
                        {job.last_status === 'failed' && (
                          <XCircle size={12} className="text-danger" />
                        )}
                        <span className="text-xs text-default-600" title={formatDate(job.last_run_at)}>
                          {timeAgo(job.last_run_at)}
                        </span>
                      </div>
                    </div>

                    {/* Last Status */}
                    {job.last_status && (
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <Tag size={14} className="text-default-400 shrink-0" />
                          <span className="text-xs text-default-500">Last status:</span>
                        </div>
                        <StatusBadge status={job.last_status} />
                      </div>
                    )}

                    {/* Next Run */}
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <Clock size={14} className="text-default-400 shrink-0" />
                        <span className="text-xs text-default-500">Next run:</span>
                      </div>
                      <span className="text-xs text-default-600">
                        {job.next_run_at ? formatDate(job.next_run_at) : 'Not scheduled'}
                      </span>
                    </div>
                  </CardBody>

                  {/* Footer: Run Now */}
                  <CardFooter className="px-4 pb-4 pt-0">
                    <Button
                      size="sm"
                      color="primary"
                      variant="flat"
                      className="w-full"
                      startContent={
                        runningJob === job.id ? undefined : <Play size={14} />
                      }
                      isLoading={runningJob === job.id}
                      isDisabled={job.status === 'disabled' || runningJob !== null}
                      onPress={() => handleRunJob(job.id, job.name)}
                    >
                      {runningJob === job.id ? 'Running...' : 'Run Now'}
                    </Button>
                  </CardFooter>
                </Card>
              ))}
            </div>
          </div>
        ))}
    </div>
  );
}

export default CronJobs;
