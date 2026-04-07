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
  usePageTitle(t('system.page_title'));
  const toast = useToast();
  const [testing, setTesting] = useState(false);

  const copyToClipboard = (text: string, label: string) => {
    navigator.clipboard.writeText(text);
    toast.success(t('system.label_copied_to_clipboard', { label }));
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
                All cron jobs run via the Laravel scheduler
              </p>
              <p className="text-xs text-default-500">
                The only cron entry needed is <Code className="text-xs">artisan schedule:run</Code> every
                minute. HTTP-based cron endpoints (<Code className="text-xs">/cron/*</Code>) were removed
                to prevent duplicate email sends. Do not use curl-based cron triggers.
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Test Connection */}
      <Card shadow="sm" className="mb-6">
        <CardBody className="flex flex-row items-center justify-between gap-4">
          <div>
            <p className="font-medium">Test Cron API</p>
            <p className="text-sm text-default-500">
              Verify that the cron job system is responding and reporting jobs
            </p>
          </div>
          <Button
            color="success"
            variant="flat"
            startContent={<PlayCircle size={16} />}
            onPress={handleTestConnection}
            isLoading={testing}
          >
            Test Connection
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
            <Tab key="docker" title={<div className="flex items-center gap-1.5"><Server size={14} /> Docker <Chip size="sm" color="success" variant="flat">Recommended</Chip></div>}>
              <div className="space-y-4 pt-4">
                <div>
                  <h3 className="text-base font-semibold mb-2">
                    1. Add to Host Crontab
                  </h3>
                  <p className="text-sm text-default-600 mb-2">
                    On the <strong>host machine</strong> (not inside the container), add this single entry:
                  </p>
                  <div className="flex items-center gap-2">
                    <Code className="flex-1 text-xs break-all">
                      * * * * * docker exec nexus-php-app php /var/www/html/artisan schedule:run {'>'}{'>'}  /var/log/nexus-scheduler.log 2{'>'}&1
                    </Code>
                    <Button
                      size="sm"
                      variant="flat"
                      isIconOnly
                      aria-label="Copy crontab entry"
                      onPress={() =>
                        copyToClipboard(
                          '* * * * * docker exec nexus-php-app php /var/www/html/artisan schedule:run >> /var/log/nexus-scheduler.log 2>&1',
                          'Crontab entry'
                        )
                      }
                    >
                      <Copy size={14} />
                    </Button>
                  </div>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">2. Verify</h3>
                  <Code className="block text-xs">sudo crontab -l</Code>
                </div>

                <div className="bg-success/10 border border-success/20 rounded-lg p-3 flex items-start gap-2">
                  <CheckCircle size={16} className="text-success mt-0.5 shrink-0" />
                  <p className="text-xs text-default-600">
                    <strong>That&apos;s it!</strong> The Laravel scheduler calls <Code className="text-xs">CronJobRunner::runAll()</Code> every
                    minute, which internally determines which of the 40+ tasks to run.
                  </p>
                </div>
              </div>
            </Tab>

            {/* Linux */}
            <Tab key="linux" title="Linux / VPS">
              <div className="space-y-4 pt-4">
                <div>
                  <h3 className="text-base font-semibold mb-2">1. Edit Crontab</h3>
                  <Code className="block text-xs">crontab -e</Code>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">2. Add Laravel Scheduler Entry</h3>
                  <div className="flex items-center gap-2">
                    <Code className="flex-1 text-xs break-all">
                      * * * * * cd /path/to/your/project && php artisan schedule:run {'>'}{'>'}  /dev/null 2{'>'}&1
                    </Code>
                    <Button
                      size="sm"
                      variant="flat"
                      isIconOnly
                      aria-label="Copy crontab entry"
                      onPress={() =>
                        copyToClipboard(
                          '* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1',
                          'Crontab entry'
                        )
                      }
                    >
                      <Copy size={14} />
                    </Button>
                  </div>
                  <p className="text-xs text-default-500 mt-1">
                    Replace <Code className="text-xs">/path/to/your/project</Code> with your actual project root.
                  </p>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">3. Verify</h3>
                  <Code className="block text-xs">crontab -l</Code>
                </div>
              </div>
            </Tab>

            {/* cPanel */}
            <Tab key="cpanel" title="cPanel">
              <div className="space-y-4 pt-4">
                <div>
                  <h3 className="text-base font-semibold mb-2">1. Access Cron Jobs</h3>
                  <p className="text-sm text-default-600">
                    Log in to cPanel, navigate to <strong>Advanced</strong> &rarr; <strong>Cron Jobs</strong>
                  </p>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">2. Add Cron Job</h3>
                  <p className="text-sm text-default-600 mb-2">
                    Set to run every minute (<Code className="text-xs">* * * * *</Code>) with:
                  </p>
                  <div className="flex items-center gap-2">
                    <Code className="flex-1 text-xs break-all">
                      cd /home/username/public_html && php artisan schedule:run {'>'}{'>'}  /dev/null 2{'>'}&1
                    </Code>
                    <Button
                      size="sm"
                      variant="flat"
                      isIconOnly
                      aria-label="Copy cPanel command"
                      onPress={() =>
                        copyToClipboard(
                          'cd /home/username/public_html && php artisan schedule:run >> /dev/null 2>&1',
                          'cPanel command'
                        )
                      }
                    >
                      <Copy size={14} />
                    </Button>
                  </div>
                  <p className="text-xs text-default-500 mt-1">
                    Replace <Code className="text-xs">/home/username/public_html</Code> with your actual path.
                    On shared hosting limited to every 5 or 15 minutes, adjust the schedule — the scheduler still handles timing internally.
                  </p>
                </div>
              </div>
            </Tab>

            {/* Azure */}
            <Tab key="azure" title="Azure VM">
              <div className="space-y-4 pt-4">
                <div>
                  <h3 className="text-base font-semibold mb-2">1. SSH into the VM</h3>
                  <Code className="block text-xs">ssh azureuser@your-vm-ip</Code>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">2. Add to Root Crontab</h3>
                  <div className="flex items-center gap-2">
                    <Code className="flex-1 text-xs break-all">
                      * * * * * docker exec nexus-php-app php /var/www/html/artisan schedule:run {'>'}{'>'}  /var/log/nexus-scheduler.log 2{'>'}&1
                    </Code>
                    <Button
                      size="sm"
                      variant="flat"
                      isIconOnly
                      aria-label="Copy Azure crontab entry"
                      onPress={() =>
                        copyToClipboard(
                          '* * * * * docker exec nexus-php-app php /var/www/html/artisan schedule:run >> /var/log/nexus-scheduler.log 2>&1',
                          'Azure crontab entry'
                        )
                      }
                    >
                      <Copy size={14} />
                    </Button>
                  </div>
                </div>

                <div className="bg-warning/10 border border-warning/20 rounded-lg p-3 flex items-start gap-2">
                  <AlertTriangle size={16} className="text-warning mt-0.5 shrink-0" />
                  <p className="text-xs text-warning-700 dark:text-warning-300">
                    Do <strong>not</strong> use Azure WebJobs, Functions, or Cloud Scheduler to hit HTTP
                    endpoints. Use <Code className="text-xs">artisan schedule:run</Code> only.
                  </p>
                </div>
              </div>
            </Tab>

            {/* GCP */}
            <Tab key="gcp" title="GCP">
              <div className="space-y-4 pt-4">
                <div>
                  <h3 className="text-base font-semibold mb-2">1. SSH into the GCE VM</h3>
                  <Code className="block text-xs">gcloud compute ssh your-instance-name</Code>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">2. Add to Root Crontab</h3>
                  <div className="flex items-center gap-2">
                    <Code className="flex-1 text-xs break-all">
                      * * * * * docker exec nexus-php-app php /var/www/html/artisan schedule:run {'>'}{'>'}  /var/log/nexus-scheduler.log 2{'>'}&1
                    </Code>
                    <Button
                      size="sm"
                      variant="flat"
                      isIconOnly
                      aria-label="Copy GCP crontab entry"
                      onPress={() =>
                        copyToClipboard(
                          '* * * * * docker exec nexus-php-app php /var/www/html/artisan schedule:run >> /var/log/nexus-scheduler.log 2>&1',
                          'GCP crontab entry'
                        )
                      }
                    >
                      <Copy size={14} />
                    </Button>
                  </div>
                </div>

                <div className="bg-warning/10 border border-warning/20 rounded-lg p-3 flex items-start gap-2">
                  <AlertTriangle size={16} className="text-warning mt-0.5 shrink-0" />
                  <p className="text-xs text-warning-700 dark:text-warning-300">
                    Do <strong>not</strong> use GCP Cloud Scheduler to hit HTTP endpoints. Use the VM
                    crontab with <Code className="text-xs">artisan schedule:run</Code> only.
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
          <h3 className="text-lg font-semibold">Verification Checklist</h3>
        </CardHeader>
        <CardBody className="space-y-2">
          <div className="flex items-center gap-2">
            <CheckCircle size={16} className="text-success" />
            <span className="text-sm">
              <Code className="text-xs">artisan schedule:run</Code> is in the host crontab (every minute)
            </span>
          </div>
          <div className="flex items-center gap-2">
            <CheckCircle size={16} className="text-success" />
            <span className="text-sm">First scheduled run has executed (check logs page)</span>
          </div>
          <div className="flex items-center gap-2">
            <CheckCircle size={16} className="text-success" />
            <span className="text-sm">Cron logs are populating in the dashboard</span>
          </div>
          <div className="flex items-center gap-2">
            <CheckCircle size={16} className="text-success" />
            <span className="text-sm">Test Connection button returns job count</span>
          </div>
          <div className="flex items-center gap-2">
            <CheckCircle size={16} className="text-success" />
            <span className="text-sm">No curl-based or HTTP cron triggers are configured</span>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default CronJobSetup;
