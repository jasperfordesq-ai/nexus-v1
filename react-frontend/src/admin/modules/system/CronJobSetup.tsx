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
import Server from 'lucide-react/icons/server';
import Copy from 'lucide-react/icons/copy';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import PlayCircle from 'lucide-react/icons/circle-play';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import Info from 'lucide-react/icons/info';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader } from '../../components';
import { adminSystem } from '../../api/adminApi';

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function CronJobSetup() {
  usePageTitle("Cron Job Setup");
  const toast = useToast();
  const [testing, setTesting] = useState(false);

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    toast.success("Copied to Clipboard");
  };

  const handleTestConnection = async () => {
    setTesting(true);
    try {
      const res = await adminSystem.getCronJobs();
      if (res.success && Array.isArray(res.data)) {
        toast.success(`Connection OK — ${res.data.length} jobs available`);
      } else {
        toast.error("API returned unexpected data");
      }
    } catch {
      toast.error("Failed to connect to cron API");
    }
    setTesting(false);
  };

  return (
    <div>
      <PageHeader
        title={"Cron Job Setup"}
        description={"Set up cron jobs to run on a custom schedule"}
      />

      {/* Important Notice */}
      <Card shadow="sm" className="mb-6">
        <CardBody>
          <div className="bg-warning/10 border border-warning/20 rounded-lg p-3 flex items-start gap-2">
            <AlertTriangle size={16} className="text-warning mt-0.5 shrink-0" />
            <div className="text-sm text-default-700 dark:text-default-300">
              <p className="font-medium mb-1">
                {"Important: how cron works on Project NEXUS"}
              </p>
              <p className="text-xs text-default-500">
                {"A single system cron entry runs"} <Code className="text-xs">artisan schedule:run</Code>{' '}
                {"every minute. The platform dispatches jobs internally — do NOT add separate"} <Code className="text-xs">/cron/*</Code>{' '}
                {"HTTP triggers."}
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Test Connection */}
      <Card shadow="sm" className="mb-6">
        <CardBody className="flex flex-row items-center justify-between gap-4">
          <div>
            <p className="font-medium">{"Test the cron API"}</p>
            <p className="text-sm text-default-500">
              {"Verify that the cron runner can be reached and lists scheduled jobs"}
            </p>
          </div>
          <Button
            color="success"
            variant="flat"
            startContent={<PlayCircle size={16} />}
            onPress={handleTestConnection}
            isLoading={testing}
          >
            {"Test connection"}
          </Button>
        </CardBody>
      </Card>

      {/* Platform-specific instructions */}
      <Card shadow="sm">
        <CardBody className="p-0">
          <Tabs
            aria-label={"Platform Setup Instructions"}
            variant="underlined"
            classNames={{
              base: 'w-full',
              tabList: 'px-4',
              panel: 'px-4 pb-4',
            }}
          >
            {/* Docker (Primary) */}
            <Tab key="docker" title={<div className="flex items-center gap-1.5"><Server size={14} /> {"Docker"} <Chip size="sm" color="success" variant="flat">{"Recommended"}</Chip></div>}>
              <div className="space-y-4 pt-4">
                <div>
                  <h3 className="text-base font-semibold mb-2">
                    {"Step 1: Add a cron entry on the host"}
                  </h3>
                  <p className="text-sm text-default-600 mb-2">
                    {"Edit the host crontab with `sudo crontab -e` and add this single line:"}
                  </p>
                  <div className="flex items-center gap-2">
                    <Code className="flex-1 text-xs break-all">
                      * * * * * docker exec nexus-php-app php /var/www/html/artisan schedule:run {'>'}{'>'}  /var/log/nexus-scheduler.log 2{'>'}&1
                    </Code>
                    <Button
                        size="sm"
                        variant="flat"
                        isIconOnly
                        aria-label={"Copy Crontab Entry"}
                        onPress={() =>
                          copyToClipboard('* * * * * docker exec nexus-php-app php /var/www/html/artisan schedule:run >> /var/log/nexus-scheduler.log 2>&1')
                        }
                    >
                      <Copy size={14} />
                    </Button>
                  </div>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">{"Verify"}</h3>
                  <Code className="block text-xs">sudo crontab -l</Code>
                </div>

                <div className="bg-success/10 border border-success/20 rounded-lg p-3 flex items-start gap-2">
                  <CheckCircle size={16} className="text-success mt-0.5 shrink-0" />
                  <p className="text-xs text-default-600">
                    <strong>{"That's it!"}</strong> {"Laravel's scheduler (via"}{' '}
                    <Code className="text-xs">CronJobRunner::runAll()</Code> {") will handle all job dispatching internally."}
                  </p>
                </div>
              </div>
            </Tab>

            {/* Linux */}
            <Tab key="linux" title={"Linux / VPS"}>
              <div className="space-y-4 pt-4">
                <div>
                  <h3 className="text-base font-semibold mb-2">{"Step 1: Open your crontab"}</h3>
                  <Code className="block text-xs">crontab -e</Code>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">{"Step 2: Add a single scheduler line"}</h3>
                  <div className="flex items-center gap-2">
                    <Code className="flex-1 text-xs break-all">
                      * * * * * cd /path/to/your/project && php artisan schedule:run {'>'}{'>'}  /dev/null 2{'>'}&1
                    </Code>
                    <Button
                        size="sm"
                        variant="flat"
                        isIconOnly
                        aria-label={"Copy Crontab Entry"}
                        onPress={() =>
                          copyToClipboard('* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1')
                        }
                    >
                      <Copy size={14} />
                    </Button>
                  </div>
                  <p className="text-xs text-default-500 mt-1">
                    {"Replace"} <Code className="text-xs">/path/to/your/project</Code>{' '}
                    {"with your actual project root."}
                  </p>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">{"Verify"}</h3>
                  <Code className="block text-xs">crontab -l</Code>
                </div>
              </div>
            </Tab>

            {/* cPanel */}
            <Tab key="cpanel" title={"cPanel"}>
              <div className="space-y-4 pt-4">
                <div>
                  <h3 className="text-base font-semibold mb-2">{"Step 1: Open Cron Jobs in cPanel"}</h3>
                  <p className="text-sm text-default-600">
                    {"In cPanel, navigate to the Advanced section and open Cron Jobs."}
                  </p>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">{"Step 2: Add a new cron job"}</h3>
                  <p className="text-sm text-default-600 mb-2">
                    {"Set the schedule to"} <Code className="text-xs">* * * * *</Code>{' '}
                    {"with the command:"}
                  </p>
                  <div className="flex items-center gap-2">
                    <Code className="flex-1 text-xs break-all">
                      cd /home/username/public_html && php artisan schedule:run {'>'}{'>'}  /dev/null 2{'>'}&1
                    </Code>
                    <Button
                      size="sm"
                      variant="flat"
                      isIconOnly
                      aria-label={"Copy Code"}
                      onPress={() =>
                        copyToClipboard('cd /home/username/public_html && php artisan schedule:run >> /dev/null 2>&1')
                      }
                    >
                      <Copy size={14} />
                    </Button>
                  </div>
                  <p className="text-xs text-default-500 mt-1">
                    {"Replace"} <Code className="text-xs">/home/username/public_html</Code>{' '}
                    {"with the absolute path to your project."}
                  </p>
                </div>
              </div>
            </Tab>

            {/* Azure */}
            <Tab key="azure" title={"Azure VM"}>
              <div className="space-y-4 pt-4">
                <div>
                  <h3 className="text-base font-semibold mb-2">{"Step 1: SSH into your VM"}</h3>
                  <Code className="block text-xs">ssh azureuser@your-vm-ip</Code>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">{"Step 2: Add a host crontab entry"}</h3>
                  <div className="flex items-center gap-2">
                    <Code className="flex-1 text-xs break-all">
                      * * * * * docker exec nexus-php-app php /var/www/html/artisan schedule:run {'>'}{'>'}  /var/log/nexus-scheduler.log 2{'>'}&1
                    </Code>
                    <Button
                      size="sm"
                      variant="flat"
                      isIconOnly
                      aria-label={"Copy Crontab Entry"}
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
                    {"Do"} <strong>{"NOT"}</strong>{' '}
                    {"call any HTTP cron endpoints. The host cron must invoke"} <Code className="text-xs">artisan schedule:run</Code>{' '}
                    {"only."}
                  </p>
                </div>
              </div>
            </Tab>

            {/* GCP */}
            <Tab key="gcp" title={"GCP"}>
              <div className="space-y-4 pt-4">
                <div>
                  <h3 className="text-base font-semibold mb-2">{"Step 1: SSH into your instance"}</h3>
                  <Code className="block text-xs">gcloud compute ssh your-instance-name</Code>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">{"Step 2: Add a host crontab entry"}</h3>
                  <div className="flex items-center gap-2">
                    <Code className="flex-1 text-xs break-all">
                      * * * * * docker exec nexus-php-app php /var/www/html/artisan schedule:run {'>'}{'>'}  /var/log/nexus-scheduler.log 2{'>'}&1
                    </Code>
                    <Button
                      size="sm"
                      variant="flat"
                      isIconOnly
                      aria-label={"Copy Crontab Entry"}
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
                    {"Do"} <strong>{"NOT"}</strong>{' '}
                    {"use Cloud Scheduler to hit HTTP cron endpoints. The host cron must invoke"} <Code className="text-xs">artisan schedule:run</Code>{' '}
                    {"only."}
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
          <h3 className="text-lg font-semibold">{"Verification checklist"}</h3>
        </CardHeader>
        <CardBody className="space-y-2">
          <div className="flex items-center gap-2">
            <CheckCircle size={16} className="text-success" />
            <span className="text-sm">
              <Code className="text-xs">artisan schedule:run</Code> {"runs every minute without errors"}
            </span>
          </div>
          <div className="flex items-center gap-2">
            <CheckCircle size={16} className="text-success" />
            <span className="text-sm">{"First run has completed and a log entry exists"}</span>
          </div>
          <div className="flex items-center gap-2">
            <CheckCircle size={16} className="text-success" />
            <span className="text-sm">{"Cron Job Logs page shows successful executions"}</span>
          </div>
          <div className="flex items-center gap-2">
            <CheckCircle size={16} className="text-success" />
            <span className="text-sm">{"Test connection button returns the configured job list"}</span>
          </div>
          <div className="flex items-center gap-2">
            <CheckCircle size={16} className="text-success" />
            <span className="text-sm">{"No separate HTTP triggers for /cron/* endpoints are configured"}</span>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default CronJobSetup;
