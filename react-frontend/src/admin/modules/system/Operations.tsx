// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Operations
 *
 * Operational tools (not config): cache statistics and background jobs.
 * Previously lived inside /admin/tenant-features (now retired).
 */

import { useState, useCallback, useEffect } from 'react';
import { Card, CardBody, CardHeader, Spinner, Button, Divider } from '@heroui/react';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Trash2 from 'lucide-react/icons/trash-2';
import Database from 'lucide-react/icons/database';
import Timer from 'lucide-react/icons/timer';
import Play from 'lucide-react/icons/play';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminConfig } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { CacheStats, BackgroundJob } from '../../api/types';

export default function Operations() {
  usePageTitle('Operations');
  const toast = useToast();

  const [cacheStats, setCacheStats] = useState<CacheStats | null>(null);
  const [jobs, setJobs] = useState<BackgroundJob[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    const [cacheRes, jobsRes] = await Promise.all([
      adminConfig.getCacheStats(),
      adminConfig.getJobs(),
    ]);
    if (cacheRes.success && cacheRes.data) setCacheStats(cacheRes.data);
    if (jobsRes.success && jobsRes.data) {
      setJobs(Array.isArray(jobsRes.data) ? jobsRes.data : []);
    }
    setLoading(false);
  }, []);

  useEffect(() => { load(); }, [load]);

  const handleClearCache = async () => {
    const res = await adminConfig.clearCache('tenant');
    if (res.success) {
      toast.success('Cache cleared successfully');
      const statsRes = await adminConfig.getCacheStats();
      if (statsRes.success && statsRes.data) setCacheStats(statsRes.data);
    } else {
      toast.error('Failed to clear cache');
    }
  };

  const handleRunJob = async (jobId: string) => {
    const res = await adminConfig.runJob(jobId);
    if (res.success) {
      toast.success('Job triggered successfully');
    } else {
      toast.error('Failed to trigger job');
    }
  };

  if (loading) {
    return (
      <div className="flex h-64 items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="Operations"
        description="Cache statistics and background job controls."
        actions={
          <Button variant="flat" size="sm" startContent={<RefreshCw size={16} />} onPress={load}>
            Refresh
          </Button>
        }
      />

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <Database size={18} className="text-warning" />
            <h3 className="font-semibold">Cache</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4 space-y-3">
            <div className="flex justify-between text-sm">
              <span className="text-default-500">Redis</span>
              <span className={cacheStats?.redis_connected ? 'text-success' : 'text-danger'}>
                {cacheStats?.redis_connected ? 'Connected' : 'Disconnected'}
              </span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-default-500">Memory used</span>
              <span>{cacheStats?.redis_memory_used || '—'}</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-default-500">Keys</span>
              <span>{cacheStats?.redis_keys_count ?? '—'}</span>
            </div>
            <Divider />
            <Button
              fullWidth
              variant="flat"
              color="warning"
              startContent={<Trash2 size={14} />}
              onPress={handleClearCache}
              size="sm"
            >
              Clear tenant cache
            </Button>
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <Timer size={18} className="text-secondary" />
            <h3 className="font-semibold">Background jobs</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4 space-y-3">
            {jobs.length > 0 ? jobs.map((job) => (
              <div key={job.id} className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium">{job.name}</p>
                  <p className="text-xs text-default-400">
                    {job.last_run_at ? `Last run: ${new Date(job.last_run_at).toLocaleString()}` : 'Never run'}
                  </p>
                </div>
                <Button
                  isIconOnly
                  size="sm"
                  variant="flat"
                  onPress={() => handleRunJob(job.id)}
                  aria-label={`Run ${job.name}`}
                >
                  <Play size={14} />
                </Button>
              </div>
            )) : (
              <p className="text-sm text-default-400">No background jobs configured</p>
            )}
          </CardBody>
        </Card>
      </div>
    </div>
  );
}
