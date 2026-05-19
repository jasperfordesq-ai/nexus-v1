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
import { PageMeta } from '@/components/seo';

interface Endpoint {
  method: 'GET' | 'POST';
  path: string;
  scope?: string;
  descriptionKey: string;
}

const ENDPOINTS: Endpoint[] = [
  { method: 'POST', path: '/api/partner/v1/oauth/token', descriptionKey: 'oauth_token' },
  { method: 'POST', path: '/api/partner/v1/oauth/revoke', descriptionKey: 'oauth_revoke' },
  { method: 'GET', path: '/api/partner/v1/users', scope: 'users.read', descriptionKey: 'users_list' },
  { method: 'GET', path: '/api/partner/v1/users/{id}', scope: 'users.read', descriptionKey: 'users_detail' },
  { method: 'GET', path: '/api/partner/v1/listings', scope: 'listings.read', descriptionKey: 'listings_list' },
  { method: 'GET', path: '/api/partner/v1/wallet/balance/{userId}', scope: 'wallet.read', descriptionKey: 'wallet_balance' },
  { method: 'POST', path: '/api/partner/v1/wallet/credit', scope: 'wallet.write', descriptionKey: 'wallet_credit' },
  { method: 'GET', path: '/api/partner/v1/aggregates/community', scope: 'aggregates.read', descriptionKey: 'aggregates_community' },
  { method: 'GET', path: '/api/partner/v1/webhooks/subscriptions', scope: 'webhooks.manage', descriptionKey: 'webhooks_list' },
  { method: 'POST', path: '/api/partner/v1/webhooks/subscriptions', scope: 'webhooks.manage', descriptionKey: 'webhooks_create' },
];

function methodColor(m: 'GET' | 'POST'): 'success' | 'primary' {
  return m === 'GET' ? 'success' : 'primary';
}

export default function DevelopersEndpointsPage() {
  const { t } = useTranslation('common');

  return (
    <div className="max-w-5xl mx-auto px-4 py-10">
      <PageMeta title={t('developers.endpoints_meta_title')} description={t('developers.endpoints_intro')} />
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
          <Table aria-label={t('developers.endpoints_table_aria')} removeWrapper>
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
                      {e.scope ?? t('empty_dash')}
                    </Chip>
                  </TableCell>
                  <TableCell className="text-sm text-[var(--color-text-muted)]">
                    {t(`developers.endpoint_descriptions.${e.descriptionKey}`)}
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
