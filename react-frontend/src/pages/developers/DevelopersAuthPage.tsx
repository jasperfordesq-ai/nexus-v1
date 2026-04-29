// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AG60 — Developers portal: OAuth2 authentication reference.
 *
 * Documents the client_credentials grant flow with copy-paste curl + JS snippets.
 */

import { useTranslation } from 'react-i18next';
import { Card, CardBody, Tabs, Tab } from '@heroui/react';
import Key from 'lucide-react/icons/key';
import { usePageTitle } from '@/hooks/usePageTitle';

const CURL_SNIPPET = `# 1. Exchange credentials for an access token
curl -X POST https://api.project-nexus.ie/api/partner/v1/oauth/token \\
  -H "Content-Type: application/json" \\
  -d '{
    "grant_type": "client_credentials",
    "client_id": "pk_xxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "client_secret": "sk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "scope": "users.read wallet.read"
  }'

# 2. Use the token on subsequent calls
curl https://api.project-nexus.ie/api/partner/v1/users \\
  -H "Authorization: Bearer at_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"`;

const JS_SNIPPET = `// Node 18+ / modern browser
async function getToken() {
  const res = await fetch('https://api.project-nexus.ie/api/partner/v1/oauth/token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      grant_type: 'client_credentials',
      client_id: process.env.NEXUS_CLIENT_ID,
      client_secret: process.env.NEXUS_CLIENT_SECRET,
      scope: 'users.read wallet.read',
    }),
  });
  if (!res.ok) throw new Error('Auth failed: ' + res.status);
  return res.json(); // { access_token, token_type, expires_in, scope }
}

async function listUsers(accessToken) {
  const res = await fetch('https://api.project-nexus.ie/api/partner/v1/users', {
    headers: { Authorization: \`Bearer \${accessToken}\` },
  });
  return res.json();
}`;

export default function DevelopersAuthPage() {
  const { t } = useTranslation('common');
  usePageTitle(`${t('developers.nav.auth')} - ${t('developers.page_title')}`);

  const steps = [
    { titleKey: 'developers.auth_step1_title', bodyKey: 'developers.auth_step1_body' },
    { titleKey: 'developers.auth_step2_title', bodyKey: 'developers.auth_step2_body' },
    { titleKey: 'developers.auth_step3_title', bodyKey: 'developers.auth_step3_body' },
  ];

  return (
    <div className="max-w-4xl mx-auto px-4 py-10">
      <header className="mb-8">
        <div className="flex items-center gap-3 text-[var(--color-text-muted)] mb-3">
          <Key size={20} />
          <span className="uppercase tracking-wide text-xs font-semibold">
            {t('developers.page_title')}
          </span>
        </div>
        <h1 className="text-3xl font-bold mb-3 text-[var(--color-text)]">
          {t('developers.nav.auth')}
        </h1>
        <p className="text-[var(--color-text-muted)]">{t('developers.auth_intro')}</p>
      </header>

      <section className="space-y-4 mb-10">
        {steps.map(({ titleKey, bodyKey }) => (
          <Card key={titleKey} shadow="sm">
            <CardBody className="p-5">
              <h3 className="font-semibold text-[var(--color-text)] mb-1">{t(titleKey)}</h3>
              <p className="text-sm text-[var(--color-text-muted)]">{t(bodyKey)}</p>
            </CardBody>
          </Card>
        ))}
      </section>

      <section>
        <Tabs aria-label="Code examples">
          <Tab key="curl" title={t('developers.curl_example')}>
            <Card shadow="sm">
              <CardBody className="p-0">
                <pre className="text-xs p-4 overflow-x-auto bg-[var(--color-surface-alt)] text-[var(--color-text)]">
                  <code>{CURL_SNIPPET}</code>
                </pre>
              </CardBody>
            </Card>
          </Tab>
          <Tab key="js" title={t('developers.js_example')}>
            <Card shadow="sm">
              <CardBody className="p-0">
                <pre className="text-xs p-4 overflow-x-auto bg-[var(--color-surface-alt)] text-[var(--color-text)]">
                  <code>{JS_SNIPPET}</code>
                </pre>
              </CardBody>
            </Card>
          </Tab>
        </Tabs>
      </section>
    </div>
  );
}
