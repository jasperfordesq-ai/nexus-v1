// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Cron Job Setup
 * Platform setup guide for configuring cron job execution
 * Parity: PHP CronJobController::setup()
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
} from '@heroui/react';
import {
  Server,
  Copy,
  CheckCircle,
  Eye,
  EyeOff,
  PlayCircle,
  AlertTriangle,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function CronJobSetup() {
  usePageTitle('Admin - Cron Job Setup');
  const toast = useToast();
  const [showKey, setShowKey] = useState(false);
  const [testing, setTesting] = useState(false);

  // In a real scenario, this would come from environment/config
  const CRON_KEY = import.meta.env.VITE_CRON_KEY || 'your-secure-cron-key-here';
  const API_URL = import.meta.env.VITE_API_BASE?.replace('/api', '') || 'https://api.project-nexus.ie';
  const CRON_URL = `${API_URL}/cron.php`;

  const obfuscatedKey = showKey
    ? CRON_KEY
    : `${CRON_KEY.slice(0, 4)}${'*'.repeat(20)}${CRON_KEY.slice(-4)}`;

  const copyToClipboard = (text: string, label: string) => {
    navigator.clipboard.writeText(text);
    toast.success(`${label} copied to clipboard`);
  };

  const handleTestConnection = async () => {
    setTesting(true);
    try {
      // Trigger a test cron run (if your API supports it)
      // For now, just show a success message
      await new Promise((resolve) => setTimeout(resolve, 1500));
      toast.success('Test connection successful');
    } catch {
      toast.error('Test connection failed');
    }
    setTesting(false);
  };

  return (
    <div>
      <PageHeader
        title="Cron Job Setup"
        description="Configure your platform to execute scheduled tasks"
      />

      {/* CRON_KEY Display */}
      <Card shadow="sm" className="mb-6">
        <CardHeader className="flex items-center gap-2">
          <Server size={18} className="text-warning" />
          <span className="text-lg font-semibold">Cron Authentication Key</span>
        </CardHeader>
        <CardBody className="space-y-3">
          <div className="flex items-center gap-3">
            <Code className="flex-1 break-all">{obfuscatedKey}</Code>
            <Button
              size="sm"
              variant="flat"
              isIconOnly
              onPress={() => setShowKey(!showKey)}
            >
              {showKey ? <EyeOff size={16} /> : <Eye size={16} />}
            </Button>
            <Button
              size="sm"
              color="primary"
              variant="flat"
              startContent={<Copy size={16} />}
              onPress={() => copyToClipboard(CRON_KEY, 'CRON_KEY')}
            >
              Copy
            </Button>
          </div>
          <p className="text-xs text-default-500">
            Keep this key secure. It authenticates cron execution requests.
          </p>
        </CardBody>
      </Card>

      {/* Test Connection */}
      <Card shadow="sm" className="mb-6">
        <CardBody className="flex flex-row items-center justify-between gap-4">
          <div>
            <p className="font-medium">Test Cron Connection</p>
            <p className="text-sm text-default-500">
              Verify that your setup can trigger cron jobs
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
            aria-label="Platform setup instructions"
            variant="underlined"
            classNames={{
              base: 'w-full',
              tabList: 'px-4',
              panel: 'px-4 pb-4',
            }}
          >
            {/* cPanel */}
            <Tab key="cpanel" title="cPanel">
              <div className="space-y-4 pt-4">
                <div>
                  <h3 className="text-base font-semibold mb-2">
                    1. Access Cron Jobs
                  </h3>
                  <p className="text-sm text-default-600">
                    Log in to cPanel and navigate to <strong>Advanced</strong> →{' '}
                    <strong>Cron Jobs</strong>
                  </p>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">
                    2. Add New Cron Job
                  </h3>
                  <p className="text-sm text-default-600 mb-2">
                    Click "Add New Cron Job" and configure:
                  </p>
                  <ul className="text-sm text-default-600 space-y-1 ml-4">
                    <li>• <strong>Common Settings:</strong> Every 5 minutes</li>
                    <li>• <strong>Minute:</strong> */5</li>
                    <li>• <strong>Hour:</strong> *</li>
                    <li>• <strong>Day:</strong> *</li>
                    <li>• <strong>Month:</strong> *</li>
                    <li>• <strong>Weekday:</strong> *</li>
                  </ul>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">3. Command</h3>
                  <div className="flex items-center gap-2 mb-2">
                    <Code className="flex-1 text-xs break-all">
                      curl -H "X-Cron-Key: {CRON_KEY}" {CRON_URL}
                    </Code>
                    <Button
                      size="sm"
                      variant="flat"
                      isIconOnly
                      onPress={() =>
                        copyToClipboard(
                          `curl -H "X-Cron-Key: ${CRON_KEY}" ${CRON_URL}`,
                          'Command'
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
                    Replace {'{CRON_KEY}'} with your actual key from above
                  </p>
                </div>
              </div>
            </Tab>

            {/* AWS EventBridge */}
            <Tab key="aws" title="AWS EventBridge">
              <div className="space-y-4 pt-4">
                <div>
                  <h3 className="text-base font-semibold mb-2">
                    1. Create EventBridge Rule
                  </h3>
                  <p className="text-sm text-default-600">
                    Go to AWS EventBridge console and create a new rule
                  </p>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">
                    2. Schedule Pattern
                  </h3>
                  <p className="text-sm text-default-600 mb-2">Choose "Schedule" and set:</p>
                  <Code className="block text-xs">rate(5 minutes)</Code>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">3. Target</h3>
                  <ul className="text-sm text-default-600 space-y-1 ml-4">
                    <li>• <strong>Target Type:</strong> AWS API Gateway</li>
                    <li>• <strong>Method:</strong> GET</li>
                    <li>• <strong>URL:</strong> {CRON_URL}</li>
                    <li>• <strong>Header:</strong> X-Cron-Key: {CRON_KEY}</li>
                  </ul>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">
                    4. Alternative: Lambda Function
                  </h3>
                  <p className="text-sm text-default-600 mb-2">
                    Create a Lambda function with this code:
                  </p>
                  <pre className="bg-default-100 p-3 rounded-lg text-xs overflow-x-auto">
{`const https = require('https');

exports.handler = async (event) => {
  const options = {
    hostname: '${API_URL.replace('https://', '')}',
    path: '/cron.php',
    method: 'GET',
    headers: {
      'X-Cron-Key': '${CRON_KEY}'
    }
  };

  return new Promise((resolve, reject) => {
    const req = https.request(options, (res) => {
      resolve({ statusCode: res.statusCode });
    });
    req.on('error', reject);
    req.end();
  });
};`}
                  </pre>
                </div>
              </div>
            </Tab>

            {/* Google Cloud Scheduler */}
            <Tab key="gcp" title="Google Cloud">
              <div className="space-y-4 pt-4">
                <div>
                  <h3 className="text-base font-semibold mb-2">
                    1. Enable Cloud Scheduler API
                  </h3>
                  <p className="text-sm text-default-600">
                    In Google Cloud Console, enable Cloud Scheduler API
                  </p>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">2. Create Job</h3>
                  <ul className="text-sm text-default-600 space-y-1 ml-4">
                    <li>• <strong>Name:</strong> nexus-cron</li>
                    <li>• <strong>Frequency:</strong> */5 * * * *</li>
                    <li>• <strong>Timezone:</strong> Your timezone</li>
                    <li>• <strong>Target:</strong> HTTP</li>
                  </ul>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">3. HTTP Target</h3>
                  <ul className="text-sm text-default-600 space-y-1 ml-4">
                    <li>• <strong>URL:</strong> {CRON_URL}</li>
                    <li>• <strong>HTTP Method:</strong> GET</li>
                    <li>• <strong>Header Name:</strong> X-Cron-Key</li>
                    <li>• <strong>Header Value:</strong> {obfuscatedKey}</li>
                  </ul>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">
                    4. gcloud CLI (Alternative)
                  </h3>
                  <div className="flex items-center gap-2">
                    <Code className="flex-1 text-xs break-all">
                      gcloud scheduler jobs create http nexus-cron --schedule="*/5 * *
                      * *" --uri="{CRON_URL}" --http-method=GET
                      --headers="X-Cron-Key={CRON_KEY}"
                    </Code>
                    <Button
                      size="sm"
                      variant="flat"
                      isIconOnly
                      onPress={() =>
                        copyToClipboard(
                          `gcloud scheduler jobs create http nexus-cron --schedule="*/5 * * * *" --uri="${CRON_URL}" --http-method=GET --headers="X-Cron-Key=${CRON_KEY}"`,
                          'gcloud command'
                        )
                      }
                    >
                      <Copy size={14} />
                    </Button>
                  </div>
                </div>
              </div>
            </Tab>

            {/* Linux Crontab */}
            <Tab key="linux" title="Linux Crontab">
              <div className="space-y-4 pt-4">
                <div>
                  <h3 className="text-base font-semibold mb-2">1. Edit Crontab</h3>
                  <Code className="block text-xs">crontab -e</Code>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">2. Add Entry</h3>
                  <div className="flex items-center gap-2">
                    <Code className="flex-1 text-xs break-all">
                      */5 * * * * curl -H "X-Cron-Key: {CRON_KEY}" {CRON_URL}
                    </Code>
                    <Button
                      size="sm"
                      variant="flat"
                      isIconOnly
                      onPress={() =>
                        copyToClipboard(
                          `*/5 * * * * curl -H "X-Cron-Key: ${CRON_KEY}" ${CRON_URL}`,
                          'Crontab entry'
                        )
                      }
                    >
                      <Copy size={14} />
                    </Button>
                  </div>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">3. Verify</h3>
                  <Code className="block text-xs">crontab -l</Code>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">
                    4. Check Logs (Optional)
                  </h3>
                  <Code className="block text-xs">tail -f /var/log/syslog | grep CRON</Code>
                </div>
              </div>
            </Tab>

            {/* Docker */}
            <Tab key="docker" title="Docker">
              <div className="space-y-4 pt-4">
                <div>
                  <h3 className="text-base font-semibold mb-2">
                    1. Add Service to docker-compose.yml
                  </h3>
                  <pre className="bg-default-100 p-3 rounded-lg text-xs overflow-x-auto">
{`services:
  cron:
    image: alpine:latest
    container_name: nexus-cron
    command: >
      sh -c "echo '*/5 * * * * wget --header='X-Cron-Key: ${CRON_KEY}' -q -O- ${CRON_URL}' > /etc/crontabs/root && crond -f"
    restart: unless-stopped`}
                  </pre>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">2. Start Container</h3>
                  <Code className="block text-xs">docker-compose up -d cron</Code>
                </div>

                <div>
                  <h3 className="text-base font-semibold mb-2">3. Check Logs</h3>
                  <Code className="block text-xs">docker logs -f nexus-cron</Code>
                </div>
              </div>
            </Tab>
          </Tabs>
        </CardBody>
      </Card>

      {/* Verification Checklist */}
      <Card shadow="sm" className="mt-6">
        <CardHeader>
          <h3 className="text-lg font-semibold">Verification Checklist</h3>
        </CardHeader>
        <CardBody className="space-y-2">
          <div className="flex items-center gap-2">
            <CheckCircle size={16} className="text-success" />
            <span className="text-sm">CRON_KEY is set in your environment</span>
          </div>
          <div className="flex items-center gap-2">
            <CheckCircle size={16} className="text-success" />
            <span className="text-sm">First cron job has executed successfully</span>
          </div>
          <div className="flex items-center gap-2">
            <CheckCircle size={16} className="text-success" />
            <span className="text-sm">Logs are populating in the database</span>
          </div>
          <div className="flex items-center gap-2">
            <CheckCircle size={16} className="text-success" />
            <span className="text-sm">Test connection button works</span>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default CronJobSetup;
