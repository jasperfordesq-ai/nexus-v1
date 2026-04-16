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
import { useToast } from '@/contexts';
import { adminCron, adminSystem } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { CronJob, CronJobSettings, GlobalCronSettings } from '../../api/types';

import { useTranslation } from 'react-i18next';
// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function CronJobSettingsPage() {
  const { t } = useTranslation('admin');
  usePageTitle(t('system.page_title'));
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
      toast.error(t('system.failed_to_load_job_settings'));
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
      toast.error(t('system.failed_to_load_global_settings'));
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
        toast.success(t('system.job_settings_saved_successfully'));
      } else {
        toast.error(res.error || t('system.failed_to_save_job_settings'));
      }
    } catch {
      toast.error(t('system.failed_to_save_job_settings'));
    }
    setSavingJobSettings(false);
  };

  // Save global settings
  const handleSaveGlobalSettings = async () => {
    setSavingGlobalSettings(true);
    try {
      const res = await adminCron.updateGlobalSettings(globalSettings);
      if (res.success) {
        toast.success(t('system.global_settings_saved_successfully'));
      } else {
        toast.error(res.error || t('system.failed_to_save_global_settings'));
      }
    } catch {
      toast.error(t('system.failed_to_save_global_settings'));
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
        title={t('system.cron_job_settings_title')}
        description={t('system.cron_job_settings_desc')}
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Per-Job Settings */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2">
            <Settings size={18} className="text-primary" />
            <span className="text-lg font-semibold">{t('system.section_per_job_settings')}</span>
          </CardHeader>
          <CardBody className="space-y-4">
            {loadingJobs ? (
              <div className="flex items-center justify-center py-8">
                <Spinner size="sm" />
              </div>
            ) : (
              <>
                <Select
                  label={t('system.label_select_job')}
                  placeholder={t('system.placeholder_choose_a_job_to_configure')}
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
                            <span className="text-sm font-medium">{t('cron_settings.enable_job')}</span>
                            <span className="text-xs text-default-400">
                              {t('system.desc_job_will_run_when_enabled')}
                            </span>
                          </div>
                        </Switch>

                        <Input
                          label={t('system.label_custom_schedule')}
                          placeholder="* * * * *"
                          description={t('system.desc_cron_expression_leave_empty_to_use_defa')}
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
                              {t('system.label_notify_on_failure')}
                            </span>
                            <span className="text-xs text-default-400">
                              {t('system.desc_notify_on_failure')}
                            </span>
                          </div>
                        </Switch>

                        {jobSettings.notify_on_failure && (
                          <Textarea
                            label={t('system.label_notification_emails')}
                            placeholder="admin@example.com, dev@example.com"
                            description={t('system.desc_comma_separated_emails')}
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
                          label={t('system.label_max_retries')}
                          type="number"
                          placeholder="3"
                          description={t('system.desc_number_of_times_to_retry_failed_jobs')}
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
                          label={t('system.label_timeout')}
                          type="number"
                          placeholder="300"
                          description={t('system.desc_maximum_execution_time')}
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
                          {t('system.btn_save_job_settings')}
                        </Button>
                      </div>
                    )}
                  </>
                )}

                {!selectedJobId && (
                  <div className="flex flex-col items-center gap-2 py-8 text-default-400">
                    <AlertCircle size={32} />
                    <p className="text-sm">{t('cron_settings.select_job')}</p>
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
            <span className="text-lg font-semibold">{t('cron_settings.global_settings')}</span>
          </CardHeader>
          <CardBody className="space-y-4">
            {loadingGlobalSettings ? (
              <div className="flex items-center justify-center py-8">
                <Spinner size="sm" />
              </div>
            ) : (
              <>
                <Input
                  label={t('system.label_default_notification_email')}
                  type="email"
                  placeholder="admin@example.com"
                  description={t('system.desc_fallback_email_for_job_failure_notificat')}
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
                  label={t('system.label_log_retention')}
                  type="number"
                  placeholder="30"
                  description={t('system.desc_how_long_to_keep_job_execution_logs')}
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
                  label={t('system.label_max_concurrent_jobs')}
                  type="number"
                  placeholder="5"
                  description={t('system.desc_maximum_number_of_jobs_that_can_run_simu')}
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
                  {t('system.btn_save_global_settings')}
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
