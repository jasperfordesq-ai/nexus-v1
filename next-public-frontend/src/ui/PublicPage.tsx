// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ReactNode } from 'react';
import Image from 'next/image';
import { Button, Card, Chip, Link } from '@heroui/react';
import { BriefcaseBusiness, CalendarDays, Heart, MapPin, ShoppingBag, Star, UserRound } from 'lucide-react';

import { resolveAssetUrl } from '../lib/assets';
import type { Translator } from '../lib/i18n';
import type { RouteOwnership } from '../lib/public-routes';
import type {
  BlogPostSummary,
  PublicContentItem,
  PublicEvent,
  PublicEventsIndex,
  PublicJob,
  PublicJobsIndex,
  PublicListing,
  PublicListingsIndex,
  PublicMarketplaceIndex,
  PublicMarketplaceListing,
  PublicOrganisation,
  PublicOrganisationsIndex,
  PublicRouteContent,
  TenantBootstrap,
} from '../lib/tenant-api';
import { getApiBase } from '../lib/tenant-api';
import { PublicChrome } from './PublicChrome';

interface PublicPageProps {
  canonicalUrl: string;
  content?: PublicRouteContent | null;
  route: RouteOwnership;
  routeSegments: string[];
  tenant: TenantBootstrap | null;
  tenantBasePath: string;
  t: Translator;
}

const homeHeroLinks = [
  { href: 'listings', labelKey: 'navigation.listings' },
  { href: 'events', labelKey: 'navigation.events' },
  { href: 'resources', labelKey: 'navigation.resources' },
];

export function PublicPage({
  canonicalUrl,
  content = null,
  route,
  routeSegments,
  tenant,
  tenantBasePath,
  t,
}: PublicPageProps): ReactNode {
  const tenantName = tenant?.name || t('brand.platformName');
  const tagline = tenant?.tagline || t('pages.home.fallbackTagline');
  const logoUrl = resolveAssetUrl(tenant?.branding?.logo_url, getApiBase());
  const pageTitle = getRouteTitle(route, content, t);
  const pageLead = getRouteLead(route, tenantName, t, content);
  const isHome = route.routeKey === 'home';

  return (
    <PublicChrome canonicalUrl={canonicalUrl} tenant={tenant} tenantBasePath={tenantBasePath} t={t}>
      <StructuredData
        canonicalUrl={canonicalUrl}
        content={content}
        pageTitle={pageTitle}
        tenant={tenant}
        tenantName={tenantName}
      />
      <section
        className={`mx-auto grid w-full max-w-7xl gap-8 px-4 py-10 sm:px-6 lg:px-8 lg:py-14 ${
          isHome ? 'lg:grid-cols-[minmax(0,1fr)_minmax(280px,380px)] lg:items-center' : ''
        }`}
      >
        <div className="min-w-0">
          <Chip className="mb-4" color="accent" size="sm" variant="soft">
            {isHome ? t('pages.home.eyebrow') : tenantName}
          </Chip>
          <h1 className="max-w-4xl text-4xl font-bold leading-tight text-theme-primary sm:text-5xl lg:text-6xl">
            {pageTitle}
          </h1>
          <p className="mt-5 max-w-3xl text-lg leading-8 text-theme-muted">{pageLead}</p>
          {isHome ? (
            <div className="mt-7 flex flex-wrap gap-3">
              <Link
                className="button button--primary button--md"
                href={withTenantBase(tenantBasePath, 'contact')}
              >
                {t('pages.home.primaryAction')}
              </Link>
              <Link
                className="button button--outline button--md"
                href={withTenantBase(tenantBasePath, 'blog')}
              >
                {t('pages.home.secondaryAction')}
              </Link>
            </div>
          ) : null}
        </div>
        {isHome ? (
          <Card
            aria-label={t('pages.home.sectionTitle')}
            className="border border-theme-default bg-theme-card shadow-lg"
            variant="default"
          >
            <Card.Header className="pb-3">
              <BrandMark logoUrl={logoUrl} tagline={tagline} tenantName={tenantName} />
            </Card.Header>
            <Card.Content className="space-y-5 pt-0">
              <p className="leading-7 text-theme-muted">{t('pages.home.sectionBody')}</p>
              <div className="grid gap-2">
                {homeHeroLinks.map((item) => (
                  <Link
                    className="rounded-lg border border-theme-default bg-theme-elevated px-3 py-2.5 font-bold text-theme-primary hover:bg-theme-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--accent)]"
                    href={withTenantBase(tenantBasePath, item.href)}
                    key={item.labelKey}
                  >
                    {t(item.labelKey)}
                  </Link>
                ))}
              </div>
            </Card.Content>
          </Card>
        ) : null}
      </section>

      <section className="mx-auto w-full max-w-7xl px-4 pb-8 sm:px-6 lg:px-8">
        {renderRouteContent(route, routeSegments, content, tenantName, tenantBasePath, t)}
      </section>
    </PublicChrome>
  );
}

function BrandMark({
  logoUrl,
  tagline,
  tenantName,
}: {
  logoUrl: string | undefined;
  tagline: string;
  tenantName: string;
}): ReactNode {
  return (
    <div className="flex min-w-0 items-center gap-3">
      <BrandLogo logoUrl={logoUrl} tenantName={tenantName} />
      <div className="min-w-0">
        <strong className="block truncate text-base font-bold text-[color:var(--nexus-ink)]">{tenantName}</strong>
        <span className="block truncate text-sm text-[color:var(--nexus-muted)]">{tagline}</span>
      </div>
    </div>
  );
}

function BrandLogo({ logoUrl, tenantName }: { logoUrl: string | undefined; tenantName: string }): ReactNode {
  if (!logoUrl) {
    return (
      <span
        aria-hidden="true"
        className="block size-12 shrink-0 rounded-lg border border-[color:var(--nexus-border)] bg-[color:var(--nexus-accent-soft)]"
      />
    );
  }

  return (
    <Image
      alt={tenantName}
      className="size-12 shrink-0 rounded-lg border border-[color:var(--nexus-border)] bg-[color:var(--nexus-surface)] object-contain p-1"
      height={96}
      src={logoUrl}
      unoptimized
      width={96}
    />
  );
}

interface RichImage {
  altText?: string | null;
  sortOrder?: number;
  url: string;
}

interface RichFact {
  label: string;
  value?: null | string;
}

const publicFamilyGridClassName = 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3';
const marketplaceGridClassName = 'grid grid-cols-1 min-[420px]:grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4';
const organisationsGridClassName = 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4';

function EmptyStatePanel({ body, title }: { body: string; title: string }): ReactNode {
  return (
    <Card className="border border-[color:var(--nexus-border)] bg-[color:var(--nexus-surface)]" variant="default">
      <Card.Header>
        <h2 className="text-xl font-bold text-[color:var(--nexus-ink)]">{title}</h2>
      </Card.Header>
      <Card.Content>
        <p className="leading-7 text-[color:var(--nexus-muted)]">{body}</p>
      </Card.Content>
    </Card>
  );
}

function RichIndexGrid({ children, className = 'grid gap-5' }: { children: ReactNode; className?: string }): ReactNode {
  return <div className={className}>{children}</div>;
}

function RichIndexCard({
  description,
  facts,
  href,
  image,
  imageAltFallback,
  meta,
  title,
}: {
  description: string;
  facts: RichFact[];
  href: string;
  image?: RichImage | null;
  imageAltFallback: string;
  meta: Array<null | string | undefined>;
  title: string;
}): ReactNode {
  const metaItems = compactText(meta);

  return (
    <article>
      <Card
        className="overflow-hidden border border-[color:var(--nexus-border)] bg-[color:var(--nexus-surface)] shadow-sm"
        data-nexus-ui="rich-index-card"
        variant="default"
      >
        <div className="grid md:grid-cols-[minmax(220px,32%)_1fr]">
          <MediaFrame href={href} image={image} imageAltFallback={imageAltFallback} variant="card" />
          <Card.Content className="p-5">
            {metaItems.length > 0 ? (
              <div className="mb-3 flex flex-wrap gap-2">
                {metaItems.map((item) => (
                  <Chip color="accent" key={item} size="sm" variant="soft">
                    {item}
                  </Chip>
                ))}
              </div>
            ) : null}
            <h2 className="text-xl font-bold leading-tight text-[color:var(--nexus-ink)]">
              <Link
                className="text-[color:var(--nexus-ink)] underline-offset-4 hover:text-[color:var(--nexus-accent)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--nexus-accent)]"
                href={href}
              >
                {title}
              </Link>
            </h2>
            <p className="mt-3 leading-7 text-[color:var(--nexus-muted)]">{description}</p>
            <FactList className="mt-5 md:grid-cols-2" facts={facts} />
          </Card.Content>
        </div>
      </Card>
    </article>
  );
}

function ListingPublicCard({
  listing,
  tenantBasePath,
  t,
}: {
  listing: PublicListing;
  tenantBasePath: string;
  t: Translator;
}): ReactNode {
  const href = withTenantBase(tenantBasePath, 'listings/' + listing.slug);
  const borderClass = listing.type === 'request' ? 'border-l-amber-500/70' : 'border-l-emerald-500/70';

  return (
    <article className="h-full" data-nexus-ui="react-listing-card">
      <Card
        className={
          'relative h-full cursor-pointer border border-theme-default bg-theme-card p-4 transition-all duration-200 hover:-translate-y-0.5 hover:bg-theme-hover hover:shadow-md border-l-4 focus-within:outline focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-accent ' +
          borderClass
        }
        variant="default"
      >
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0 space-y-2">
            <div className="flex flex-wrap gap-2">
              {listing.category?.name ? (
                <Chip color="accent" size="sm" variant="soft">
                  {listing.category.name}
                </Chip>
              ) : null}
              {formatTimeValue(listing, t) ? (
                <Chip color="success" size="sm" variant="soft">
                  {formatTimeValue(listing, t)}
                </Chip>
              ) : null}
            </div>
            <h2 className="text-lg font-semibold leading-tight text-theme-primary">
              <Link
                className="text-theme-primary underline-offset-4 hover:text-accent focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--accent)]"
                href={href}
              >
                {listing.title}
              </Link>
            </h2>
          </div>
          <IndexIconButton label={listing.title} />
        </div>
        <Card.Content className="mt-4 p-0">
          <CardImageLink
            href={href}
            image={listing.primaryImage}
            imageAltFallback={t('listings.imageAltFallback')}
          />
          <p className="mt-4 line-clamp-3 text-sm leading-6 text-theme-muted">{listing.excerpt || listing.description}</p>
          <CompactFactRow
            facts={[
              {
                icon: <MapPin aria-hidden="true" className="size-4 shrink-0" />,
                label: t('listings.locationLabel'),
                value: listing.location.label,
              },
              {
                icon: <UserRound aria-hidden="true" className="size-4 shrink-0" />,
                label: t('listings.providerLabel'),
                value: listing.provider.displayName,
              },
            ]}
            uiMarker="listing-facts"
          />
        </Card.Content>
      </Card>
    </article>
  );
}

function EventPublicCard({
  event,
  tenantBasePath,
  t,
}: {
  event: PublicEvent;
  tenantBasePath: string;
  t: Translator;
}): ReactNode {
  const href = withTenantBase(tenantBasePath, 'events/' + event.slug);

  return (
    <article className="h-full" data-nexus-ui="react-event-card">
      <Card className="h-full border border-theme-default bg-theme-card p-4 transition-all hover:-translate-y-0.5 hover:bg-theme-hover hover:shadow-md" variant="default">
        <CardImageLink href={href} image={event.primaryImage} imageAltFallback={t('events.imageAltFallback')} />
        <Card.Content className="mt-4 p-0">
          <div className="mb-3 flex items-start justify-between gap-3">
            <div className="flex flex-wrap gap-2">
              {event.category?.name ? (
                <Chip color="accent" size="sm" variant="soft">
                  {event.category.name}
                </Chip>
              ) : null}
              {formatEventRange(event) ? (
                <Chip color="accent" size="sm" variant="soft">
                  {formatEventRange(event)}
                </Chip>
              ) : null}
            </div>
            <IndexIconButton label={event.title} />
          </div>
          <h2 className="text-lg font-semibold leading-tight text-theme-primary">
            <Link
              className="text-theme-primary underline-offset-4 hover:text-accent focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--accent)]"
              href={href}
            >
              {event.title}
            </Link>
          </h2>
          <p className="mt-3 line-clamp-3 text-sm leading-6 text-theme-muted">{event.excerpt || event.description}</p>
          <CompactFactRow
            facts={[
              {
                icon: <CalendarDays aria-hidden="true" className="size-4 shrink-0" />,
                label: t('events.dateLabel'),
                value: formatEventRange(event),
              },
              {
                icon: <MapPin aria-hidden="true" className="size-4 shrink-0" />,
                label: t('events.locationLabel'),
                value: event.location.label,
              },
              {
                icon: <UserRound aria-hidden="true" className="size-4 shrink-0" />,
                label: t('events.organiserLabel'),
                value: event.organiser.displayName,
              },
            ]}
            uiMarker="event-facts"
          />
        </Card.Content>
      </Card>
    </article>
  );
}

function JobPublicCard({
  job,
  tenantBasePath,
  t,
}: {
  job: PublicJob;
  tenantBasePath: string;
  t: Translator;
}): ReactNode {
  const href = withTenantBase(tenantBasePath, 'jobs/' + job.slug);

  return (
    <article className="h-full" data-nexus-ui="react-job-card">
      <Card className="h-full border border-theme-default bg-theme-card p-5 transition-transform hover:scale-[1.01] motion-reduce:transition-none motion-reduce:hover:scale-100" variant="default">
        <div className="flex items-start gap-4">
          <Link
            className="rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface-base)]"
            href={href}
          >
            <CardLogoImage image={job.primaryImage} imageAltFallback={t('jobs.imageAltFallback')} />
          </Link>
          <div className="min-w-0 flex-1">
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0">
                <h2 className="text-lg font-semibold leading-tight text-theme-primary">
                  <Link
                    className="text-theme-primary underline-offset-4 hover:text-accent focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--accent)]"
                    href={href}
                  >
                    {job.title}
                  </Link>
                </h2>
                <p className="mt-1 text-sm font-medium text-theme-secondary">{job.employer.displayName}</p>
              </div>
              <IndexIconButton label={job.title} />
            </div>
            <p className="mt-3 line-clamp-2 text-sm leading-6 text-theme-muted">{job.excerpt || job.description}</p>
          </div>
        </div>
        <CompactFactRow
          facts={[
            {
              icon: <BriefcaseBusiness aria-hidden="true" className="size-4 shrink-0" />,
              label: t('jobs.categoryLabel'),
              value: job.category?.name,
            },
            {
              icon: <MapPin aria-hidden="true" className="size-4 shrink-0" />,
              label: t('jobs.locationLabel'),
              value: formatJobLocation(job, t),
            },
            {
              icon: <Clock3Icon />,
              label: t('jobs.compensationLabel'),
              value: formatJobCompensation(job, t),
            },
          ]}
          uiMarker="job-facts"
        />
      </Card>
    </article>
  );
}

function MarketplacePublicCard({
  item,
  tenantBasePath,
  t,
}: {
  item: PublicMarketplaceListing;
  tenantBasePath: string;
  t: Translator;
}): ReactNode {
  const href = withTenantBase(tenantBasePath, 'marketplace/' + item.slug);

  return (
    <article className="h-full" data-nexus-ui="react-marketplace-card">
      <Card className="h-full overflow-hidden border border-theme-default bg-theme-card transition-colors hover:bg-theme-hover" variant="default">
        <div className="relative">
          <CardImageLink
            className="aspect-square rounded-none"
            href={href}
            image={item.primaryImage}
            imageAltFallback={t('marketplaceItems.imageAltFallback')}
          />
          <div className="absolute inset-x-3 top-3 flex flex-wrap justify-between gap-2">
            {formatMarketplacePrice(item, t) ? (
              <Chip color="accent" size="sm" variant="primary">
                {formatMarketplacePrice(item, t)}
              </Chip>
            ) : null}
            {item.condition ? (
              <Chip color="accent" size="sm" variant="soft">
                {item.condition}
              </Chip>
            ) : null}
          </div>
        </div>
        <Card.Content className="p-4">
          <div className="flex items-start justify-between gap-3">
            <h2 className="line-clamp-2 text-base font-semibold leading-tight text-theme-primary">
              <Link
                className="text-theme-primary underline-offset-4 hover:text-accent focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--accent)]"
                href={href}
              >
                {item.title}
              </Link>
            </h2>
            <IndexIconButton label={item.title} />
          </div>
          <p className="mt-3 line-clamp-2 text-sm leading-6 text-theme-muted">{item.excerpt || item.description}</p>
          <CompactFactRow
            facts={[
              {
                icon: <ShoppingBag aria-hidden="true" className="size-4 shrink-0" />,
                label: t('marketplaceItems.categoryLabel'),
                value: item.category?.name,
              },
              {
                icon: <MapPin aria-hidden="true" className="size-4 shrink-0" />,
                label: t('marketplaceItems.locationLabel'),
                value: item.location.label,
              },
              {
                icon: <UserRound aria-hidden="true" className="size-4 shrink-0" />,
                label: t('marketplaceItems.sellerLabel'),
                value: item.seller.displayName,
              },
            ]}
            uiMarker="marketplace-facts"
          />
        </Card.Content>
      </Card>
    </article>
  );
}

function OrganisationPublicCard({
  organisation,
  tenantBasePath,
  t,
}: {
  organisation: PublicOrganisation;
  tenantBasePath: string;
  t: Translator;
}): ReactNode {
  const href = withTenantBase(tenantBasePath, 'organisations/' + organisation.slug);

  return (
    <article className="h-full" data-nexus-ui="react-organisation-card">
      <Card className="h-full border border-theme-default bg-theme-card p-5 transition-colors hover:bg-theme-hover" variant="default">
        <div className="flex items-start gap-4">
          <Link
            className="rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--surface-base)]"
            href={href}
          >
            <CardLogoImage image={organisation.logoImage} imageAltFallback={t('organisationProfiles.logoAltFallback')} />
          </Link>
          <div className="min-w-0 flex-1">
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0">
                <h2 className="text-lg font-semibold leading-tight text-theme-primary">
                  <Link
                    className="text-theme-primary underline-offset-4 hover:text-accent focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--accent)]"
                    href={href}
                  >
                    {organisation.name}
                  </Link>
                </h2>
                {organisation.location.label ? (
                  <p className="mt-1 text-sm text-theme-muted">{organisation.location.label}</p>
                ) : null}
              </div>
              <IndexIconButton label={organisation.name} />
            </div>
          </div>
        </div>
        <p className="mt-4 line-clamp-2 text-sm leading-6 text-theme-muted">
          {organisation.excerpt || organisation.description}
        </p>
        <CompactFactRow
          facts={[
            {
              icon: <UserRound aria-hidden="true" className="size-4 shrink-0" />,
              label: t('organisationProfiles.ownerLabel'),
              value: organisation.owner.displayName,
            },
            {
              icon: <BriefcaseBusiness aria-hidden="true" className="size-4 shrink-0" />,
              label: t('organisationProfiles.opportunitiesLabel'),
              value: formatCount(organisation.stats.opportunityCount, 'organisationProfiles.opportunityCount', t),
            },
            {
              icon: <Star aria-hidden="true" className="size-4 shrink-0" />,
              label: t('organisationProfiles.volunteersLabel'),
              value: formatCount(organisation.stats.volunteerCount, 'organisationProfiles.volunteerCount', t),
            },
          ]}
          uiMarker="organisation-facts"
        />
      </Card>
    </article>
  );
}

function IndexIconButton({ label }: { label: string }): ReactNode {
  return (
    <Button aria-label={label} className="shrink-0 text-theme-muted hover:text-accent" isIconOnly size="sm" variant="ghost">
      <Heart aria-hidden="true" className="size-4" />
    </Button>
  );
}

function CardImageLink({
  className = 'aspect-[4/3] rounded-lg',
  href,
  image,
  imageAltFallback,
}: {
  className?: string;
  href: string;
  image?: RichImage | null;
  imageAltFallback: string;
}): ReactNode {
  const content = image ? (
    <Image
      alt={image.altText || imageAltFallback}
      className="h-full w-full object-cover transition-transform duration-200 group-hover:scale-[1.02]"
      height={480}
      src={image.url}
      unoptimized
      width={640}
    />
  ) : (
    <span aria-hidden="true" className="block h-full w-full bg-accent/10" />
  );

  return (
    <Link className={'group block overflow-hidden bg-accent/10 ' + className} href={href}>
      {content}
    </Link>
  );
}

function CardLogoImage({
  image,
  imageAltFallback,
}: {
  image?: RichImage | null;
  imageAltFallback: string;
}): ReactNode {
  if (!image) {
    return <span aria-hidden="true" className="size-14 shrink-0 rounded-xl bg-accent/10 ring-1 ring-border" />;
  }

  return (
    <Image
      alt={image.altText || imageAltFallback}
      className="size-14 shrink-0 rounded-xl bg-theme-elevated object-cover ring-1 ring-border"
      height={112}
      src={image.url}
      unoptimized
      width={112}
    />
  );
}

function CompactFactRow({
  facts,
  uiMarker,
}: {
  facts: Array<{ icon: ReactNode; label: string; value?: null | string }>;
  uiMarker: string;
}): ReactNode {
  const visibleFacts = facts.filter((fact) => Boolean(fact.value));

  if (visibleFacts.length === 0) {
    return null;
  }

  return (
    <div className="mt-4 grid gap-2 text-sm text-theme-muted" data-nexus-ui={uiMarker}>
      {visibleFacts.map((fact) => (
        <div className="flex min-w-0 items-center gap-2" key={fact.label + fact.value}>
          <span className="text-theme-subtle">{fact.icon}</span>
          <span className="sr-only">{fact.label}</span>
          <span className="truncate">{fact.value}</span>
        </div>
      ))}
    </div>
  );
}

function Clock3Icon(): ReactNode {
  return (
    <span aria-hidden="true" className="inline-flex size-4 shrink-0 items-center justify-center rounded-full border border-current text-[10px] font-bold">
      h
    </span>
  );
}

function RichDetailLayout({
  asideTitle,
  backHref,
  backLabel,
  breadcrumbLabel,
  children,
  facts,
  gallery = [],
  galleryTitle,
  image,
  imageAltFallback,
  title,
  uiMarker = 'rich-detail',
}: {
  asideTitle: string;
  backHref: string;
  backLabel: string;
  breadcrumbLabel: string;
  children: ReactNode;
  facts: RichFact[];
  gallery?: RichImage[];
  galleryTitle?: string;
  image?: RichImage | null;
  imageAltFallback: string;
  title: string;
  uiMarker?: string;
}): ReactNode {
  return (
    <article className="grid gap-5" data-nexus-ui={uiMarker}>
      <DetailBreadcrumb backHref={backHref} backLabel={backLabel} breadcrumbLabel={breadcrumbLabel} title={title} />
      {image ? <MediaFrame image={image} imageAltFallback={imageAltFallback} variant="hero" /> : null}
      <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(260px,320px)] lg:items-start">
        <div className="grid gap-5">
          {children}
          {gallery.length > 0 && galleryTitle ? (
            <ContentCard title={galleryTitle}>
              <GalleryGrid imageAltFallback={imageAltFallback} images={gallery} />
            </ContentCard>
          ) : null}
        </div>
        <aside>
          <Card
            className="border border-theme-default bg-theme-card lg:sticky lg:top-4"
            variant="default"
          >
            <Card.Header>
              <h2 className="text-xl font-bold text-theme-primary">{asideTitle}</h2>
            </Card.Header>
            <Card.Content>
              <FactList facts={facts} />
            </Card.Content>
          </Card>
        </aside>
      </div>
    </article>
  );
}

function ContentCard({ children, title, uiMarker }: { children: ReactNode; title: string; uiMarker?: string }): ReactNode {
  return (
    <Card
      className="border border-theme-default bg-theme-card"
      data-nexus-ui={uiMarker}
      variant="default"
    >
      <Card.Header>
        <h2 className="text-xl font-bold text-theme-primary">{title}</h2>
      </Card.Header>
      <Card.Content>
        <div className="space-y-4 leading-7 text-theme-muted">{children}</div>
      </Card.Content>
    </Card>
  );
}

function DetailBreadcrumb({
  backHref,
  backLabel,
  breadcrumbLabel,
  title,
}: {
  backHref: string;
  backLabel: string;
  breadcrumbLabel: string;
  title: string;
}): ReactNode {
  return (
    <nav aria-label={breadcrumbLabel} className="flex flex-wrap items-center gap-2 text-sm text-theme-muted">
      <Link
        className="font-bold text-accent hover:text-theme-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--accent)]"
        href={backHref}
      >
        {backLabel}
      </Link>
      <span aria-hidden="true">/</span>
      <span className="text-theme-primary">{title}</span>
    </nav>
  );
}

function FactList({
  className = '',
  facts,
  uiMarker,
}: {
  className?: string;
  facts: RichFact[];
  uiMarker?: string;
}): ReactNode {
  const visibleFacts = facts.filter((fact) => Boolean(fact.value));

  if (visibleFacts.length === 0) {
    return null;
  }

  return (
    <dl className={('grid gap-3 ' + className).trim()} data-nexus-ui={uiMarker}>
      {visibleFacts.map((fact) => (
        <div className="min-w-0" key={fact.label}>
          <dt className="text-xs font-bold uppercase text-theme-subtle">{fact.label}</dt>
          <dd className="mt-1 [overflow-wrap:anywhere] text-theme-primary">{fact.value}</dd>
        </div>
      ))}
    </dl>
  );
}

function MediaFrame({
  href,
  image,
  imageAltFallback,
  variant,
}: {
  href?: string;
  image?: RichImage | null;
  imageAltFallback: string;
  variant: 'card' | 'hero';
}): ReactNode {
  const frameClassName =
    variant === 'hero'
      ? 'aspect-[16/7] max-h-[460px] rounded-lg'
      : 'aspect-[4/3] min-h-56 md:min-h-full';
  const dimensions =
    variant === 'hero'
      ? {
          height: 700,
          width: 1600,
        }
      : {
          height: 480,
          width: 640,
        };
  const content = image ? (
    <Image
      alt={image.altText || imageAltFallback}
      className="h-full w-full object-cover"
      height={dimensions.height}
      src={image.url}
      unoptimized
      width={dimensions.width}
    />
  ) : (
    <span aria-hidden="true" className="block h-full w-full bg-[color:var(--nexus-accent-soft)]" />
  );
  const className = 'block overflow-hidden bg-[color:var(--nexus-accent-soft)] ' + frameClassName;

  if (href) {
    return (
      <Link className={className} href={href}>
        {content}
      </Link>
    );
  }

  return <div className={className}>{content}</div>;
}

function GalleryGrid({
  imageAltFallback,
  images,
}: {
  imageAltFallback: string;
  images: RichImage[];
}): ReactNode {
  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      {images.map((image) => (
        <Image
          alt={image.altText || imageAltFallback}
          className="aspect-[4/3] w-full rounded-lg bg-[color:var(--nexus-accent-soft)] object-cover"
          height={480}
          key={image.url + '-' + (image.sortOrder ?? 0)}
          src={image.url}
          unoptimized
          width={640}
        />
      ))}
    </div>
  );
}

function renderRouteContent(
  route: RouteOwnership,
  routeSegments: string[],
  content: PublicRouteContent | null,
  tenantName: string,
  tenantBasePath: string,
  t: Translator,
): ReactNode {
  if (route.routeKey === 'home') {
    return (
      <ContentCard title={t('pages.home.sectionTitle')}>
        <p>{t('pages.home.sectionBody')}</p>
      </ContentCard>
    );
  }

  if (content?.kind === 'blog-index') {
    return <BlogIndex posts={content.posts} tenantBasePath={tenantBasePath} t={t} />;
  }

  if (content?.kind === 'blog-detail' && content.post) {
    return (
      <ContentCard title={content.post.title}>
        <HtmlBlock html={content.post.content || content.post.excerpt || ''} />
      </ContentCard>
    );
  }

  if (content?.kind === 'cms-page' && content.page) {
    return (
      <ContentCard title={content.page.title}>
        <HtmlBlock html={content.page.content || ''} />
      </ContentCard>
    );
  }

  if (content?.kind === 'listings-index') {
    return <ListingsIndex listings={content.listings} tenantBasePath={tenantBasePath} t={t} />;
  }

  if (content?.kind === 'listing-detail' && content.listing) {
    return <ListingDetail listing={content.listing} tenantBasePath={tenantBasePath} t={t} />;
  }

  if (content?.kind === 'events-index') {
    return <EventsIndex events={content.events} tenantBasePath={tenantBasePath} t={t} />;
  }

  if (content?.kind === 'event-detail' && content.event) {
    return <EventDetail event={content.event} tenantBasePath={tenantBasePath} t={t} />;
  }

  if (content?.kind === 'jobs-index') {
    return <JobsIndex jobs={content.jobs} tenantBasePath={tenantBasePath} t={t} />;
  }

  if (content?.kind === 'job-detail' && content.job) {
    return <JobDetail job={content.job} tenantBasePath={tenantBasePath} t={t} />;
  }

  if (content?.kind === 'marketplace-index') {
    return <MarketplaceIndex items={content.items} tenantBasePath={tenantBasePath} t={t} />;
  }

  if (content?.kind === 'marketplace-detail' && content.item) {
    return <MarketplaceDetail item={content.item} tenantBasePath={tenantBasePath} t={t} />;
  }

  if (content?.kind === 'organisations-index') {
    return <OrganisationsIndex organisations={content.organisations} tenantBasePath={tenantBasePath} t={t} />;
  }

  if (content?.kind === 'organisation-detail' && content.organisation) {
    return <OrganisationDetail organisation={content.organisation} tenantBasePath={tenantBasePath} t={t} />;
  }

  if (content?.kind === 'public-collection') {
    return (
      <PublicCollection
        basePath={withTenantBase(tenantBasePath, routeSegments[0] ?? '')}
        emptyTitle={t(route.labelKey ?? 'pages.about.title')}
        items={content.items}
        t={t}
      />
    );
  }

  if (content?.kind === 'public-detail' && content.item) {
    return <PublicDetail item={content.item} />;
  }

  if (route.routeKey === 'blog-detail') {
    return <EmptyStatePanel body={t('pages.blogDetail.missing')} title={t('pages.blogDetail.title')} />;
  }

  if (route.routeKey === 'cms-page') {
    return (
      <ContentCard title={routeSegments.at(-1) ?? t('pages.cmsPage.title')}>
        <p>{t('pages.cmsPage.missing')}</p>
      </ContentCard>
    );
  }

  return (
    <ContentCard title={t(route.labelKey ?? 'pages.about.title')}>
      <p>{t(`pages.${route.routeKey}.body`, { tenantName })}</p>
    </ContentCard>
  );
}

function BlogIndex({
  posts,
  tenantBasePath,
  t,
}: {
  posts: BlogPostSummary[];
  tenantBasePath: string;
  t: Translator;
}): ReactNode {
  if (posts.length === 0) {
    return <EmptyStatePanel body={t('pages.blog.empty')} title={t('pages.blog.title')} />;
  }

  return (
    <RichIndexGrid>
      {posts.map((post) => (
        <RichIndexCard
          description={post.excerpt || ''}
          facts={[]}
          href={withTenantBase(tenantBasePath, 'blog/' + post.slug)}
          image={null}
          imageAltFallback={post.title}
          key={post.slug}
          meta={[post.author_name, formatDate(post.published_at ?? null)]}
          title={post.title}
        />
      ))}
    </RichIndexGrid>
  );
}

function ListingsIndex({
  listings,
  tenantBasePath,
  t,
}: {
  listings: PublicListingsIndex;
  tenantBasePath: string;
  t: Translator;
}): ReactNode {
  if (listings.items.length === 0) {
    return <EmptyStatePanel body={t('listings.empty')} title={t('pages.listings.title')} />;
  }

  return (
    <RichIndexGrid className={publicFamilyGridClassName}>
      {listings.items.map((listing) => (
        <ListingPublicCard key={listing.id} listing={listing} tenantBasePath={tenantBasePath} t={t} />
      ))}
    </RichIndexGrid>
  );
}

function ListingDetail({
  listing,
  tenantBasePath,
  t,
}: {
  listing: PublicListing;
  tenantBasePath: string;
  t: Translator;
}): ReactNode {
  const gallery = listing.gallery.length > 0 ? listing.gallery : compactImages([listing.primaryImage]);

  return (
    <RichDetailLayout
      asideTitle={t('listings.providerLabel')}
      backHref={withTenantBase(tenantBasePath, 'listings')}
      backLabel={t('listings.backToListings')}
      breadcrumbLabel={t('listings.breadcrumbLabel')}
      facts={[
        { label: t('listings.providerLabel'), value: listing.provider.displayName },
        { label: t('listings.categoryLabel'), value: listing.category?.name },
        { label: t('listings.valueLabel'), value: formatTimeValue(listing, t) },
        { label: t('listings.locationLabel'), value: listing.location.label },
        { label: t('listings.statusLabel'), value: listing.status },
        { label: t('listings.updatedLabel'), value: formatDate(listing.updatedAt) },
        { label: t('listings.createdLabel'), value: formatDate(listing.createdAt) },
      ]}
      gallery={gallery}
      galleryTitle={t('listings.galleryLabel')}
      image={listing.primaryImage}
      imageAltFallback={t('listings.imageAltFallback')}
      title={listing.title}
      uiMarker="react-listing-detail"
    >
      <ContentCard title={listing.title} uiMarker="public-detail-panel">
        <p>{listing.description}</p>
      </ContentCard>
    </RichDetailLayout>
  );
}

function EventsIndex({
  events,
  tenantBasePath,
  t,
}: {
  events: PublicEventsIndex;
  tenantBasePath: string;
  t: Translator;
}): ReactNode {
  if (events.events.length === 0) {
    return <EmptyStatePanel body={t('events.empty')} title={t('pages.events.title')} />;
  }

  return (
    <RichIndexGrid className={publicFamilyGridClassName}>
      {events.events.map((event) => (
        <EventPublicCard event={event} key={event.id} tenantBasePath={tenantBasePath} t={t} />
      ))}
    </RichIndexGrid>
  );
}

function EventDetail({
  event,
  tenantBasePath,
  t,
}: {
  event: PublicEvent;
  tenantBasePath: string;
  t: Translator;
}): ReactNode {
  return (
    <RichDetailLayout
      asideTitle={t('events.organiserLabel')}
      backHref={withTenantBase(tenantBasePath, 'events')}
      backLabel={t('events.backToEvents')}
      breadcrumbLabel={t('events.breadcrumbLabel')}
      facts={[
        { label: t('events.organiserLabel'), value: event.organiser.displayName },
        { label: t('events.dateLabel'), value: formatEventRange(event) },
        { label: t('events.categoryLabel'), value: event.category?.name },
        { label: t('events.locationLabel'), value: event.location.label },
        { label: t('events.statusLabel'), value: event.status },
        { label: t('events.updatedLabel'), value: formatDate(event.updatedAt) },
        { label: t('events.createdLabel'), value: formatDate(event.createdAt) },
      ]}
      image={event.primaryImage}
      imageAltFallback={t('events.imageAltFallback')}
      title={event.title}
      uiMarker="react-event-detail"
    >
      <ContentCard title={event.title} uiMarker="public-detail-panel">
        <p>{event.description}</p>
      </ContentCard>
    </RichDetailLayout>
  );
}

function JobsIndex({
  jobs,
  tenantBasePath,
  t,
}: {
  jobs: PublicJobsIndex;
  tenantBasePath: string;
  t: Translator;
}): ReactNode {
  if (jobs.jobs.length === 0) {
    return <EmptyStatePanel body={t('jobs.empty')} title={t('pages.jobs.title')} />;
  }

  return (
    <RichIndexGrid className={publicFamilyGridClassName}>
      {jobs.jobs.map((job) => (
        <JobPublicCard job={job} key={job.id} tenantBasePath={tenantBasePath} t={t} />
      ))}
    </RichIndexGrid>
  );
}

function JobDetail({
  job,
  tenantBasePath,
  t,
}: {
  job: PublicJob;
  tenantBasePath: string;
  t: Translator;
}): ReactNode {
  const gallery = job.gallery.length > 0 ? job.gallery : compactJobImages([job.primaryImage]);

  return (
    <RichDetailLayout
      asideTitle={t('jobs.employerLabel')}
      backHref={withTenantBase(tenantBasePath, 'jobs')}
      backLabel={t('jobs.backToJobs')}
      breadcrumbLabel={t('jobs.breadcrumbLabel')}
      facts={[
        { label: t('jobs.employerLabel'), value: job.employer.displayName },
        { label: t('jobs.compensationLabel'), value: formatJobCompensation(job, t) },
        { label: t('jobs.locationLabel'), value: formatJobLocation(job, t) },
        { label: t('jobs.categoryLabel'), value: job.category?.name },
        { label: t('jobs.typeLabel'), value: job.jobType },
        { label: t('jobs.commitmentLabel'), value: job.commitment },
        { label: t('jobs.deadlineLabel'), value: formatDate(job.deadlineAt) },
        { label: t('jobs.statusLabel'), value: job.status },
        { label: t('jobs.updatedLabel'), value: formatDate(job.updatedAt) },
        { label: t('jobs.createdLabel'), value: formatDate(job.createdAt) },
      ]}
      gallery={gallery}
      galleryTitle={t('jobs.galleryLabel')}
      image={job.primaryImage}
      imageAltFallback={t('jobs.imageAltFallback')}
      title={job.title}
      uiMarker="react-job-detail"
    >
      <ContentCard title={job.title} uiMarker="public-detail-panel">
        <p>{job.description}</p>
      </ContentCard>
      {job.skills.length > 0 ? (
        <ContentCard title={t('jobs.skillsLabel')}>
          <p>{job.skills.join(', ')}</p>
        </ContentCard>
      ) : null}
    </RichDetailLayout>
  );
}

function MarketplaceIndex({
  items,
  tenantBasePath,
  t,
}: {
  items: PublicMarketplaceIndex;
  tenantBasePath: string;
  t: Translator;
}): ReactNode {
  if (items.items.length === 0) {
    return <EmptyStatePanel body={t('marketplaceItems.empty')} title={t('pages.marketplace.title')} />;
  }

  return (
    <RichIndexGrid className={marketplaceGridClassName}>
      {items.items.map((item) => (
        <MarketplacePublicCard item={item} key={item.id} tenantBasePath={tenantBasePath} t={t} />
      ))}
    </RichIndexGrid>
  );
}

function MarketplaceDetail({
  item,
  tenantBasePath,
  t,
}: {
  item: PublicMarketplaceListing;
  tenantBasePath: string;
  t: Translator;
}): ReactNode {
  const gallery = item.gallery.length > 0 ? item.gallery : compactMarketplaceImages([item.primaryImage]);

  return (
    <RichDetailLayout
      asideTitle={t('marketplaceItems.sellerLabel')}
      backHref={withTenantBase(tenantBasePath, 'marketplace')}
      backLabel={t('marketplaceItems.backToMarketplace')}
      breadcrumbLabel={t('marketplaceItems.breadcrumbLabel')}
      facts={[
        { label: t('marketplaceItems.sellerLabel'), value: item.seller.displayName },
        { label: t('marketplaceItems.priceLabel'), value: formatMarketplacePrice(item, t) },
        { label: t('marketplaceItems.categoryLabel'), value: item.category?.name },
        { label: t('marketplaceItems.locationLabel'), value: item.location.label },
        { label: t('marketplaceItems.conditionLabel'), value: item.condition },
        { label: t('marketplaceItems.deliveryLabel'), value: formatMarketplaceDelivery(item, t) },
        { label: t('marketplaceItems.quantityLabel'), value: formatNullableNumber(item.quantity) },
        { label: t('marketplaceItems.statusLabel'), value: item.status },
        { label: t('marketplaceItems.expiresLabel'), value: formatDate(item.expiresAt) },
        { label: t('marketplaceItems.updatedLabel'), value: formatDate(item.updatedAt) },
        { label: t('marketplaceItems.createdLabel'), value: formatDate(item.createdAt) },
      ]}
      gallery={gallery}
      galleryTitle={t('marketplaceItems.galleryLabel')}
      image={item.primaryImage}
      imageAltFallback={t('marketplaceItems.imageAltFallback')}
      title={item.title}
      uiMarker="react-marketplace-detail"
    >
      <ContentCard title={item.title} uiMarker="public-detail-panel">
        <p>{item.description}</p>
      </ContentCard>
    </RichDetailLayout>
  );
}

function OrganisationsIndex({
  organisations,
  tenantBasePath,
  t,
}: {
  organisations: PublicOrganisationsIndex;
  tenantBasePath: string;
  t: Translator;
}): ReactNode {
  if (organisations.organisations.length === 0) {
    return <EmptyStatePanel body={t('organisationProfiles.empty')} title={t('pages.organisations.title')} />;
  }

  return (
    <RichIndexGrid className={organisationsGridClassName}>
      {organisations.organisations.map((organisation) => (
        <OrganisationPublicCard
          key={organisation.id}
          organisation={organisation}
          tenantBasePath={tenantBasePath}
          t={t}
        />
      ))}
    </RichIndexGrid>
  );
}

function OrganisationDetail({
  organisation,
  tenantBasePath,
  t,
}: {
  organisation: PublicOrganisation;
  tenantBasePath: string;
  t: Translator;
}): ReactNode {
  return (
    <RichDetailLayout
      asideTitle={t('organisationProfiles.profileLabel')}
      backHref={withTenantBase(tenantBasePath, 'organisations')}
      backLabel={t('organisationProfiles.backToOrganisations')}
      breadcrumbLabel={t('organisationProfiles.breadcrumbLabel')}
      facts={[
        { label: t('organisationProfiles.ownerLabel'), value: organisation.owner.displayName },
        { label: t('organisationProfiles.websiteLabel'), value: organisation.website },
        { label: t('organisationProfiles.emailLabel'), value: organisation.contactEmail },
        { label: t('organisationProfiles.locationLabel'), value: organisation.location.label },
        {
          label: t('organisationProfiles.opportunitiesLabel'),
          value: formatCount(organisation.stats.opportunityCount, 'organisationProfiles.opportunityCount', t),
        },
        {
          label: t('organisationProfiles.volunteersLabel'),
          value: formatCount(organisation.stats.volunteerCount, 'organisationProfiles.volunteerCount', t),
        },
        {
          label: t('organisationProfiles.hoursLabel'),
          value: t('organisationProfiles.hourCount', { count: formatNumber(organisation.stats.totalHours) }),
        },
        { label: t('organisationProfiles.ratingLabel'), value: formatRating(organisation) },
        { label: t('organisationProfiles.typeLabel'), value: organisation.orgType },
        { label: t('organisationProfiles.statusLabel'), value: organisation.status },
        { label: t('organisationProfiles.updatedLabel'), value: formatDate(organisation.updatedAt) },
        { label: t('organisationProfiles.createdLabel'), value: formatDate(organisation.createdAt) },
      ]}
      image={organisation.logoImage}
      imageAltFallback={t('organisationProfiles.logoAltFallback')}
      title={organisation.name}
      uiMarker="react-organisation-detail"
    >
      <ContentCard title={organisation.name} uiMarker="public-detail-panel">
        <p>{organisation.description}</p>
      </ContentCard>
    </RichDetailLayout>
  );
}

function PublicCollection({
  basePath,
  emptyTitle,
  items,
  t,
}: {
  basePath: string;
  emptyTitle: string;
  items: PublicContentItem[];
  t: Translator;
}): ReactNode {
  if (items.length === 0) {
    return <EmptyStatePanel body={t('pages.publicCollection.empty')} title={emptyTitle} />;
  }

  return (
    <RichIndexGrid>
      {items.map((item) => (
        <RichIndexCard
          description={item.description || ''}
          facts={[]}
          href={withTenantBase(basePath, item.slug ?? item.id)}
          image={null}
          imageAltFallback={item.title}
          key={item.slug ?? item.id}
          meta={[]}
          title={item.title}
        />
      ))}
    </RichIndexGrid>
  );
}



function formatTimeValue(listing: PublicListing, t: Translator): string | null {
  if (listing.timeCreditValue.hours === null) {
    return null;
  }

  return t('listings.valueHours', { count: formatNumber(listing.timeCreditValue.hours) });
}

function formatEventRange(event: PublicEvent): string | null {
  const start = formatDateTime(event.startAt);
  const end = formatDateTime(event.endAt);

  if (start && end) {
    return `${start} - ${end}`;
  }

  return start ?? end;
}

function formatJobCompensation(job: PublicJob, t: Translator): string | null {
  const { compensation } = job;
  const currency = compensation.salaryCurrency ?? '';

  if (compensation.salaryMin !== null && compensation.salaryMax !== null) {
    return `${currency} ${formatNumber(compensation.salaryMin)} - ${formatNumber(compensation.salaryMax)}`.trim();
  }

  if (compensation.salaryMin !== null) {
    return `${currency} ${formatNumber(compensation.salaryMin)}`.trim();
  }

  if (compensation.timeCredits !== null) {
    return t('jobs.timeCredits', { count: formatNumber(compensation.timeCredits) });
  }

  if (compensation.hoursPerWeek !== null) {
    return t('jobs.hoursPerWeek', { count: formatNumber(compensation.hoursPerWeek) });
  }

  if (compensation.salaryNegotiable) {
    return t('jobs.salaryNegotiable');
  }

  return null;
}

function formatJobLocation(job: PublicJob, t: Translator): string | null {
  if (job.location.isRemote && job.location.label) {
    return `${job.location.label} / ${t('jobs.remoteLabel')}`;
  }

  if (job.location.isRemote) {
    return t('jobs.remoteLabel');
  }

  return job.location.label;
}

function formatJobType(job: PublicJob): string | null {
  return compactText([job.jobType, job.commitment]).join(' / ') || null;
}

function formatMarketplacePrice(item: PublicMarketplaceListing, t: Translator): string | null {
  if (item.price.priceType === 'free') {
    return t('marketplaceItems.freePrice');
  }

  if (item.price.amount !== null) {
    return `${item.price.currency ?? ''} ${formatNumber(item.price.amount)}`.trim();
  }

  if (item.price.timeCredits !== null) {
    return t('marketplaceItems.timeCredits', { count: formatNumber(item.price.timeCredits) });
  }

  if (item.price.priceType === 'contact') {
    return t('marketplaceItems.contactPrice');
  }

  return item.price.priceType;
}

function formatMarketplaceDelivery(item: PublicMarketplaceListing, t: Translator): string | null {
  return compactText([
    item.delivery.method,
    item.delivery.localPickup ? t('marketplaceItems.localPickupLabel') : null,
    item.delivery.shippingAvailable ? t('marketplaceItems.shippingLabel') : null,
  ]).join(' / ') || null;
}

function formatNullableNumber(value: number | null): string | null {
  return value === null ? null : formatNumber(value);
}

function formatCount(value: number, key: string, t: Translator): string {
  return t(key, { count: formatNumber(value) });
}

function formatRating(organisation: PublicOrganisation): string | null {
  if (organisation.stats.averageRating <= 0 || organisation.stats.reviewCount <= 0) {
    return null;
  }

  return `${formatNumber(organisation.stats.averageRating)} / 5`;
}

function formatDateTime(value: string | null): string | null {
  if (!value) {
    return null;
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat('en', {
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date);
}

function formatDate(value: string | null): string | null {
  if (!value) {
    return null;
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat('en', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(date);
}

function formatNumber(value: number): string {
  return new Intl.NumberFormat('en').format(value);
}

function compactText(values: Array<null | string | undefined>): string[] {
  return values.filter((value): value is string => Boolean(value));
}

function compactImages(values: Array<PublicListing['primaryImage']>): PublicListing['gallery'] {
  return values.filter((value): value is PublicListing['gallery'][number] => value !== null);
}

function compactJobImages(values: Array<PublicJob['primaryImage']>): PublicJob['gallery'] {
  return values.filter((value): value is PublicJob['gallery'][number] => value !== null);
}

function compactMarketplaceImages(
  values: Array<PublicMarketplaceListing['primaryImage']>,
): PublicMarketplaceListing['gallery'] {
  return values.filter((value): value is PublicMarketplaceListing['gallery'][number] => value !== null);
}

function PublicDetail({ item }: { item: PublicContentItem }): ReactNode {
  return (
    <ContentCard title={item.title}>
      {item.description ? <p>{item.description}</p> : null}
    </ContentCard>
  );
}

function HtmlBlock({ html }: { html: string }): ReactNode {
  if (!html) {
    return null;
  }

  return <div dangerouslySetInnerHTML={{ __html: html }} />;
}

function StructuredData({
  canonicalUrl,
  content,
  pageTitle,
  tenant,
  tenantName,
}: {
  canonicalUrl: string;
  content: PublicRouteContent | null;
  pageTitle: string;
  tenant: TenantBootstrap | null;
  tenantName: string;
}): ReactNode {
  const data = buildStructuredData({
    canonicalUrl,
    content,
    pageTitle,
    tenant,
    tenantName,
  });

  return <script dangerouslySetInnerHTML={{ __html: JSON.stringify(data) }} type="application/ld+json" />;
}

function buildStructuredData({
  canonicalUrl,
  content,
  pageTitle,
  tenant,
  tenantName,
}: {
  canonicalUrl: string;
  content: PublicRouteContent | null;
  pageTitle: string;
  tenant: TenantBootstrap | null;
  tenantName: string;
}): Record<string, unknown> {
  if (content?.kind === 'blog-detail' && content.post) {
    return {
      '@context': 'https://schema.org',
      '@type': 'BlogPosting',
      headline: content.post.title,
      url: canonicalUrl,
    };
  }

  if (content?.kind === 'listings-index') {
    const baseUrl = canonicalUrl.replace(/\/+$/, '');

    return {
      '@context': 'https://schema.org',
      '@type': 'ItemList',
      itemListElement: content.listings.items.map((listing, index) => ({
        '@type': 'ListItem',
        image: listing.primaryImage?.url,
        name: listing.title,
        position: index + 1,
        url: `${baseUrl}/${encodeURIComponent(listing.slug)}`,
      })),
      name: pageTitle,
      url: canonicalUrl,
    };
  }

  if (content?.kind === 'events-index') {
    const baseUrl = canonicalUrl.replace(/\/+$/, '');

    return {
      '@context': 'https://schema.org',
      '@type': 'ItemList',
      itemListElement: content.events.events.map((event, index) => ({
        '@type': 'ListItem',
        image: event.primaryImage?.url,
        name: event.title,
        position: index + 1,
        url: `${baseUrl}/${encodeURIComponent(event.slug)}`,
      })),
      name: pageTitle,
      url: canonicalUrl,
    };
  }

  if (content?.kind === 'jobs-index') {
    const baseUrl = canonicalUrl.replace(/\/+$/, '');

    return {
      '@context': 'https://schema.org',
      '@type': 'ItemList',
      itemListElement: content.jobs.jobs.map((job, index) => ({
        '@type': 'ListItem',
        image: job.primaryImage?.url,
        name: job.title,
        position: index + 1,
        url: `${baseUrl}/${encodeURIComponent(job.slug)}`,
      })),
      name: pageTitle,
      url: canonicalUrl,
    };
  }

  if (content?.kind === 'marketplace-index') {
    const baseUrl = canonicalUrl.replace(/\/+$/, '');

    return {
      '@context': 'https://schema.org',
      '@type': 'ItemList',
      itemListElement: content.items.items.map((item, index) => ({
        '@type': 'ListItem',
        image: item.primaryImage?.url,
        name: item.title,
        position: index + 1,
        url: `${baseUrl}/${encodeURIComponent(item.slug)}`,
      })),
      name: pageTitle,
      url: canonicalUrl,
    };
  }

  if (content?.kind === 'organisations-index') {
    const baseUrl = canonicalUrl.replace(/\/+$/, '');

    return {
      '@context': 'https://schema.org',
      '@type': 'ItemList',
      itemListElement: content.organisations.organisations.map((organisation, index) => ({
        '@type': 'ListItem',
        image: organisation.logoImage?.url,
        name: organisation.name,
        position: index + 1,
        url: `${baseUrl}/${encodeURIComponent(organisation.slug)}`,
      })),
      name: pageTitle,
      url: canonicalUrl,
    };
  }

  if (content?.kind === 'listing-detail' && content.listing) {
    return {
      '@context': 'https://schema.org',
      '@type': 'Service',
      areaServed: content.listing.location.label
        ? {
            '@type': 'Place',
            name: content.listing.location.label,
          }
        : undefined,
      description: content.listing.description,
      image: content.listing.primaryImage?.url,
      name: content.listing.title,
      provider: {
        '@type': 'Organization',
        name: content.listing.provider.displayName ?? tenantName,
      },
      url: canonicalUrl,
    };
  }

  if (content?.kind === 'event-detail' && content.event) {
    return {
      '@context': 'https://schema.org',
      '@type': 'Event',
      description: content.event.description,
      endDate: content.event.endAt ?? undefined,
      eventStatus: 'https://schema.org/EventScheduled',
      image: content.event.primaryImage?.url,
      location: content.event.location.label
        ? {
            '@type': 'Place',
            name: content.event.location.label,
          }
        : undefined,
      name: content.event.title,
      organizer: {
        '@type': 'Organization',
        name: content.event.organiser.displayName ?? tenantName,
      },
      startDate: content.event.startAt ?? undefined,
      url: canonicalUrl,
    };
  }

  if (content?.kind === 'job-detail' && content.job) {
    return {
      '@context': 'https://schema.org',
      '@type': 'JobPosting',
      applicantLocationRequirements: content.job.location.isRemote
        ? {
            '@type': 'Country',
            name: content.job.location.label ?? tenantName,
          }
        : undefined,
      baseSalary:
        content.job.compensation.salaryMin !== null || content.job.compensation.salaryMax !== null
          ? {
              '@type': 'MonetaryAmount',
              currency: content.job.compensation.salaryCurrency ?? undefined,
              value: {
                '@type': 'QuantitativeValue',
                maxValue: content.job.compensation.salaryMax ?? undefined,
                minValue: content.job.compensation.salaryMin ?? undefined,
                unitText: content.job.compensation.salaryType ?? undefined,
              },
            }
          : undefined,
      datePosted: content.job.createdAt ?? undefined,
      description: content.job.description,
      employmentType: content.job.commitment ?? undefined,
      hiringOrganization: {
        '@type': 'Organization',
        name: content.job.employer.displayName ?? tenantName,
        logo: content.job.employer.logoUrl ?? undefined,
      },
      image: content.job.primaryImage?.url,
      jobLocation: content.job.location.label
        ? {
            '@type': 'Place',
            address: content.job.location.label,
          }
        : undefined,
      title: content.job.title,
      url: canonicalUrl,
      validThrough: content.job.deadlineAt ?? undefined,
    };
  }

  if (content?.kind === 'marketplace-detail' && content.item) {
    const price = content.item.price.amount ?? (content.item.price.priceType === 'free' ? 0 : undefined);

    return {
      '@context': 'https://schema.org',
      '@type': 'Product',
      category: content.item.category?.name ?? undefined,
      description: content.item.description,
      image: content.item.primaryImage?.url,
      name: content.item.title,
      offers: {
        '@type': 'Offer',
        price,
        priceCurrency: content.item.price.currency ?? undefined,
        availability:
          content.item.status === 'active'
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock',
        url: canonicalUrl,
        seller: {
          '@type': 'Person',
          name: content.item.seller.displayName ?? tenantName,
        },
      },
      url: canonicalUrl,
    };
  }

  if (content?.kind === 'organisation-detail' && content.organisation) {
    return {
      '@context': 'https://schema.org',
      '@type': 'Organization',
      aggregateRating:
        content.organisation.stats.averageRating > 0 && content.organisation.stats.reviewCount > 0
          ? {
              '@type': 'AggregateRating',
              ratingCount: content.organisation.stats.reviewCount,
              ratingValue: content.organisation.stats.averageRating,
            }
          : undefined,
      description: content.organisation.description,
      email: content.organisation.contactEmail ?? undefined,
      logo: content.organisation.logoImage?.url,
      name: content.organisation.name,
      url: content.organisation.website ?? canonicalUrl,
    };
  }

  if (content?.kind === 'public-detail' && content.item) {
    return {
      '@context': 'https://schema.org',
      '@type': 'WebPage',
      description: content.item.description,
      name: content.item.title,
      url: canonicalUrl,
    };
  }

  return {
    '@context': 'https://schema.org',
    '@type': (tenant?.seo?.meta_description ?? tenant?.seo?.description) ? 'Organization' : 'WebPage',
    name: pageTitle || tenantName,
    url: canonicalUrl,
  };
}

function getRouteTitle(route: RouteOwnership, content: PublicRouteContent | null, t: Translator): string {
  if (content?.kind === 'blog-detail' && content.post?.title) {
    return content.post.title;
  }

  if (content?.kind === 'cms-page' && content.page?.title) {
    return content.page.title;
  }

  if (content?.kind === 'public-detail' && content.item?.title) {
    return content.item.title;
  }

  if (content?.kind === 'listing-detail' && content.listing?.title) {
    return content.listing.title;
  }

  if (content?.kind === 'event-detail' && content.event?.title) {
    return content.event.title;
  }

  if (content?.kind === 'job-detail' && content.job?.title) {
    return content.job.title;
  }

  if (content?.kind === 'marketplace-detail' && content.item?.title) {
    return content.item.title;
  }

  if (content?.kind === 'organisation-detail' && content.organisation?.name) {
    return content.organisation.name;
  }

  return t(route.labelKey ?? 'pages.home.title');
}

function getRouteLead(
  route: RouteOwnership,
  tenantName: string,
  t: Translator,
  content: PublicRouteContent | null,
): string {
  if (content?.kind === 'blog-detail' && content.post?.meta_description) {
    return content.post.meta_description;
  }

  if (content?.kind === 'cms-page' && content.page?.meta_description) {
    return content.page.meta_description;
  }

  if (content?.kind === 'public-detail' && content.item?.description) {
    return content.item.description;
  }

  if (content?.kind === 'listing-detail' && content.listing?.excerpt) {
    return content.listing.excerpt;
  }

  if (content?.kind === 'event-detail' && content.event?.excerpt) {
    return content.event.excerpt;
  }

  if (content?.kind === 'job-detail' && content.job?.excerpt) {
    return content.job.excerpt;
  }

  if (content?.kind === 'marketplace-detail' && content.item?.excerpt) {
    return content.item.excerpt;
  }

  if (content?.kind === 'organisation-detail' && content.organisation?.excerpt) {
    return content.organisation.excerpt;
  }

  return t(`pages.${route.routeKey}.lead`, { tenantName });
}

function withTenantBase(tenantBasePath: string, path: string): string {
  const normalizedBase = tenantBasePath.replace(/\/+$/, '');
  const normalizedPath = path.replace(/^\/+/, '');

  if (!normalizedBase && !normalizedPath) {
    return '/';
  }

  if (!normalizedPath) {
    return normalizedBase || '/';
  }

  return `${normalizedBase}/${normalizedPath}` || '/';
}
