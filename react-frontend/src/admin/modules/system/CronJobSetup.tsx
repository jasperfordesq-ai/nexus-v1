// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Cron Job Setup
 * Platform setup guide for configuring the Laravel scheduler.
 *
 * All cron jobs run via `artisan schedule:run` which calls CronJobRunner::runAll().
 * HTTP-based cron endpoints (/cron/*) were removed (2026-04-02) to prevent
 * duplicate email sends. This page reflects the single-trigger model.
 */

import { useState } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Tabs,
  Tab,
  Code,
  Chip,
} from '@heroui/react';
import {
  Server,
  Copy,
  CheckCircle,
  PlayCircle,
  AlertTriangle,
  Info,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader } from '../../components';
import { adminSystem } from '../../api/adminApi';

import { useTranslation } from 'react-i18next';

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function CronJobSetup() {
  const { t } = useTranslation('admin');
  usePageTitle(t('system.cron_job_setup_title'));
  const toast = useToast();
  const [testing, setTesting] = useState(false);

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    toast.success(t('system.copied_to_clipboard'));
  };

  const handleTestConnection = async () => {
    setTesting(true);
    try {
      const res = await adminSystem.getCronJobs();
      if (res.success && Array.isArray(res.data)) {
        toast.success(t('system.connection_ok', { count: res.data.length }));
      } else {
        toast.error(t('system.api_unexpected_data'));
      }
    } catch {
      toast.error(t('system.failed_to_connect_cron_api'));
    }
    setTesting(false);
  };

  return (
    <div>
      <PageHeader
        title={t('system.cron_job_setup_title')}
        description={t('system.cron_job_setup_desc')}
      />

      {/* Important Notice */}
      <Card shadow="sm" className="mb-6">
        <CardBody>
          <div className="bg-warning/10 border border-warning/20 rounded-lg p-3 flex items-start gap-2">
            <AlertTriangle size={16} className="text-warning mt-0.5 shrink-0" />
            <div className="text-sm text-default-700 dark:text-default-300">
              <p className="font-medium mb-1">
                {t('cron_setup.notice_title')}
              </p>
              <p className="text-xs text-default-500">
                {t('cron_setup.notice_prefix')} <Code className="text-xs">artisan schedule:run</Code>{' '}
                {t('cron_setup.notice_middle')} <Code className="text-xs">/cron/*</Code>{' '}
                {t('cron_setup.notice_suffix')}
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Test Connection */}
      <Card shadow="sm" className="mb-6">
        <CardBody className="flex flex-row items-center justify-between gap-4">
          <div>
            <p className="font-medium">{t('cron_setup.test_cron_api')}</p>
            <p className="text-sm text-default-500">
              {t('cron_setup.test_cron_api_desc')}
            </p>
          </div>
          <Button
            color="success"
            variant="flat"
            startContent={<PlayCircle size={16} />}
            onPress={handleTestConnection}
            isLoading={testing}
          >
            {t('cron_setup.test_connection')}
          </Button>
        </CardBody>
      </Card>

      {/* Platform-specific instructions */}
      <Card shadow="sm">
        <CardBody className="p-0">
          <Tabs
            aria-label={t('system.label_platform_setup_instructions')}
            variant="underlined"
            classNames={{
              base: 'w-full',
              tabList: 'px-4',
              panel: 'px-4 pb-4',
            }}
          >
            {/* Docker (Primary) */}
            <Tab key="docker" title={<div className="flex items-center gap-1.5"><Server size={14} /> {t('cron_setup.docker')} <Chip size="sm" color="success" variant="flat">{t('cron_setup.recommended')}</Chip></div>}>
              <div className="space-y-4 pt-4">
                <div>
                  <h3 className="text-base font-semibold mb-2">
                    {t('cron_setup.docker_step_1')}
                  </h3>
                  <p className="text-sm text-default-600 mb-2">
                    {t('cron_setup.docker_step_1_desc')}
                  </p>
                  <div className="flex items-center gap-2">
                    <Code className="flex-1 text-xs break-all">
                      * * * * * docker exec nexus-php-app php /var/www/html/artisan schedule:run {'>'}{'>'}  /var/log/nexus-scheduler.log 2{'>'}&1
                    </Code>
                    <Button
                        size="sm"
                        variant="flat"
                        isIconOnly
                        aria-label={t('system.label_copy_crontab_entry')}
                        onPress={() =>
                          copyToClipboard('* * * * * docker exec nexus-php-app php /var/www/html/artisan schedule:run >> /var/log/nexus-scheduler.log 2>&1')
                        }
                    >
                      <Copy size={14} />
                    </Button>
                  </div>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">{t('cron_setup.verify')}</h3>
                  <Code className="block text-xs">sudo crontab -l</Code>
                </div>

                <div className="bg-success/10 border border-success/20 rounded-lg p-3 flex items-start gap-2">
                  <CheckCircle size={16} className="text-success mt-0.5 shrink-0" />
                  <p className="text-xs text-default-600">
                    <strong>{t('cron_setup.thats_it')}</strong> {t('cron_setup.thats_it_prefix')}{' '}
                    <Code className="text-xs">CronJobRunner::runAll()</Code> {t('cron_setup.thats_it_suffix')}
                  </p>
                </div>
              </div>
            </Tab>

            {/* Linux */}
            <Tab key="linux" title={t('cron_setup.linux_vps')}>
              <div className="space-y-4 pt-4">
                <div>
                  <h3 className="text-base font-semibold mb-2">{t('cron_setup.linux_step_1')}</h3>
                  <Code className="block text-xs">crontab -e</Code>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">{t('cron_setup.linux_step_2')}</h3>
                  <div className="flex items-center gap-2">
                    <Code className="flex-1 text-xs break-all">
                      * * * * * cd /path/to/your/project && php artisan schedule:run {'>'}{'>'}  /dev/null 2{'>'}&1
                    </Code>
                    <Button
                        size="sm"
                        variant="flat"
                        isIconOnly
                        aria-label={t('system.label_copy_crontab_entry')}
                        onPress={() =>
                          copyToClipboard('* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1')
                        }
                    >
                      <Copy size={14} />
                    </Button>
                  </div>
                  <p className="text-xs text-default-500 mt-1">
                    {t('cron_setup.linux_step_2_desc')} <Code className="text-xs">/path/to/your/project</Code>{' '}
                    {t('cron_setup.with_your_actual_project_root')}
                  </p>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">{t('cron_setup.verify')}</h3>
                  <Code className="block text-xs">crontab -l</Code>
                </div>
              </div>
            </Tab>

            {/* cPanel */}
            <Tab key="cpanel" title={t('cron_setup.cpanel')}>
              <div className="space-y-4 pt-4">
                <div>
                  <h3 className="text-base font-semibold mb-2">{t('cron_setup.cpanel_step_1')}</h3>
                  <p className="text-sm text-default-600">
                    {t('cron_setup.cpanel_step_1_desc')}
                  </p>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">{t('cron_setup.cpanel_step_2')}</h3>
                  <p className="text-sm text-default-600 mb-2">
                    {t('cron_setup.cpanel_step_2_desc')} <Code className="text-xs">* * * * *</Code>{' '}
                    {t('cron_setup.with')}
                  </p>
                  <div className="flex items-center gap-2">
                    <Code className="flex-1 text-xs break-all">
                      cd /home/username/public_html && php artisan schedule:run {'>'}{'>'}  /dev/null 2{'>'}&1
                    </Code>
                    <Button
                      size="sm"
                      variant="flat"
                      isIconOnly
                      aria-label={t('system.label_copy_code')}
                      onPress={() =>
                        copyToClipboard('cd /home/username/public_html && php artisan schedule:run >> /dev/null 2>&1')
                      }
                    >
                      <Copy size={14} />
                    </Button>
                  </div>
                  <p className="text-xs text-default-500 mt-1">
                    {t('cron_setup.cpanel_path_prefix')} <Code className="text-xs">/home/username/public_html</Code>{' '}
                    {t('cron_setup.cpanel_path_suffix')}
                  </p>
                </div>
              </div>
            </Tab>

            {/* Azure */}
            <Tab key="azure" title={t('cron_setup.azure_vm')}>
              <div className="space-y-4 pt-4">
                <div>
                  <h3 className="text-base font-semibold mb-2">{t('cron_setup.azure_step_1')}</h3>
                  <Code className="block text-xs">ssh azureuser@your-vm-ip</Code>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">{t('cron_setup.azure_step_2')}</h3>
                  <div className="flex items-center gap-2">
                    <Code className="flex-1 text-xs break-all">
                      * * * * * docker exec nexus-php-app php /var/www/html/artisan schedule:run {'>'}{'>'}  /var/log/nexus-scheduler.log 2{'>'}&1
                    </Code>
                    <Button
                      size="sm"
                      variant="flat"
                      isIconOnly
                      aria-label={t('system.label_copy_crontab_entry')}
                      onPress={() =>
                        copyToClipboard('* * * * * docker exec nexus-php-app php /var/www/html/artisan schedule:run >> /var/log/nexus-scheduler.log 2>&1')
                      }
                    >
                      <Copy size={14} />
                    </Button>
                  </div>
                </div>

                <div className="bg-warning/10 border border-warning/20 rounded-lg p-3 flex items-start gap-2">
                  <AlertTriangle size={16} className="text-warning mt-0.5 shrink-0" />
                  <p className="text-xs text-warning-700 dark:text-warning-300">
                    {t('cron_setup.azure_warning_prefix')} <strong>{t('cron_setup.not')}</strong>{' '}
                    {t('cron_setup.azure_warning_middle')} <Code className="text-xs">artisan schedule:run</Code>{' '}
                    {t('cron_setup.only')}
                  </p>
                </div>
              </div>
            </Tab>

            {/* GCP */}
            <Tab key="gcp" title={t('cron_setup.gcp')}>
              <div className="space-y-4 pt-4">
                <div>
                  <h3 className="text-base font-semibold mb-2">{t('cron_setup.gcp_step_1')}</h3>
                  <Code className="block text-xs">gcloud compute ssh your-instance-name</Code>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">{t('cron_setup.gcp_step_2')}</h3>
                  <div className="flex items-center gap-2">
                    <Code className="flex-1 text-xs break-all">
                      * * * * * docker exec nexus-php-app php /var/www/html/artisan schedule:run {'>'}{'>'}  /var/log/nexus-scheduler.log 2{'>'}&1
                    </Code>
                    <Button
                      size="sm"
                      variant="flat"
                      isIconOnly
                      aria-label={t('system.label_copy_crontab_entry')}
                      onPress={() =>
                        copyToClipboard('* * * * * docker exec nexus-php-app php /var/www/html/artisan schedule:run >> /var/log/nexus-scheduler.log 2>&1')
                      }
                    >
                      <Copy size={14} />
                    </Button>
                  </div>
                </div>

                <div className="bg-warning/10 border border-warning/20 rounded-lg p-3 flex items-start gap-2">
                  <AlertTriangle size={16} className="text-warning mt-0.5 shrink-0" />
                  <p className="text-xs text-warning-700 dark:text-warning-300">
                    {t('cron_setup.gcp_warning_prefix')} <strong>{t('cron_setup.not')}</strong>{' '}
                    {t('cron_setup.gcp_warning_middle')} <Code className="text-xs">artisan schedule:run</Code>{' '}
                    {t('cron_setup.only')}
                  </p>
                </div>
              </div>
            </Tab>
          </Tabs>
        </CardBody>
      </Card>

      {/* Verification Checklist */}
      <Card shadow="sm" className="mt-6">
        <CardHeader className="flex items-center gap-2">
          <Info size={18} className="text-primary" />
          <h3 className="text-lg font-semibold">{t('cron_setup.verification_checklist')}</h3>
        </CardHeader>
        <CardBody className="space-y-2">
          <div className="flex items-center gap-2">
            <CheckCircle size={16} className="text-success" />
            <span className="text-sm">
              <Code className="text-xs">artisan schedule:run</Code> {t('cron_setup.checklist_schedule')}
            </span>
          </div>
          <div className="flex items-center gap-2">
            <CheckCircle size={16} className="text-success" />
            <span className="text-sm">{t('cron_setup.checklist_first_run')}</span>
          </div>
          <div className="flex items-center gap-2">
            <CheckCircle size={16} className="text-success" />
            <span className="text-sm">{t('cron_setup.checklist_logs')}</span>
          </div>
          <div className="flex items-center gap-2">
            <CheckCircle size={16} className="text-success" />
            <span className="text-sm">{t('cron_setup.checklist_test_connection')}</span>
          </div>
          <div className="flex items-center gap-2">
            <CheckCircle size={16} className="text-success" />
            <span className="text-sm">{t('cron_setup.checklist_no_http_triggers')}</span>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default CronJobSetup;
