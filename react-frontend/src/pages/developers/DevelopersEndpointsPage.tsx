// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AG60 — Developers portal: endpoint reference.
 *
 * Hand-picked list of v1 partner endpoints. Mirrors the routes registered in
 * routes/api.php under the `partner.api:<scope>` middleware group, along with
 * the public OAuth endpoints. Update both lists when adding new partner
 * endpoints.
 */

import { useTranslation } from 'react-i18next';
import { Card, CardBody, Chip, Table, TableHeader, TableColumn, TableBody, TableRow, TableCell } from '@heroui/react';
import BookOpen from 'lucide-react/icons/book-open';
import { usePageTitle } from '@/hooks/usePageTitle';

interface Endpoint {
  method: 'GET' | 'POST';
  path: string;
  scope: string;
  description: string;
}

const ENDPOINTS: Endpoint[] = [
  { method: 'POST', path: '/api/partner/v1/oauth/token', scope: '—', description: 'Exchange client credentials for a bearer token (client_credentials grant).' },
  { method: 'POST', path: '/api/partner/v1/oauth/revoke', scope: '—', description: 'Revoke an issued bearer token (RFC 7009).' },
  { method: 'GET', path: '/api/partner/v1/users', scope: 'users.read', description: 'List active members. Add scope users.pii to receive email.' },
  { method: 'GET', path: '/api/partner/v1/users/{id}', scope: 'users.read', description: 'Fetch a single member by ID.' },
  { method: 'GET', path: '/api/partner/v1/listings', scope: 'listings.read', description: 'List active listings (services and requests).' },
  { method: 'GET', path: '/api/partner/v1/wallet/balance/{userId}', scope: 'wallet.read', description: 'Read a member\'s time-credit balance in hours.' },
  { method: 'POST', path: '/api/partner/v1/wallet/credit', scope: 'wallet.write', description: 'Credit time hours from a settled bank transfer. Body: user_id, hours, reference, note?' },
  { method: 'GET', path: '/api/partner/v1/aggregates/community', scope: 'aggregates.read', description: 'Bucketed community totals (no PII). Useful for partner dashboards.' },
  { method: 'GET', path: '/api/partner/v1/webhooks/subscriptions', scope: 'webhooks.manage', description: 'List your webhook subscriptions.' },
  { method: 'POST', path: '/api/partner/v1/webhooks/subscriptions', scope: 'webhooks.manage', description: 'Subscribe to events. Body: event_types[], target_url (https://).' },
];

function methodColor(m: 'GET' | 'POST'): 'success' | 'primary' {
  return m === 'GET' ? 'success' : 'primary';
}

export default function DevelopersEndpointsPage() {
  const { t } = useTranslation('common');
  usePageTitle(`${t('developers.nav.endpoints')} - ${t('developers.page_title')}`);

  return (
    <div className="max-w-5xl mx-auto px-4 py-10">
      <header className="mb-8">
        <div className="flex items-center gap-3 text-[var(--color-text-muted)] mb-3">
          <BookOpen size={20} />
          <span className="uppercase tracking-wide text-xs font-semibold">
            {t('developers.page_title')}
          </span>
        </div>
        <h1 className="text-3xl font-bold mb-3 text-[var(--color-text)]">
          {t('developers.nav.endpoints')}
        </h1>
        <p className="text-[var(--color-text-muted)]">{t('developers.endpoints_intro')}</p>
      </header>

      <Card shadow="sm">
        <CardBody className="p-0">
          <Table aria-label="Partner API endpoints" removeWrapper>
            <TableHeader>
              <TableColumn>{t('developers.method_label')}</TableColumn>
              <TableColumn>{t('developers.path_label')}</TableColumn>
              <TableColumn>{t('developers.scope_label')}</TableColumn>
              <TableColumn>{t('developers.description_label')}</TableColumn>
            </TableHeader>
            <TableBody>
              {ENDPOINTS.map((e) => (
                <TableRow key={`${e.method}-${e.path}`}>
                  <TableCell>
                    <Chip size="sm" color={methodColor(e.method)} variant="flat">
                      {e.method}
                    </Chip>
                  </TableCell>
                  <TableCell className="font-mono text-xs">{e.path}</TableCell>
                  <TableCell>
                    <Chip size="sm" variant="flat">
                      {e.scope}
                    </Chip>
                  </TableCell>
                  <TableCell className="text-sm text-[var(--color-text-muted)]">
                    {e.description}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardBody>
      </Card>
    </div>
  );
}
