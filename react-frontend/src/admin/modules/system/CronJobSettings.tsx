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
import { useToast } from '@/contexts';
import { adminCron, adminSystem } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { CronJob, CronJobSettings, GlobalCronSettings } from '../../api/types';

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function CronJobSettingsPage() {
  usePageTitle('Admin - Cron Job Settings');
  const toast = useToast();

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
      toast.error('Failed to load job settings');
    }
    setLoadingJobSettings(false);
  }, [toast]);

  // Load global settings
  const loadGlobalSettings = useCallback(async () => {
    setLoadingGlobalSettings(true);
    try {
      const res = await adminCron.getGlobalSettings();
      if (res.success && res.data) {
        setGlobalSettings(res.data);
      }
    } catch {
      toast.error('Failed to load global settings');
    }
    setLoadingGlobalSettings(false);
  }, [toast]);

  // Save job settings
  const handleSaveJobSettings = async () => {
    if (!selectedJobId) return;
    setSavingJobSettings(true);
    try {
      const res = await adminCron.updateJobSettings(selectedJobId, jobSettings);
      if (res.success) {
        toast.success('Job settings saved successfully');
      } else {
        toast.error(res.error || 'Failed to save job settings');
      }
    } catch {
      toast.error('Failed to save job settings');
    }
    setSavingJobSettings(false);
  };

  // Save global settings
  const handleSaveGlobalSettings = async () => {
    setSavingGlobalSettings(true);
    try {
      const res = await adminCron.updateGlobalSettings(globalSettings);
      if (res.success) {
        toast.success('Global settings saved successfully');
      } else {
        toast.error(res.error || 'Failed to save global settings');
      }
    } catch {
      toast.error('Failed to save global settings');
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

  return (
    <div>
      <PageHeader
        title="Cron Job Settings"
        description="Configure per-job and global cron settings"
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Per-Job Settings */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2">
            <Settings size={18} className="text-primary" />
            <span className="text-lg font-semibold">Per-Job Settings</span>
          </CardHeader>
          <CardBody className="space-y-4">
            {loadingJobs ? (
              <div className="flex items-center justify-center py-8">
                <Spinner size="sm" />
              </div>
            ) : (
              <>
                <Select
                  label="Select Job"
                  placeholder="Choose a job to configure"
                  variant="bordered"
                  selectedKeys={selectedJobId ? [selectedJobId] : []}
                  onChange={(e) => setSelectedJobId(e.target.value)}
                >
                  {jobs.map((job) => (
                    <SelectItem key={job.id.toString()}>
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
                            <span className="text-sm font-medium">Enable Job</span>
                            <span className="text-xs text-default-400">
                              Job will run on schedule when enabled
                            </span>
                          </div>
                        </Switch>

                        <Input
                          label="Custom Schedule"
                          placeholder="* * * * *"
                          description="Cron expression (leave empty to use default)"
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
                              Notify on Failure
                            </span>
                            <span className="text-xs text-default-400">
                              Send email when job fails
                            </span>
                          </div>
                        </Switch>

                        {jobSettings.notify_on_failure && (
                          <Textarea
                            label="Notification Emails"
                            placeholder="admin@example.com, dev@example.com"
                            description="Comma-separated email addresses"
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
                          label="Max Retries"
                          type="number"
                          placeholder="3"
                          description="Number of times to retry failed jobs"
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
                          label="Timeout (seconds)"
                          type="number"
                          placeholder="300"
                          description="Maximum execution time"
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
                          Save Job Settings
                        </Button>
                      </div>
                    )}
                  </>
                )}

                {!selectedJobId && (
                  <div className="flex flex-col items-center gap-2 py-8 text-default-400">
                    <AlertCircle size={32} />
                    <p className="text-sm">Select a job to configure</p>
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
            <span className="text-lg font-semibold">Global Settings</span>
          </CardHeader>
          <CardBody className="space-y-4">
            {loadingGlobalSettings ? (
              <div className="flex items-center justify-center py-8">
                <Spinner size="sm" />
              </div>
            ) : (
              <>
                <Input
                  label="Default Notification Email"
                  type="email"
                  placeholder="admin@example.com"
                  description="Fallback email for job failure notifications"
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
                  label="Log Retention (days)"
                  type="number"
                  placeholder="30"
                  description="How long to keep job execution logs"
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
                  label="Max Concurrent Jobs"
                  type="number"
                  placeholder="5"
                  description="Maximum number of jobs that can run simultaneously"
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
                  Save Global Settings
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
