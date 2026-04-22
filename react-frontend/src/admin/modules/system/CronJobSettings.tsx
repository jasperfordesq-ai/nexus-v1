// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Cron Job Settings
 * Configure per-job and global cron settings
 * Parity: PHP CronJobController::settings()
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Input,
  Textarea,
  Switch,
  Select,
  SelectItem,
  Divider,
  Spinner,
} from '@heroui/react';
import { Settings, Save, AlertCircle, Info } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useAuth, useTenant, useToast } from '@/contexts';
import { Navigate } from 'react-router-dom';
import { adminCron, adminSystem } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { CronJob, CronJobSettings, GlobalCronSettings } from '../../api/types';

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function CronJobSettingsPage() {
  usePageTitle("Cron Job Settings");
  const toast = useToast();
  const { user } = useAuth();
  const { tenantPath } = useTenant();

  const userRecord = user as Record<string, unknown> | null;
  const isPlatformSuperAdmin =
    (user?.role as string) === 'super_admin' ||
    userRecord?.is_super_admin === true;

  const [jobs, setJobs] = useState<CronJob[]>([]);
  const [loadingJobs, setLoadingJobs] = useState(true);
  const [selectedJobId, setSelectedJobId] = useState<string>('');

  // Per-job settings
  const [jobSettings, setJobSettings] = useState<CronJobSettings>({
    job_id: '',
    is_enabled: true,
    custom_schedule: '',
    notify_on_failure: false,
    notify_emails: '',
    max_retries: 3,
    timeout_seconds: 300,
  });
  const [loadingJobSettings, setLoadingJobSettings] = useState(false);
  const [savingJobSettings, setSavingJobSettings] = useState(false);

  // Global settings
  const [globalSettings, setGlobalSettings] = useState<GlobalCronSettings>({
    default_notify_email: '',
    log_retention_days: 30,
    max_concurrent_jobs: 5,
  });
  const [loadingGlobalSettings, setLoadingGlobalSettings] = useState(true);
  const [savingGlobalSettings, setSavingGlobalSettings] = useState(false);

  // Load jobs list
  const loadJobs = useCallback(async () => {
    setLoadingJobs(true);
    try {
      const res = await adminSystem.getCronJobs();
      if (res.success && res.data) {
        setJobs(Array.isArray(res.data) ? res.data : []);
      }
    } catch {
      setJobs([]);
    }
    setLoadingJobs(false);
  }, []);

  // Load job-specific settings
  const loadJobSettings = useCallback(async (jobId: string) => {
    if (!jobId) return;
    setLoadingJobSettings(true);
    try {
      const res = await adminCron.getJobSettings(jobId);
      if (res.success && res.data) {
        setJobSettings(res.data);
      }
    } catch {
      toast.error("Failed to load job settings");
    }
    setLoadingJobSettings(false);
  }, [toast, t])

  // Load global settings
  const loadGlobalSettings = useCallback(async () => {
    setLoadingGlobalSettings(true);
    try {
      const res = await adminCron.getGlobalSettings();
      if (res.success && res.data) {
        setGlobalSettings(res.data);
      }
    } catch {
      toast.error("Failed to load global settings");
    }
    setLoadingGlobalSettings(false);
  }, [toast, t])

  // Save job settings
  const handleSaveJobSettings = async () => {
    if (!selectedJobId) return;
    setSavingJobSettings(true);
    try {
      const res = await adminCron.updateJobSettings(selectedJobId, jobSettings);
      if (res.success) {
        toast.success("Job settings saved successfully");
      } else {
        toast.error(res.error || "Failed to save job settings");
      }
    } catch {
      toast.error("Failed to save job settings");
    }
    setSavingJobSettings(false);
  };

  // Save global settings
  const handleSaveGlobalSettings = async () => {
    setSavingGlobalSettings(true);
    try {
      const res = await adminCron.updateGlobalSettings(globalSettings);
      if (res.success) {
        toast.success("Global settings saved successfully");
      } else {
        toast.error(res.error || "Failed to save global settings");
      }
    } catch {
      toast.error("Failed to save global settings");
    }
    setSavingGlobalSettings(false);
  };

  useEffect(() => {
    loadJobs();
    loadGlobalSettings();
  }, [loadJobs, loadGlobalSettings]);

  useEffect(() => {
    if (selectedJobId) {
      loadJobSettings(selectedJobId);
    }
  }, [selectedJobId, loadJobSettings]);

  if (!isPlatformSuperAdmin) {
    return <Navigate to={tenantPath('/admin')} replace />;
  }

  return (
    <div>
      <PageHeader
        title={"Cron Job Settings"}
        description={"Configure settings for background job execution"}
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Per-Job Settings */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2">
            <Settings size={18} className="text-primary" />
            <span className="text-lg font-semibold">{"Per Job Settings"}</span>
          </CardHeader>
          <CardBody className="space-y-4">
            {loadingJobs ? (
              <div className="flex items-center justify-center py-8">
                <Spinner size="sm" />
              </div>
            ) : (
              <>
                <Select
                  label={"Select Job"}
                  placeholder={"Choose a Job to Configure..."}
                  variant="bordered"
                  selectedKeys={selectedJobId ? [selectedJobId] : []}
                  onChange={(e) => setSelectedJobId(e.target.value)}
                >
                  {jobs.map((job) => (
                    <SelectItem key={job.slug}>
                      {job.name}
                    </SelectItem>
                  ))}
                </Select>

                {selectedJobId && (
                  <>
                    <Divider />

                    {loadingJobSettings ? (
                      <div className="flex items-center justify-center py-8">
                        <Spinner size="sm" />
                      </div>
                    ) : (
                      <div className="space-y-4">
                        <Switch
                          isSelected={jobSettings.is_enabled}
                          onValueChange={(value) =>
                            setJobSettings({ ...jobSettings, is_enabled: value })
                          }
                        >
                          <div className="flex flex-col gap-1">
                            <span className="text-sm font-medium">{"Enable Job"}</span>
                            <span className="text-xs text-default-400">
                              {"Job Will Run When Enabled."}
                            </span>
                          </div>
                        </Switch>

                        <Input
                          label={"Custom Schedule"}
                          placeholder={"Enter cron expression..."}
                          description={"Cron expression for custom schedule. Leave empty to use the default."}
                          variant="bordered"
                          value={jobSettings.custom_schedule || ''}
                          onChange={(e) =>
                            setJobSettings({
                              ...jobSettings,
                              custom_schedule: e.target.value,
                            })
                          }
                          startContent={
                            <Info size={16} className="text-default-400" />
                          }
                        />

                        <Switch
                          isSelected={jobSettings.notify_on_failure}
                          onValueChange={(value) =>
                            setJobSettings({
                              ...jobSettings,
                              notify_on_failure: value,
                            })
                          }
                        >
                          <div className="flex flex-col gap-1">
                            <span className="text-sm font-medium">
                              {"Notify on Failure"}
                            </span>
                            <span className="text-xs text-default-400">
                              {"Notify on Failure."}
                            </span>
                          </div>
                        </Switch>

                        {jobSettings.notify_on_failure && (
                          <Textarea
                            label={"Notification Emails"}
                            placeholder={"Enter notification emails..."}
                            description={"Comma Separated Emails."}
                            variant="bordered"
                            minRows={2}
                            value={jobSettings.notify_emails || ''}
                            onChange={(e) =>
                              setJobSettings({
                                ...jobSettings,
                                notify_emails: e.target.value,
                              })
                            }
                          />
                        )}

                        <Input
                          label={"Max Retries"}
                          type="number"
                          placeholder={"Enter max retries..."}
                          description={"Number of times to retry failed jobs before giving up"}
                          variant="bordered"
                          value={jobSettings.max_retries.toString()}
                          onChange={(e) =>
                            setJobSettings({
                              ...jobSettings,
                              max_retries: parseInt(e.target.value) || 0,
                            })
                          }
                        />

                        <Input
                          label={"Timeout"}
                          type="number"
                          placeholder={"Enter timeout seconds..."}
                          description={"Maximum time in seconds a job is allowed to run before being killed"}
                          variant="bordered"
                          value={jobSettings.timeout_seconds.toString()}
                          onChange={(e) =>
                            setJobSettings({
                              ...jobSettings,
                              timeout_seconds: parseInt(e.target.value) || 0,
                            })
                          }
                        />

                        <Button
                          color="primary"
                          startContent={<Save size={16} />}
                          onPress={handleSaveJobSettings}
                          isLoading={savingJobSettings}
                          className="w-full"
                        >
                          {"Save Job Settings"}
                        </Button>
                      </div>
                    )}
                  </>
                )}

                {!selectedJobId && (
                  <div className="flex flex-col items-center gap-2 py-8 text-default-400">
                    <AlertCircle size={32} />
                    <p className="text-sm">{"Select Job"}</p>
                  </div>
                )}
              </>
            )}
          </CardBody>
        </Card>

        {/* Global Settings */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2">
            <Settings size={18} className="text-secondary" />
            <span className="text-lg font-semibold">{"Global Settings"}</span>
          </CardHeader>
          <CardBody className="space-y-4">
            {loadingGlobalSettings ? (
              <div className="flex items-center justify-center py-8">
                <Spinner size="sm" />
              </div>
            ) : (
              <>
                <Input
                  label={"Default Notification Email"}
                  type="email"
                  placeholder={"Enter default notification email..."}
                  description={"Fallback email address for job failure notifications if no notification email is set"}
                  variant="bordered"
                  value={globalSettings.default_notify_email || ''}
                  onChange={(e) =>
                    setGlobalSettings({
                      ...globalSettings,
                      default_notify_email: e.target.value,
                    })
                  }
                />

                <Input
                  label={"Log Retention"}
                  type="number"
                  placeholder={"Enter log retention days..."}
                  description={"How long to keep job execution logs before automatic deletion"}
                  variant="bordered"
                  value={globalSettings.log_retention_days.toString()}
                  onChange={(e) =>
                    setGlobalSettings({
                      ...globalSettings,
                      log_retention_days: parseInt(e.target.value) || 0,
                    })
                  }
                />

                <Input
                  label={"Max Concurrent Jobs"}
                  type="number"
                  placeholder={"Enter max concurrent jobs..."}
                  description={"Maximum number of jobs that can run simultaneously"}
                  variant="bordered"
                  value={globalSettings.max_concurrent_jobs.toString()}
                  onChange={(e) =>
                    setGlobalSettings({
                      ...globalSettings,
                      max_concurrent_jobs: parseInt(e.target.value) || 0,
                    })
                  }
                />

                <Button
                  color="primary"
                  startContent={<Save size={16} />}
                  onPress={handleSaveGlobalSettings}
                  isLoading={savingGlobalSettings}
                  className="w-full"
                >
                  {"Save Global Settings"}
                </Button>
              </>
            )}
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export default CronJobSettingsPage;
