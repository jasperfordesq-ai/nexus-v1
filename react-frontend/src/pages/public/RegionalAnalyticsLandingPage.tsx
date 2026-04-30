// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AG59 — Public marketing landing page for the Paid Regional Analytics
 * product. Pitches the offering to municipalities and SME partners and
 * routes interest into the existing /contact form.
 */

import { Button, Card, CardBody, Chip } from '@heroui/react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import BarChart3 from 'lucide-react/icons/bar-chart-3';
import Download from 'lucide-react/icons/download';
import MapPin from 'lucide-react/icons/map-pin';
import ShieldCheck from 'lucide-react/icons/shield-check';
import TrendingUp from 'lucide-react/icons/trending-up';
import Users from 'lucide-react/icons/users';
import { usePageTitle } from '@/hooks';

export default function RegionalAnalyticsLandingPage() {
  const { t } = useTranslation('common');
  usePageTitle('Regional Analytics');

  const features = [
    {
      icon: TrendingUp,
      title: t('regional_analytics.feature_trends_title', 'Trends'),
      body: t(
        'regional_analytics.feature_trends_body',
        'Track engagement, member activity, and volunteer hours over time — no individuals, just regional aggregates.',
      ),
    },
    {
      icon: MapPin,
      title: t('regional_analytics.feature_demand_supply_title', 'Demand & Supply'),
      body: t(
        'regional_analytics.feature_demand_supply_body',
        'See where offers and requests cluster by 3-digit postcode and category, with a privacy-bucketed match rate.',
      ),
    },
    {
      icon: Users,
      title: t('regional_analytics.feature_demographics_title', 'Demographics'),
      body: t(
        'regional_analytics.feature_demographics_body',
        'Age and gender distribution, suppressed below N=10 — perfect for grant applications and impact reporting.',
      ),
    },
    {
      icon: BarChart3,
      title: t('regional_analytics.feature_footfall_title', 'Footfall'),
      body: t(
        'regional_analytics.feature_footfall_body',
        'Anonymised page-view trends across public areas of the platform — measure attention, not identity.',
      ),
    },
  ];

  const tiers: Array<{
    key: 'basic' | 'pro' | 'enterprise';
    price: string;
    description: string;
    features: string[];
  }> = [
    {
      key: 'basic',
      price: t('regional_analytics.tiers.basic.price', '€299 / month'),
      description: t(
        'regional_analytics.tiers.basic.description',
        'For small municipalities and community partners.',
      ),
      features: [
        t('regional_analytics.tiers.basic.feature_1', 'Trends + Demographics modules'),
        t('regional_analytics.tiers.basic.feature_2', 'Monthly PDF report'),
        t('regional_analytics.tiers.basic.feature_3', 'Email support'),
      ],
    },
    {
      key: 'pro',
      price: t('regional_analytics.tiers.pro.price', '€799 / month'),
      description: t(
        'regional_analytics.tiers.pro.description',
        'For mid-sized municipalities and SME partners with multiple stakeholders.',
      ),
      features: [
        t('regional_analytics.tiers.pro.feature_1', 'All four modules'),
        t('regional_analytics.tiers.pro.feature_2', 'Weekly PDF + on-demand reports'),
        t('regional_analytics.tiers.pro.feature_3', 'Live partner dashboard'),
        t('regional_analytics.tiers.pro.feature_4', 'Priority support'),
      ],
    },
    {
      key: 'enterprise',
      price: t('regional_analytics.tiers.enterprise.price', 'Custom pricing'),
      description: t(
        'regional_analytics.tiers.enterprise.description',
        'For city governments and federated networks. Custom modules, custom branding, dedicated success manager.',
      ),
      features: [
        t('regional_analytics.tiers.enterprise.feature_1', 'Custom data feeds & exports'),
        t('regional_analytics.tiers.enterprise.feature_2', 'White-labelled PDFs'),
        t('regional_analytics.tiers.enterprise.feature_3', 'Quarterly review workshops'),
        t('regional_analytics.tiers.enterprise.feature_4', 'Dedicated success manager'),
      ],
    },
  ];

  const contactSubject = encodeURIComponent('Regional Analytics — Partner enquiry');

  return (
    <div className="max-w-6xl mx-auto px-6 py-12 space-y-16">
      {/* Hero */}
      <section className="text-center space-y-4">
        <Chip color="primary" variant="flat" startContent={<ShieldCheck size={14} className="ml-1" />}>
          {t('regional_analytics.privacy_badge', 'Privacy-first by design')}
        </Chip>
        <h1 className="text-4xl md:text-5xl font-bold tracking-tight">
          {t('regional_analytics.hero_title', 'Understand your region. Without compromising privacy.')}
        </h1>
        <p className="text-lg text-[var(--color-text-muted)] max-w-2xl mx-auto">
          {t(
            'regional_analytics.hero_subtitle',
            'A paid analytics product for municipalities and SME partners. Bucketed aggregates, suppressed segments below N=10, never any individual data.',
          )}
        </p>
        <div className="flex justify-center gap-3 pt-2">
          <Button as={Link} to={`/contact?subject=${contactSubject}`} color="primary" size="lg">
            {t('regional_analytics.cta_request_access', 'Request access')}
          </Button>
          <Button as={Link} to="/about" variant="flat" size="lg">
            {t('regional_analytics.cta_learn_more', 'Learn more')}
          </Button>
        </div>
      </section>

      {/* Features */}
      <section>
        <h2 className="text-2xl font-semibold text-center mb-6">
          {t('regional_analytics.features_heading', "What's included")}
        </h2>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {features.map((f) => {
            const Icon = f.icon;
            return (
              <Card key={f.title} shadow="sm">
                <CardBody className="flex flex-row gap-4 p-5">
                  <div className="rounded-lg bg-primary-100 dark:bg-primary-900/30 p-3 h-fit">
                    <Icon size={22} className="text-primary-600 dark:text-primary-400" />
                  </div>
                  <div>
                    <h3 className="font-semibold mb-1">{f.title}</h3>
                    <p className="text-sm text-[var(--color-text-muted)]">{f.body}</p>
                  </div>
                </CardBody>
              </Card>
            );
          })}
        </div>
      </section>

      {/* Pricing */}
      <section>
        <h2 className="text-2xl font-semibold text-center mb-6">
          {t('regional_analytics.pricing_heading', 'Simple, transparent pricing')}
        </h2>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {tiers.map((tier) => (
            <Card key={tier.key} shadow={tier.key === 'pro' ? 'lg' : 'sm'} className={tier.key === 'pro' ? 'border-2 border-primary-500' : ''}>
              <CardBody className="p-6">
                <Chip size="sm" variant="flat" color={tier.key === 'pro' ? 'primary' : 'default'} className="mb-3">
                  {t(`regional_analytics.tiers.${tier.key}.label`, tier.key)}
                </Chip>
                <div className="text-2xl font-bold mb-1">{tier.price}</div>
                <p className="text-sm text-[var(--color-text-muted)] mb-4">{tier.description}</p>
                <ul className="space-y-2 mb-5">
                  {tier.features.map((f, i) => (
                    <li key={i} className="text-sm flex items-start gap-2">
                      <span className="text-primary-500">•</span>
                      <span>{f}</span>
                    </li>
                  ))}
                </ul>
                <Button
                  as={Link}
                  to={`/contact?subject=${contactSubject}%20-%20${tier.key}`}
                  color={tier.key === 'pro' ? 'primary' : 'default'}
                  variant={tier.key === 'pro' ? 'solid' : 'flat'}
                  fullWidth
                  startContent={<Download size={16} />}
                >
                  {t('regional_analytics.tier_cta', 'Request a quote')}
                </Button>
              </CardBody>
            </Card>
          ))}
        </div>
      </section>

      {/* Privacy footer */}
      <section className="text-center pt-8 border-t border-[var(--color-border)]">
        <ShieldCheck size={32} className="mx-auto text-[var(--color-text-muted)] mb-3" />
        <p className="text-sm text-[var(--color-text-muted)] max-w-2xl mx-auto">
          {t(
            'regional_analytics.privacy_footer',
            'All metrics are bucketed and anonymised. Segments with N<10 are suppressed. We never expose individual users, names, or addresses to partners.',
          )}
        </p>
      </section>
    </div>
  );
}
