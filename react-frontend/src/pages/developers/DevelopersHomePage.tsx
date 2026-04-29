// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AG60 — Developers portal home page.
 *
 * Public landing page for the Partner API. Linked from the footer. Explains
 * what the API can do and links to auth / endpoints / webhooks reference.
 */

import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Card, CardBody, Button } from '@heroui/react';
import Code from 'lucide-react/icons/code';
import Key from 'lucide-react/icons/key';
import Webhook from 'lucide-react/icons/webhook';
import BookOpen from 'lucide-react/icons/book-open';
import Gauge from 'lucide-react/icons/gauge';
import ArrowRight from 'lucide-react/icons/arrow-right';
import { usePageTitle } from '@/hooks/usePageTitle';

export default function DevelopersHomePage() {
  const { t } = useTranslation('common');
  usePageTitle(t('developers.page_title'));

  const features = [
    { icon: Key, titleKey: 'developers.feature_oauth_title', bodyKey: 'developers.feature_oauth_body' },
    { icon: Code, titleKey: 'developers.feature_curated_title', bodyKey: 'developers.feature_curated_body' },
    { icon: Webhook, titleKey: 'developers.feature_webhooks_title', bodyKey: 'developers.feature_webhooks_body' },
    { icon: Gauge, titleKey: 'developers.feature_rate_limit_title', bodyKey: 'developers.feature_rate_limit_body' },
  ];

  const navLinks = [
    { to: '/developers/auth', icon: Key, key: 'developers.nav.auth' },
    { to: '/developers/endpoints', icon: BookOpen, key: 'developers.nav.endpoints' },
    { to: '/developers/webhooks', icon: Webhook, key: 'developers.nav.webhooks' },
  ];

  return (
    <div className="max-w-5xl mx-auto px-4 py-10">
      <header className="mb-10">
        <div className="flex items-center gap-3 text-[var(--color-text-muted)] mb-3">
          <Code size={20} />
          <span className="uppercase tracking-wide text-xs font-semibold">
            {t('developers.page_title')}
          </span>
        </div>
        <h1 className="text-4xl font-bold mb-4 text-[var(--color-text)]">
          {t('developers.hero_title')}
        </h1>
        <p className="text-lg text-[var(--color-text-muted)] max-w-3xl">
          {t('developers.hero_subtitle')}
        </p>
      </header>

      <section className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-10">
        {features.map(({ icon: Icon, titleKey, bodyKey }) => (
          <Card key={titleKey} shadow="sm">
            <CardBody className="p-5">
              <div className="flex items-start gap-3">
                <Icon size={22} className="text-[var(--color-primary)] mt-0.5 shrink-0" />
                <div>
                  <h3 className="font-semibold text-[var(--color-text)] mb-1">
                    {t(titleKey)}
                  </h3>
                  <p className="text-sm text-[var(--color-text-muted)]">{t(bodyKey)}</p>
                </div>
              </div>
            </CardBody>
          </Card>
        ))}
      </section>

      <section className="mb-10">
        <Card shadow="sm" className="bg-[var(--color-surface-alt)]">
          <CardBody className="p-6">
            <h2 className="text-xl font-semibold mb-2 text-[var(--color-text)]">
              {t('developers.request_access_cta')}
            </h2>
            <p className="text-[var(--color-text-muted)] mb-4">
              {t('developers.request_access_body')}
            </p>
            <Button as={Link} to="/contact" color="primary" endContent={<ArrowRight size={16} />}>
              {t('developers.request_access_cta')}
            </Button>
          </CardBody>
        </Card>
      </section>

      <section>
        <h2 className="text-xl font-semibold mb-4 text-[var(--color-text)]">
          {t('developers.nav.overview')}
        </h2>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          {navLinks.map(({ to, icon: Icon, key }) => (
            <Card key={to} as={Link} to={to} isPressable shadow="sm" className="block">
              <CardBody className="p-4 flex flex-row items-center gap-3">
                <Icon size={20} className="text-[var(--color-primary)]" />
                <span className="font-medium text-[var(--color-text)]">{t(key)}</span>
                <ArrowRight size={16} className="ml-auto text-[var(--color-text-muted)]" />
              </CardBody>
            </Card>
          ))}
        </div>
      </section>
    </div>
  );
}
