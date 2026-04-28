// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import {
  Card,
  CardBody,
  Chip,
  Input,
  Skeleton,
  Tab,
  Tabs,
} from '@heroui/react';
import BadgeCheck from 'lucide-react/icons/badge-check';
import Building2 from 'lucide-react/icons/building-2';
import Mail from 'lucide-react/icons/mail';
import Phone from 'lucide-react/icons/phone';
import Search from 'lucide-react/icons/search';
import Globe from 'lucide-react/icons/globe';
import Heart from 'lucide-react/icons/heart';
import { useTranslation } from 'react-i18next';
import { PageMeta } from '@/components/seo';
import { useTenant } from '@/contexts';
import { useApi } from '@/hooks/useApi';
import { usePageTitle } from '@/hooks';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface CareProvider {
  id: number;
  name: string;
  type: 'spitex' | 'tagesstätte' | 'private' | 'verein' | 'volunteer';
  description: string | null;
  categories: string[] | null;
  address: string | null;
  contact_phone: string | null;
  contact_email: string | null;
  website_url: string | null;
  opening_hours: Record<string, string> | null;
  is_verified: boolean;
}

interface DirectoryResponse {
  data: CareProvider[];
  total: number;
  per_page: number;
  current_page: number;
}

type ProviderType = 'all' | 'spitex' | 'tagesstätte' | 'private' | 'verein' | 'volunteer';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

type ChipColor = 'default' | 'primary' | 'secondary' | 'success' | 'warning' | 'danger';

function typeChipColor(type: CareProvider['type']): ChipColor {
  switch (type) {
    case 'spitex':       return 'primary';
    case 'tagesstätte':  return 'secondary';
    case 'private':      return 'warning';
    case 'verein':       return 'success';
    case 'volunteer':    return 'danger';
    default:             return 'default';
  }
}

// ---------------------------------------------------------------------------
// Sub-components
// ---------------------------------------------------------------------------

function ProviderCardSkeleton() {
  return (
    <Card className="p-4">
      <CardBody className="gap-3">
        <div className="flex items-start justify-between gap-3">
          <Skeleton className="h-5 w-2/3 rounded-lg" />
          <Skeleton className="h-6 w-20 rounded-full" />
        </div>
        <Skeleton className="h-4 w-full rounded-lg" />
        <Skeleton className="h-4 w-5/6 rounded-lg" />
        <div className="flex gap-3 pt-1">
          <Skeleton className="h-4 w-1/3 rounded-lg" />
          <Skeleton className="h-4 w-1/3 rounded-lg" />
        </div>
      </CardBody>
    </Card>
  );
}

interface ProviderCardProps {
  provider: CareProvider;
  t: (key: string) => string;
}

function ProviderCard({ provider, t }: ProviderCardProps) {
  return (
    <Card className="p-1 hover:shadow-md transition-shadow">
      <CardBody className="gap-2.5">
        {/* Header */}
        <div className="flex flex-wrap items-start justify-between gap-2">
          <div className="flex items-center gap-2 min-w-0">
            {provider.is_verified && (
              <BadgeCheck
                className="h-4 w-4 shrink-0 text-primary"
                aria-label={t('caring_community.providers.verified_badge')}
              />
            )}
            <h3 className="font-semibold text-theme-primary truncate">{provider.name}</h3>
          </div>
          <Chip size="sm" color={typeChipColor(provider.type)} variant="flat" className="shrink-0">
            {provider.type}
          </Chip>
        </div>

        {/* Description */}
        {provider.description && (
          <p className="text-sm text-theme-muted line-clamp-2">{provider.description}</p>
        )}

        {/* Address */}
        {provider.address && (
          <div className="flex items-start gap-1.5 text-sm text-theme-muted">
            <Building2 className="h-3.5 w-3.5 mt-0.5 shrink-0" aria-hidden="true" />
            <span>{provider.address}</span>
          </div>
        )}

        {/* Contact row */}
        <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
          {provider.contact_phone && (
            <a
              href={`tel:${provider.contact_phone}`}
              className="flex items-center gap-1.5 text-theme-muted hover:text-primary transition-colors"
            >
              <Phone className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
              <span>{provider.contact_phone}</span>
            </a>
          )}
          {provider.contact_email && (
            <a
              href={`mailto:${provider.contact_email}`}
              className="flex items-center gap-1.5 text-theme-muted hover:text-primary transition-colors"
            >
              <Mail className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
              <span className="truncate max-w-[180px]">{provider.contact_email}</span>
            </a>
          )}
          {provider.website_url && (
            <a
              href={provider.website_url}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-1.5 text-theme-muted hover:text-primary transition-colors"
            >
              <Globe className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
              <span>{t('caring_community.providers.website')}</span>
            </a>
          )}
        </div>

        {/* Verified badge strip */}
        {provider.is_verified && (
          <div className="flex items-center gap-1.5 text-xs text-primary font-medium pt-0.5">
            <BadgeCheck className="h-3.5 w-3.5" aria-hidden="true" />
            {t('caring_community.providers.verified_badge')}
          </div>
        )}
      </CardBody>
    </Card>
  );
}

// ---------------------------------------------------------------------------
// Main page
// ---------------------------------------------------------------------------

const FILTER_TABS: { key: ProviderType; labelKey: string }[] = [
  { key: 'all',          labelKey: 'caring_community.providers.filter_all' },
  { key: 'spitex',       labelKey: 'caring_community.providers.filter_spitex' },
  { key: 'tagesstätte',  labelKey: 'caring_community.providers.filter_tagesstte' },
  { key: 'private',      labelKey: 'caring_community.providers.filter_private' },
  { key: 'verein',       labelKey: 'caring_community.providers.filter_verein' },
  { key: 'volunteer',    labelKey: 'caring_community.providers.filter_volunteer' },
];

export default function CareProviderDirectoryPage() {
  const { t } = useTranslation('caring_community');
  const { hasFeature } = useTenant();
  usePageTitle(t('caring_community.providers.title'));

  const [activeType, setActiveType] = useState<ProviderType>('all');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');

  // Debounce search input
  const handleSearchChange = (value: string) => {
    setSearch(value);
    const timer = setTimeout(() => setDebouncedSearch(value), 350);
    return () => clearTimeout(timer);
  };

  // Build query string
  const params = new URLSearchParams();
  if (activeType !== 'all') params.set('type', activeType);
  if (debouncedSearch) params.set('search', debouncedSearch);

  const queryString = params.toString();
  const apiPath = `/v2/caring-community/providers${queryString ? `?${queryString}` : ''}`;

  const { data: response, loading, error } = useApi<DirectoryResponse>(apiPath);

  if (!hasFeature('caring_community')) {
    return null;
  }

  const providers = response?.data ?? [];

  return (
    <>
      <PageMeta
        title={t('caring_community.providers.title')}
        description={t('caring_community.providers.subtitle')}
      />

      <div className="mx-auto max-w-5xl px-4 py-8 space-y-6">
        {/* Page header */}
        <div className="space-y-1">
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-2">
            <Heart className="h-6 w-6 text-primary" aria-hidden="true" />
            {t('caring_community.providers.title')}
          </h1>
          <p className="text-theme-muted">{t('caring_community.providers.subtitle')}</p>
        </div>

        {/* Search */}
        <Input
          placeholder={t('caring_community.providers.search_placeholder')}
          value={search}
          onValueChange={handleSearchChange}
          startContent={<Search className="h-4 w-4 text-default-400" aria-hidden="true" />}
          variant="bordered"
          classNames={{ inputWrapper: 'max-w-md' }}
          isClearable
          onClear={() => { setSearch(''); setDebouncedSearch(''); }}
        />

        {/* Type filter tabs */}
        <Tabs
          selectedKey={activeType}
          onSelectionChange={(key) => setActiveType(key as ProviderType)}
          variant="underlined"
          classNames={{ tabList: 'gap-2 flex-wrap' }}
          aria-label="Filter providers by type"
        >
          {FILTER_TABS.map(({ key, labelKey }) => (
            <Tab key={key} title={t(labelKey)} />
          ))}
        </Tabs>

        {/* Results */}
        {loading ? (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {Array.from({ length: 6 }).map((_, i) => (
              <ProviderCardSkeleton key={i} />
            ))}
          </div>
        ) : error ? (
          <div className="rounded-xl border border-danger/30 bg-danger/5 p-6 text-center text-danger">
            {t('caring_community.providers.no_providers')}
          </div>
        ) : providers.length === 0 ? (
          <div className="flex flex-col items-center gap-3 py-16 text-center text-theme-muted">
            <Heart className="h-12 w-12 opacity-30" aria-hidden="true" />
            <p className="text-lg font-medium">{t('caring_community.providers.no_providers')}</p>
            <p className="text-sm">{t('caring_community.providers.no_providers_hint')}</p>
          </div>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {providers.map((provider) => (
              <ProviderCard key={provider.id} provider={provider} t={t} />
            ))}
          </div>
        )}
      </div>
    </>
  );
}
