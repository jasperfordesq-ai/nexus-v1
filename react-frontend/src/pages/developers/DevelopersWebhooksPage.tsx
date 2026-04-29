// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AG60 — Developers portal: webhook signing reference.
 *
 * Documents the HMAC-SHA256 signature scheme used by PartnerWebhookDispatcher
 * and provides a copy-paste verification snippet.
 */

import { useTranslation } from 'react-i18next';
import { Card, CardBody, Tabs, Tab } from '@heroui/react';
import Webhook from 'lucide-react/icons/webhook';
import { usePageTitle } from '@/hooks/usePageTitle';

const NODE_VERIFY = `// Verify an inbound NEXUS webhook (Node 18+)
import crypto from 'crypto';

function verifySignature(rawBody, signatureHeader, secret) {
  const expected = crypto
    .createHmac('sha256', secret)
    .update(rawBody)
    .digest('hex');
  // Constant-time comparison
  const a = Buffer.from(expected);
  const b = Buffer.from(signatureHeader);
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

// Express handler
app.post('/webhooks/nexus', express.raw({ type: 'application/json' }), (req, res) => {
  const sig = req.header('X-Partner-Signature') || '';
  if (!verifySignature(req.body, sig, process.env.NEXUS_WEBHOOK_SECRET)) {
    return res.status(401).send('invalid signature');
  }
  const event = JSON.parse(req.body.toString());
  // event.event_type === 'wallet.credited'
  // event.data === { transaction_id, user_id, hours, reference }
  res.status(200).send('ok');
});`;

const PHP_VERIFY = `<?php
// Verify an inbound NEXUS webhook (PHP 8+)
$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_PARTNER_SIGNATURE'] ?? '';
$secret = getenv('NEXUS_WEBHOOK_SECRET');

$expected = hash_hmac('sha256', $rawBody, $secret);
if (! hash_equals($expected, $signature)) {
    http_response_code(401);
    echo 'invalid signature';
    exit;
}

$event = json_decode($rawBody, true);
// $event['event_type'] === 'wallet.credited'
http_response_code(200);
echo 'ok';`;

export default function DevelopersWebhooksPage() {
  const { t } = useTranslation('common');
  usePageTitle(`${t('developers.nav.webhooks')} - ${t('developers.page_title')}`);

  return (
    <div className="max-w-4xl mx-auto px-4 py-10">
      <header className="mb-8">
        <div className="flex items-center gap-3 text-[var(--color-text-muted)] mb-3">
          <Webhook size={20} />
          <span className="uppercase tracking-wide text-xs font-semibold">
            {t('developers.page_title')}
          </span>
        </div>
        <h1 className="text-3xl font-bold mb-3 text-[var(--color-text)]">
          {t('developers.nav.webhooks')}
        </h1>
        <p className="text-[var(--color-text-muted)]">{t('developers.webhooks_intro')}</p>
      </header>

      <section className="mb-8">
        <Card shadow="sm">
          <CardBody className="p-5">
            <h2 className="text-lg font-semibold mb-2 text-[var(--color-text)]">
              {t('developers.webhook_events_title')}
            </h2>
            <ul className="text-sm text-[var(--color-text-muted)] list-disc pl-5">
              <li>{t('developers.webhook_event_wallet_credited')}</li>
            </ul>
          </CardBody>
        </Card>
      </section>

      <section className="mb-8">
        <Card shadow="sm">
          <CardBody className="p-5">
            <h2 className="text-lg font-semibold mb-2 text-[var(--color-text)]">
              {t('developers.webhook_create_title')}
            </h2>
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('developers.webhook_create_body')}
            </p>
          </CardBody>
        </Card>
      </section>

      <section>
        <h2 className="text-lg font-semibold mb-3 text-[var(--color-text)]">
          {t('developers.webhook_signing_title')}
        </h2>
        <p className="text-sm text-[var(--color-text-muted)] mb-3">
          {t('developers.webhook_signing_body')}
        </p>
        <Tabs aria-label="Verification examples">
          <Tab key="node" title="Node.js">
            <Card shadow="sm">
              <CardBody className="p-0">
                <pre className="text-xs p-4 overflow-x-auto bg-[var(--color-surface-alt)] text-[var(--color-text)]">
                  <code>{NODE_VERIFY}</code>
                </pre>
              </CardBody>
            </Card>
          </Tab>
          <Tab key="php" title="PHP">
            <Card shadow="sm">
              <CardBody className="p-0">
                <pre className="text-xs p-4 overflow-x-auto bg-[var(--color-surface-alt)] text-[var(--color-text)]">
                  <code>{PHP_VERIFY}</code>
                </pre>
              </CardBody>
            </Card>
          </Tab>
        </Tabs>
      </section>
    </div>
  );
}
