// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { CSSProperties, ReactNode } from 'react';
import { Card, Chip, Link, Surface } from '@heroui/react';

import { resolveAssetUrl, safeCssColor } from '../lib/assets';
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

interface PublicPageProps {
  canonicalUrl: string;
  content?: PublicRouteContent | null;
  route: RouteOwnership;
  routeSegments: string[];
  tenant: TenantBootstrap | null;
  tenantBasePath: string;
  t: Translator;
}

const navigationItems = [
  { href: '', labelKey: 'navigation.home' },
  { href: 'listings', labelKey: 'navigation.listings' },
  { href: 'events', labelKey: 'navigation.events' },
  { href: 'jobs', labelKey: 'navigation.jobs' },
  { href: 'marketplace', labelKey: 'navigation.marketplace' },
  { href: 'organisations', labelKey: 'navigation.organisations' },
  { href: 'resources', labelKey: 'navigation.resources' },
  { href: 'blog', labelKey: 'navigation.blog' },
  { href: 'help', labelKey: 'navigation.help' },
  { href: 'contact', labelKey: 'navigation.contact' },
];

const homeHeroLinks = [
  { href: 'listings', labelKey: 'navigation.listings' },
  { href: 'events', labelKey: 'navigation.events' },
  { href: 'resources', labelKey: 'navigation.resources' },
];

const footerSections = [
  {
    labelKey: 'footer.platform',
    links: [
      { href: 'listings', labelKey: 'navigation.listings' },
      { href: 'events', labelKey: 'navigation.events' },
      { href: 'jobs', labelKey: 'navigation.jobs' },
      { href: 'marketplace', labelKey: 'navigation.marketplace' },
      { href: 'organisations', labelKey: 'navigation.organisations' },
    ],
  },
  {
    labelKey: 'footer.support',
    links: [
      { href: 'help', labelKey: 'navigation.help' },
      { href: 'contact', labelKey: 'navigation.contact' },
      { href: 'faq', labelKey: 'navigation.faq' },
      { href: 'about', labelKey: 'navigation.about' },
    ],
  },
  {
    labelKey: 'footer.legal',
    links: [
      { href: 'legal', labelKey: 'pages.legal.title' },
      { href: 'terms', labelKey: 'pages.terms.title' },
      { href: 'privacy', labelKey: 'pages.privacy.title' },
      { href: 'accessibility', labelKey: 'pages.accessibility.title' },
    ],
  },
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
  const accentColor = safeCssColor(tenant?.branding?.primary_color);
  const secondaryColor = safeCssColor(tenant?.branding?.secondary_color);
  const pageTitle = getRouteTitle(route, content, t);
  const pageLead = getRouteLead(route, tenantName, t, content);
  const style = buildThemeStyle(accentColor, secondaryColor);
  const isHome = route.routeKey === 'home';

  return (
    <Surface
      className="min-h-screen bg-[color:var(--nexus-bg)] text-[color:var(--nexus-ink)]"
      data-nexus-ui="heroui-public"
      style={style}
    >
      <StructuredData
        canonicalUrl={canonicalUrl}
        content={content}
        pageTitle={pageTitle}
        tenant={tenant}
        tenantName={tenantName}
      />
      <header className="mx-auto w-full max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
        <Card
          className="border border-[color:var(--nexus-border)] bg-[color:var(--nexus-surface)]/95 shadow-sm"
          variant="secondary"
        >
          <Card.Content className="flex flex-col gap-4 p-4 lg:flex-row lg:items-center lg:justify-between">
            <BrandLink href={withTenantBase(tenantBasePath, '')} logoUrl={logoUrl} tagline={tagline} tenantName={tenantName} />
            <nav aria-label={t('navigation.aria')} className="flex flex-wrap gap-2 lg:justify-end">
              {navigationItems.map((item) => (
                <Link
                  className="rounded-lg border border-transparent px-3 py-2 text-sm font-semibold text-[color:var(--nexus-muted)] hover:border-[color:var(--nexus-border)] hover:bg-[color:var(--nexus-accent-soft)] hover:text-[color:var(--nexus-ink)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--nexus-accent)]"
                  href={withTenantBase(tenantBasePath, item.href)}
                  key={item.labelKey}
                >
                  {t(item.labelKey)}
                </Link>
              ))}
            </nav>
          </Card.Content>
        </Card>
      </header>

      <main>
        <section
          className={`mx-auto grid w-full max-w-7xl gap-8 px-4 py-10 sm:px-6 lg:px-8 lg:py-14 ${
            isHome ? 'lg:grid-cols-[minmax(0,1fr)_minmax(280px,380px)] lg:items-center' : ''
          }`}
        >
          <div className="min-w-0">
            <Chip className="mb-4" color="accent" size="sm" variant="soft">
              {isHome ? t('pages.home.eyebrow') : tenantName}
            </Chip>
            <h1 className="max-w-4xl text-4xl font-bold leading-tight text-[color:var(--nexus-ink)] sm:text-5xl lg:text-6xl">
              {pageTitle}
            </h1>
            <p className="mt-5 max-w-3xl text-lg leading-8 text-[color:var(--nexus-muted)]">{pageLead}</p>
            {isHome ? (
              <div className="mt-7 flex flex-wrap gap-3">
                <Link
                  className="rounded-lg bg-[color:var(--nexus-accent)] px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:opacity-95 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--nexus-accent)]"
                  href={withTenantBase(tenantBasePath, 'contact')}
                >
                  {t('pages.home.primaryAction')}
                </Link>
                <Link
                  className="rounded-lg border border-[color:var(--nexus-border)] bg-[color:var(--nexus-surface)] px-4 py-2.5 text-sm font-bold text-[color:var(--nexus-ink)] shadow-sm hover:bg-[color:var(--nexus-accent-soft)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--nexus-accent)]"
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
              className="border border-[color:var(--nexus-border)] bg-[color:var(--nexus-surface)] shadow-lg"
              variant="default"
            >
              <Card.Header className="pb-3">
                <BrandMark logoUrl={logoUrl} tagline={tagline} tenantName={tenantName} />
              </Card.Header>
              <Card.Content className="space-y-5 pt-0">
                <p className="leading-7 text-[color:var(--nexus-muted)]">{t('pages.home.sectionBody')}</p>
                <div className="grid gap-2">
                  {homeHeroLinks.map((item) => (
                    <Link
                      className="rounded-lg border border-[color:var(--nexus-border)] bg-[color:var(--nexus-surface-raised)] px-3 py-2.5 font-bold text-[color:var(--nexus-ink)] hover:bg-[color:var(--nexus-accent-soft)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--nexus-accent)]"
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
      </main>

      <footer className="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div className="border-t border-[color:var(--nexus-border)] pt-8">
          <div className="grid gap-8 lg:grid-cols-[minmax(260px,1.4fr)_repeat(3,minmax(150px,1fr))]">
            <div className="grid gap-4">
              <BrandLink
                href={withTenantBase(tenantBasePath, '')}
                logoUrl={logoUrl}
                tagline={tagline}
                tenantName={tenantName}
              />
              <p className="max-w-md leading-7 text-[color:var(--nexus-muted)]">{t('footer.attribution')}</p>
            </div>
            {footerSections.map((section) => (
              <nav aria-label={t(section.labelKey)} className="grid content-start gap-3" key={section.labelKey}>
                <h2 className="text-sm font-bold text-[color:var(--nexus-ink)]">{t(section.labelKey)}</h2>
                <ul className="grid gap-2">
                  {section.links.map((link) => (
                    <li key={`${section.labelKey}-${link.href}`}>
                      <Link
                        className="text-sm text-[color:var(--nexus-muted)] hover:text-[color:var(--nexus-ink)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--nexus-accent)]"
                        href={withTenantBase(tenantBasePath, link.href)}
                      >
                        {t(link.labelKey)}
                      </Link>
                    </li>
                  ))}
                </ul>
              </nav>
            ))}
          </div>
          <div className="mt-7 flex flex-wrap justify-between gap-3 border-t border-[color:var(--nexus-border)] pt-5 text-sm text-[color:var(--nexus-muted)]">
            <p>{t('footer.copyright')}</p>
            <Link
              className="text-[color:var(--nexus-muted)] hover:text-[color:var(--nexus-ink)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--nexus-accent)]"
              href={canonicalUrl}
            >
              {t('metadata.canonicalLabel')}
            </Link>
          </div>
        </div>
      </footer>
    </Surface>
  );
}

function BrandLink({
  href,
  logoUrl,
  tagline,
  tenantName,
}: {
  href: string;
  logoUrl: string | undefined;
  tagline: string;
  tenantName: string;
}): ReactNode {
  return (
    <Link
      className="inline-flex min-w-0 items-center gap-3 rounded-lg text-[color:var(--nexus-ink)] no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--nexus-accent)]"
      href={href}
    >
      <BrandLogo logoUrl={logoUrl} />
      <span className="min-w-0">
        <strong className="block truncate text-base font-bold">{tenantName}</strong>
        <span className="block truncate text-sm text-[color:var(--nexus-muted)]">{tagline}</span>
      </span>
    </Link>
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
      <BrandLogo logoUrl={logoUrl} />
      <div className="min-w-0">
        <strong className="block truncate text-base font-bold text-[color:var(--nexus-ink)]">{tenantName}</strong>
        <span className="block truncate text-sm text-[color:var(--nexus-muted)]">{tagline}</span>
      </div>
    </div>
  );
}

function BrandLogo({ logoUrl }: { logoUrl: string | undefined }): ReactNode {
  if (!logoUrl) {
    return (
      <span
        aria-hidden="true"
        className="block size-12 shrink-0 rounded-lg border border-[color:var(--nexus-border)] bg-[color:var(--nexus-accent-soft)]"
      />
    );
  }

  return (
    <img
      alt=""
      className="size-12 shrink-0 rounded-lg border border-[color:var(--nexus-border)] bg-[color:var(--nexus-surface)] object-contain p-1"
      src={logoUrl}
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

function RichIndexGrid({ children }: { children: ReactNode }): ReactNode {
  return <div className="grid gap-5">{children}</div>;
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
}): ReactNode {
  return (
    <article className="grid gap-5" data-nexus-ui="rich-detail">
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
            className="border border-[color:var(--nexus-border)] bg-[color:var(--nexus-surface)] lg:sticky lg:top-4"
            variant="default"
          >
            <Card.Header>
              <h2 className="text-xl font-bold text-[color:var(--nexus-ink)]">{asideTitle}</h2>
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

function ContentCard({ children, title }: { children: ReactNode; title: string }): ReactNode {
  return (
    <Card className="border border-[color:var(--nexus-border)] bg-[color:var(--nexus-surface)]" variant="default">
      <Card.Header>
        <h2 className="text-xl font-bold text-[color:var(--nexus-ink)]">{title}</h2>
      </Card.Header>
      <Card.Content>
        <div className="space-y-4 leading-7 text-[color:var(--nexus-muted)]">{children}</div>
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
    <nav aria-label={breadcrumbLabel} className="flex flex-wrap items-center gap-2 text-sm text-[color:var(--nexus-muted)]">
      <Link
        className="font-bold text-[color:var(--nexus-accent)] hover:text-[color:var(--nexus-ink)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--nexus-accent)]"
        href={backHref}
      >
        {backLabel}
      </Link>
      <span aria-hidden="true">/</span>
      <span className="text-[color:var(--nexus-ink)]">{title}</span>
    </nav>
  );
}

function FactList({ className = '', facts }: { className?: string; facts: RichFact[] }): ReactNode {
  const visibleFacts = facts.filter((fact) => Boolean(fact.value));

  if (visibleFacts.length === 0) {
    return null;
  }

  return (
    <dl className={('grid gap-3 ' + className).trim()}>
      {visibleFacts.map((fact) => (
        <div className="min-w-0" key={fact.label}>
          <dt className="text-xs font-bold uppercase text-[color:var(--nexus-muted)]">{fact.label}</dt>
          <dd className="mt-1 [overflow-wrap:anywhere] text-[color:var(--nexus-ink)]">{fact.value}</dd>
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
  const content = image ? (
    <img alt={image.altText || imageAltFallback} className="h-full w-full object-cover" src={image.url} />
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
        <img
          alt={image.altText || imageAltFallback}
          className="aspect-[4/3] w-full rounded-lg bg-[color:var(--nexus-accent-soft)] object-cover"
          key={image.url + '-' + (image.sortOrder ?? 0)}
          src={image.url}
        />
      ))}
    </div>
  );
}

function buildThemeStyle(
  accentColor: string | undefined,
  secondaryColor: string | undefined,
): CSSProperties | undefined {
  const style: CSSProperties & Record<string, string> = {};

  if (accentColor) {
    style['--nexus-accent'] = accentColor;
  }

  if (secondaryColor) {
    style['--nexus-accent-secondary'] = secondaryColor;
  }

  return Object.keys(style).length > 0 ? style : undefined;
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
    <RichIndexGrid>
      {listings.items.map((listing) => (
        <RichIndexCard
          description={listing.excerpt || listing.description}
          facts={[
            { label: t('listings.locationLabel'), value: listing.location.label },
            { label: t('listings.providerLabel'), value: listing.provider.displayName },
          ]}
          href={withTenantBase(tenantBasePath, 'listings/' + listing.slug)}
          image={listing.primaryImage}
          imageAltFallback={t('listings.imageAltFallback')}
          key={listing.id}
          meta={[listing.category?.name, formatTimeValue(listing, t)]}
          title={listing.title}
        />
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
    >
      <ContentCard title={listing.title}>
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
    <RichIndexGrid>
      {events.events.map((event) => (
        <RichIndexCard
          description={event.excerpt || event.description}
          facts={[
            { label: t('events.locationLabel'), value: event.location.label },
            { label: t('events.organiserLabel'), value: event.organiser.displayName },
          ]}
          href={withTenantBase(tenantBasePath, 'events/' + event.slug)}
          image={event.primaryImage}
          imageAltFallback={t('events.imageAltFallback')}
          key={event.id}
          meta={[event.category?.name, formatEventRange(event)]}
          title={event.title}
        />
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
    >
      <ContentCard title={event.title}>
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
    <RichIndexGrid>
      {jobs.jobs.map((job) => (
        <RichIndexCard
          description={job.excerpt || job.description}
          facts={[
            { label: t('jobs.locationLabel'), value: formatJobLocation(job, t) },
            { label: t('jobs.employerLabel'), value: job.employer.displayName },
          ]}
          href={withTenantBase(tenantBasePath, 'jobs/' + job.slug)}
          image={job.primaryImage}
          imageAltFallback={t('jobs.imageAltFallback')}
          key={job.id}
          meta={[job.category?.name, formatJobType(job), formatJobCompensation(job, t)]}
          title={job.title}
        />
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
    >
      <ContentCard title={job.title}>
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
    <RichIndexGrid>
      {items.items.map((item) => (
        <RichIndexCard
          description={item.excerpt || item.description}
          facts={[
            { label: t('marketplaceItems.locationLabel'), value: item.location.label },
            { label: t('marketplaceItems.sellerLabel'), value: item.seller.displayName },
          ]}
          href={withTenantBase(tenantBasePath, 'marketplace/' + item.slug)}
          image={item.primaryImage}
          imageAltFallback={t('marketplaceItems.imageAltFallback')}
          key={item.id}
          meta={[item.category?.name, formatMarketplacePrice(item, t)]}
          title={item.title}
        />
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
    >
      <ContentCard title={item.title}>
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
    <RichIndexGrid>
      {organisations.organisations.map((organisation) => (
        <RichIndexCard
          description={organisation.excerpt || organisation.description}
          facts={[
            { label: t('organisationProfiles.ownerLabel'), value: organisation.owner.displayName },
            { label: t('organisationProfiles.websiteLabel'), value: organisation.website },
          ]}
          href={withTenantBase(tenantBasePath, 'organisations/' + organisation.slug)}
          image={organisation.logoImage}
          imageAltFallback={t('organisationProfiles.logoAltFallback')}
          key={organisation.id}
          meta={[
            formatCount(organisation.stats.opportunityCount, 'organisationProfiles.opportunityCount', t),
            formatCount(organisation.stats.volunteerCount, 'organisationProfiles.volunteerCount', t),
          ]}
          title={organisation.name}
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
    >
      <ContentCard title={organisation.name}>
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
            name: content.job.location.label ?? 'Remote',
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
    '@type': tenant?.seo?.description ? 'Organization' : 'WebPage',
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
