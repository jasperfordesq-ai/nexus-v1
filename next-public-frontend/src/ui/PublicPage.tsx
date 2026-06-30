// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { CSSProperties, ReactNode } from 'react';

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
    <div className="public-shell" style={style}>
      <StructuredData
        canonicalUrl={canonicalUrl}
        content={content}
        pageTitle={pageTitle}
        tenant={tenant}
        tenantName={tenantName}
      />
      <header className="site-header brand-chrome">
        <a className="brand-link" href={withTenantBase(tenantBasePath, '')}>
          {logoUrl ? <img alt="" className="brand-logo" src={logoUrl} /> : null}
          <span>
            <strong>{tenantName}</strong>
            <span>{tagline}</span>
          </span>
        </a>
        <nav aria-label={t('navigation.aria')} className="public-nav">
          {navigationItems.map((item) => (
            <a href={withTenantBase(tenantBasePath, item.href)} key={item.labelKey}>
              {t(item.labelKey)}
            </a>
          ))}
        </nav>
      </header>

      <main>
        <section className={`hero-band${isHome ? ' home-hero' : ''}`}>
          <div className="hero-copy">
            <p className="eyebrow">{isHome ? t('pages.home.eyebrow') : tenantName}</p>
            <h1>{pageTitle}</h1>
            <p>{pageLead}</p>
            {isHome ? (
              <div className="action-row">
                <a className="button primary" href={withTenantBase(tenantBasePath, 'contact')}>
                  {t('pages.home.primaryAction')}
                </a>
                <a className="button secondary" href={withTenantBase(tenantBasePath, 'blog')}>
                  {t('pages.home.secondaryAction')}
                </a>
              </div>
            ) : null}
          </div>
          {isHome ? (
            <aside aria-label={t('pages.home.sectionTitle')} className="home-hero-panel">
              <div className="home-hero-brand">
                {logoUrl ? <img alt="" className="brand-logo" src={logoUrl} /> : null}
                <div>
                  <strong>{tenantName}</strong>
                  <span>{tagline}</span>
                </div>
              </div>
              <p>{t('pages.home.sectionBody')}</p>
              <div className="home-hero-links">
                {homeHeroLinks.map((item) => (
                  <a href={withTenantBase(tenantBasePath, item.href)} key={item.labelKey}>
                    {t(item.labelKey)}
                  </a>
                ))}
              </div>
            </aside>
          ) : null}
        </section>

        <section className="content-band">
          {renderRouteContent(route, routeSegments, content, tenantName, tenantBasePath, t)}
        </section>
      </main>

      <footer className="site-footer">
        <div className="footer-grid">
          <div className="footer-brand">
            <a className="brand-link" href={withTenantBase(tenantBasePath, '')}>
              {logoUrl ? <img alt="" className="brand-logo" src={logoUrl} /> : null}
              <span>
                <strong>{tenantName}</strong>
                <span>{tagline}</span>
              </span>
            </a>
            <p>{t('footer.attribution')}</p>
          </div>
          {footerSections.map((section) => (
            <nav aria-label={t(section.labelKey)} className="footer-nav-group" key={section.labelKey}>
              <h2>{t(section.labelKey)}</h2>
              <ul>
                {section.links.map((link) => (
                  <li key={`${section.labelKey}-${link.href}`}>
                    <a href={withTenantBase(tenantBasePath, link.href)}>{t(link.labelKey)}</a>
                  </li>
                ))}
              </ul>
            </nav>
          ))}
        </div>
        <div className="footer-bottom">
          <p>{t('footer.copyright')}</p>
          <a href={canonicalUrl}>{t('metadata.canonicalLabel')}</a>
        </div>
      </footer>
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
      <article className="public-panel">
        <h2>{t('pages.home.sectionTitle')}</h2>
        <p>{t('pages.home.sectionBody')}</p>
      </article>
    );
  }

  if (content?.kind === 'blog-index') {
    return <BlogIndex posts={content.posts} tenantBasePath={tenantBasePath} t={t} />;
  }

  if (content?.kind === 'blog-detail' && content.post) {
    return (
      <article className="public-panel article-content">
        <h2>{content.post.title}</h2>
        <HtmlBlock html={content.post.content || content.post.excerpt || ''} />
      </article>
    );
  }

  if (content?.kind === 'cms-page' && content.page) {
    return (
      <article className="public-panel article-content">
        <h2>{content.page.title}</h2>
        <HtmlBlock html={content.page.content || ''} />
      </article>
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
    return (
      <article className="public-panel">
        <h2>{t('pages.blogDetail.title')}</h2>
        <p>{t('pages.blogDetail.missing')}</p>
      </article>
    );
  }

  if (route.routeKey === 'cms-page') {
    return (
      <article className="public-panel">
        <h2>{routeSegments.at(-1) ?? t('pages.cmsPage.title')}</h2>
        <p>{t('pages.cmsPage.missing')}</p>
      </article>
    );
  }

  return (
    <article className="public-panel">
      <h2>{t(route.labelKey ?? 'pages.about.title')}</h2>
      <p>{t(`pages.${route.routeKey}.body`, { tenantName })}</p>
    </article>
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
    return (
      <article className="public-panel">
        <h2>{t('pages.blog.title')}</h2>
        <p>{t('pages.blog.empty')}</p>
      </article>
    );
  }

  return (
    <div className="post-list">
      {posts.map((post) => (
        <article className="public-panel" key={post.slug}>
          <h2>
            <a href={withTenantBase(tenantBasePath, `blog/${post.slug}`)}>{post.title}</a>
          </h2>
          {post.excerpt ? <p>{post.excerpt}</p> : null}
        </article>
      ))}
    </div>
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
    return (
      <article className="public-panel">
        <h2>{t('pages.listings.title')}</h2>
        <p>{t('listings.empty')}</p>
      </article>
    );
  }

  return (
    <div className="listings-grid">
      {listings.items.map((listing) => (
        <article className="listing-card" key={listing.id}>
          <a className="listing-card-image" href={withTenantBase(tenantBasePath, `listings/${listing.slug}`)}>
            {listing.primaryImage ? (
              <img
                alt={listing.primaryImage.altText || t('listings.imageAltFallback')}
                src={listing.primaryImage.url}
              />
            ) : (
              <span aria-hidden="true" />
            )}
          </a>
          <div className="listing-card-body">
            <p className="listing-card-meta">
              {compactText([listing.category?.name, formatTimeValue(listing, t)]).join(' · ')}
            </p>
            <h2>
              <a href={withTenantBase(tenantBasePath, `listings/${listing.slug}`)}>{listing.title}</a>
            </h2>
            <p>{listing.excerpt || listing.description}</p>
            <dl className="listing-facts">
              <DefinitionRow label={t('listings.locationLabel')} value={listing.location.label} />
              <DefinitionRow label={t('listings.providerLabel')} value={listing.provider.displayName} />
            </dl>
          </div>
        </article>
      ))}
    </div>
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
    <article className="listing-detail">
      <nav aria-label={t('listings.breadcrumbLabel')} className="listing-breadcrumb">
        <a href={withTenantBase(tenantBasePath, 'listings')}>{t('listings.backToListings')}</a>
        <span aria-hidden="true">/</span>
        <span>{listing.title}</span>
      </nav>

      {listing.primaryImage ? (
        <img
          alt={listing.primaryImage.altText || t('listings.imageAltFallback')}
          className="listing-hero-image"
          src={listing.primaryImage.url}
        />
      ) : null}

      <div className="listing-detail-grid">
        <div className="listing-detail-main">
          <section className="public-panel article-content">
            <h2>{listing.title}</h2>
            <p>{listing.description}</p>
          </section>

          {gallery.length > 0 ? (
            <section className="public-panel">
              <h2>{t('listings.galleryLabel')}</h2>
              <div className="listing-gallery">
                {gallery.map((image) => (
                  <img
                    alt={image.altText || t('listings.imageAltFallback')}
                    key={`${image.url}-${image.sortOrder ?? 0}`}
                    src={image.url}
                  />
                ))}
              </div>
            </section>
          ) : null}
        </div>

        <aside className="public-panel listing-detail-aside">
          <h2>{t('listings.providerLabel')}</h2>
          <p>{listing.provider.displayName}</p>
          <dl className="listing-facts stacked">
            <DefinitionRow label={t('listings.categoryLabel')} value={listing.category?.name} />
            <DefinitionRow label={t('listings.valueLabel')} value={formatTimeValue(listing, t)} />
            <DefinitionRow label={t('listings.locationLabel')} value={listing.location.label} />
            <DefinitionRow label={t('listings.statusLabel')} value={listing.status} />
            <DefinitionRow label={t('listings.updatedLabel')} value={formatDate(listing.updatedAt)} />
            <DefinitionRow label={t('listings.createdLabel')} value={formatDate(listing.createdAt)} />
          </dl>
        </aside>
      </div>
    </article>
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
    return (
      <article className="public-panel">
        <h2>{t('pages.events.title')}</h2>
        <p>{t('events.empty')}</p>
      </article>
    );
  }

  return (
    <div className="listings-grid">
      {events.events.map((event) => (
        <article className="listing-card" key={event.id}>
          <a className="listing-card-image" href={withTenantBase(tenantBasePath, `events/${event.slug}`)}>
            {event.primaryImage ? (
              <img alt={event.primaryImage.altText || t('events.imageAltFallback')} src={event.primaryImage.url} />
            ) : (
              <span aria-hidden="true" />
            )}
          </a>
          <div className="listing-card-body">
            <p className="listing-card-meta">
              {compactText([event.category?.name, formatEventRange(event)]).join(' / ')}
            </p>
            <h2>
              <a href={withTenantBase(tenantBasePath, `events/${event.slug}`)}>{event.title}</a>
            </h2>
            <p>{event.excerpt || event.description}</p>
            <dl className="listing-facts">
              <DefinitionRow label={t('events.locationLabel')} value={event.location.label} />
              <DefinitionRow label={t('events.organiserLabel')} value={event.organiser.displayName} />
            </dl>
          </div>
        </article>
      ))}
    </div>
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
    <article className="listing-detail">
      <nav aria-label={t('events.breadcrumbLabel')} className="listing-breadcrumb">
        <a href={withTenantBase(tenantBasePath, 'events')}>{t('events.backToEvents')}</a>
        <span aria-hidden="true">/</span>
        <span>{event.title}</span>
      </nav>

      {event.primaryImage ? (
        <img
          alt={event.primaryImage.altText || t('events.imageAltFallback')}
          className="listing-hero-image"
          src={event.primaryImage.url}
        />
      ) : null}

      <div className="listing-detail-grid">
        <div className="listing-detail-main">
          <section className="public-panel article-content">
            <h2>{event.title}</h2>
            <p>{event.description}</p>
          </section>
        </div>

        <aside className="public-panel listing-detail-aside">
          <h2>{t('events.organiserLabel')}</h2>
          <p>{event.organiser.displayName}</p>
          <dl className="listing-facts stacked">
            <DefinitionRow label={t('events.dateLabel')} value={formatEventRange(event)} />
            <DefinitionRow label={t('events.categoryLabel')} value={event.category?.name} />
            <DefinitionRow label={t('events.locationLabel')} value={event.location.label} />
            <DefinitionRow label={t('events.statusLabel')} value={event.status} />
            <DefinitionRow label={t('events.updatedLabel')} value={formatDate(event.updatedAt)} />
            <DefinitionRow label={t('events.createdLabel')} value={formatDate(event.createdAt)} />
          </dl>
        </aside>
      </div>
    </article>
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
    return (
      <article className="public-panel">
        <h2>{t('pages.jobs.title')}</h2>
        <p>{t('jobs.empty')}</p>
      </article>
    );
  }

  return (
    <div className="listings-grid">
      {jobs.jobs.map((job) => (
        <article className="listing-card" key={job.id}>
          <a className="listing-card-image" href={withTenantBase(tenantBasePath, `jobs/${job.slug}`)}>
            {job.primaryImage ? (
              <img alt={job.primaryImage.altText || t('jobs.imageAltFallback')} src={job.primaryImage.url} />
            ) : (
              <span aria-hidden="true" />
            )}
          </a>
          <div className="listing-card-body">
            <p className="listing-card-meta">
              {compactText([job.category?.name, formatJobType(job), formatJobCompensation(job, t)]).join(' / ')}
            </p>
            <h2>
              <a href={withTenantBase(tenantBasePath, `jobs/${job.slug}`)}>{job.title}</a>
            </h2>
            <p>{job.excerpt || job.description}</p>
            <dl className="listing-facts">
              <DefinitionRow label={t('jobs.locationLabel')} value={formatJobLocation(job, t)} />
              <DefinitionRow label={t('jobs.employerLabel')} value={job.employer.displayName} />
            </dl>
          </div>
        </article>
      ))}
    </div>
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
    <article className="listing-detail">
      <nav aria-label={t('jobs.breadcrumbLabel')} className="listing-breadcrumb">
        <a href={withTenantBase(tenantBasePath, 'jobs')}>{t('jobs.backToJobs')}</a>
        <span aria-hidden="true">/</span>
        <span>{job.title}</span>
      </nav>

      {job.primaryImage ? (
        <img
          alt={job.primaryImage.altText || t('jobs.imageAltFallback')}
          className="listing-hero-image"
          src={job.primaryImage.url}
        />
      ) : null}

      <div className="listing-detail-grid">
        <div className="listing-detail-main">
          <section className="public-panel article-content">
            <h2>{job.title}</h2>
            <p>{job.description}</p>
          </section>

          {job.skills.length > 0 ? (
            <section className="public-panel">
              <h2>{t('jobs.skillsLabel')}</h2>
              <p>{job.skills.join(', ')}</p>
            </section>
          ) : null}

          {gallery.length > 0 ? (
            <section className="public-panel">
              <h2>{t('jobs.galleryLabel')}</h2>
              <div className="listing-gallery">
                {gallery.map((image) => (
                  <img
                    alt={image.altText || t('jobs.imageAltFallback')}
                    key={`${image.url}-${image.sortOrder ?? 0}`}
                    src={image.url}
                  />
                ))}
              </div>
            </section>
          ) : null}
        </div>

        <aside className="public-panel listing-detail-aside">
          <h2>{t('jobs.employerLabel')}</h2>
          <p>{job.employer.displayName}</p>
          <dl className="listing-facts stacked">
            <DefinitionRow label={t('jobs.compensationLabel')} value={formatJobCompensation(job, t)} />
            <DefinitionRow label={t('jobs.locationLabel')} value={formatJobLocation(job, t)} />
            <DefinitionRow label={t('jobs.categoryLabel')} value={job.category?.name} />
            <DefinitionRow label={t('jobs.typeLabel')} value={job.jobType} />
            <DefinitionRow label={t('jobs.commitmentLabel')} value={job.commitment} />
            <DefinitionRow label={t('jobs.deadlineLabel')} value={formatDate(job.deadlineAt)} />
            <DefinitionRow label={t('jobs.statusLabel')} value={job.status} />
            <DefinitionRow label={t('jobs.updatedLabel')} value={formatDate(job.updatedAt)} />
            <DefinitionRow label={t('jobs.createdLabel')} value={formatDate(job.createdAt)} />
          </dl>
        </aside>
      </div>
    </article>
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
    return (
      <article className="public-panel">
        <h2>{t('pages.marketplace.title')}</h2>
        <p>{t('marketplaceItems.empty')}</p>
      </article>
    );
  }

  return (
    <div className="listings-grid">
      {items.items.map((item) => (
        <article className="listing-card" key={item.id}>
          <a className="listing-card-image" href={withTenantBase(tenantBasePath, `marketplace/${item.slug}`)}>
            {item.primaryImage ? (
              <img
                alt={item.primaryImage.altText || t('marketplaceItems.imageAltFallback')}
                src={item.primaryImage.url}
              />
            ) : (
              <span aria-hidden="true" />
            )}
          </a>
          <div className="listing-card-body">
            <p className="listing-card-meta">
              {compactText([item.category?.name, formatMarketplacePrice(item, t)]).join(' / ')}
            </p>
            <h2>
              <a href={withTenantBase(tenantBasePath, `marketplace/${item.slug}`)}>{item.title}</a>
            </h2>
            <p>{item.excerpt || item.description}</p>
            <dl className="listing-facts">
              <DefinitionRow label={t('marketplaceItems.locationLabel')} value={item.location.label} />
              <DefinitionRow label={t('marketplaceItems.sellerLabel')} value={item.seller.displayName} />
            </dl>
          </div>
        </article>
      ))}
    </div>
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
    <article className="listing-detail">
      <nav aria-label={t('marketplaceItems.breadcrumbLabel')} className="listing-breadcrumb">
        <a href={withTenantBase(tenantBasePath, 'marketplace')}>{t('marketplaceItems.backToMarketplace')}</a>
        <span aria-hidden="true">/</span>
        <span>{item.title}</span>
      </nav>

      {item.primaryImage ? (
        <img
          alt={item.primaryImage.altText || t('marketplaceItems.imageAltFallback')}
          className="listing-hero-image"
          src={item.primaryImage.url}
        />
      ) : null}

      <div className="listing-detail-grid">
        <div className="listing-detail-main">
          <section className="public-panel article-content">
            <h2>{item.title}</h2>
            <p>{item.description}</p>
          </section>

          {gallery.length > 0 ? (
            <section className="public-panel">
              <h2>{t('marketplaceItems.galleryLabel')}</h2>
              <div className="listing-gallery">
                {gallery.map((image) => (
                  <img
                    alt={image.altText || t('marketplaceItems.imageAltFallback')}
                    key={`${image.url}-${image.sortOrder ?? 0}`}
                    src={image.url}
                  />
                ))}
              </div>
            </section>
          ) : null}
        </div>

        <aside className="public-panel listing-detail-aside">
          <h2>{t('marketplaceItems.sellerLabel')}</h2>
          <p>{item.seller.displayName}</p>
          <dl className="listing-facts stacked">
            <DefinitionRow label={t('marketplaceItems.priceLabel')} value={formatMarketplacePrice(item, t)} />
            <DefinitionRow label={t('marketplaceItems.categoryLabel')} value={item.category?.name} />
            <DefinitionRow label={t('marketplaceItems.locationLabel')} value={item.location.label} />
            <DefinitionRow label={t('marketplaceItems.conditionLabel')} value={item.condition} />
            <DefinitionRow label={t('marketplaceItems.deliveryLabel')} value={formatMarketplaceDelivery(item, t)} />
            <DefinitionRow label={t('marketplaceItems.quantityLabel')} value={formatNullableNumber(item.quantity)} />
            <DefinitionRow label={t('marketplaceItems.statusLabel')} value={item.status} />
            <DefinitionRow label={t('marketplaceItems.expiresLabel')} value={formatDate(item.expiresAt)} />
            <DefinitionRow label={t('marketplaceItems.updatedLabel')} value={formatDate(item.updatedAt)} />
            <DefinitionRow label={t('marketplaceItems.createdLabel')} value={formatDate(item.createdAt)} />
          </dl>
        </aside>
      </div>
    </article>
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
    return (
      <article className="public-panel">
        <h2>{t('pages.organisations.title')}</h2>
        <p>{t('organisationProfiles.empty')}</p>
      </article>
    );
  }

  return (
    <div className="listings-grid">
      {organisations.organisations.map((organisation) => (
        <article className="listing-card" key={organisation.id}>
          <a className="listing-card-image" href={withTenantBase(tenantBasePath, `organisations/${organisation.slug}`)}>
            {organisation.logoImage ? (
              <img
                alt={organisation.logoImage.altText || t('organisationProfiles.logoAltFallback')}
                src={organisation.logoImage.url}
              />
            ) : (
              <span aria-hidden="true" />
            )}
          </a>
          <div className="listing-card-body">
            <p className="listing-card-meta">
              {compactText([
                formatCount(organisation.stats.opportunityCount, 'organisationProfiles.opportunityCount', t),
                formatCount(organisation.stats.volunteerCount, 'organisationProfiles.volunteerCount', t),
              ]).join(' / ')}
            </p>
            <h2>
              <a href={withTenantBase(tenantBasePath, `organisations/${organisation.slug}`)}>{organisation.name}</a>
            </h2>
            <p>{organisation.excerpt || organisation.description}</p>
            <dl className="listing-facts">
              <DefinitionRow label={t('organisationProfiles.ownerLabel')} value={organisation.owner.displayName} />
              <DefinitionRow label={t('organisationProfiles.websiteLabel')} value={organisation.website} />
            </dl>
          </div>
        </article>
      ))}
    </div>
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
    <article className="listing-detail">
      <nav aria-label={t('organisationProfiles.breadcrumbLabel')} className="listing-breadcrumb">
        <a href={withTenantBase(tenantBasePath, 'organisations')}>{t('organisationProfiles.backToOrganisations')}</a>
        <span aria-hidden="true">/</span>
        <span>{organisation.name}</span>
      </nav>

      {organisation.logoImage ? (
        <img
          alt={organisation.logoImage.altText || t('organisationProfiles.logoAltFallback')}
          className="listing-hero-image"
          src={organisation.logoImage.url}
        />
      ) : null}

      <div className="listing-detail-grid">
        <div className="listing-detail-main">
          <section className="public-panel article-content">
            <h2>{organisation.name}</h2>
            <p>{organisation.description}</p>
          </section>
        </div>

        <aside className="public-panel listing-detail-aside">
          <h2>{t('organisationProfiles.profileLabel')}</h2>
          <dl className="listing-facts stacked">
            <DefinitionRow label={t('organisationProfiles.ownerLabel')} value={organisation.owner.displayName} />
            <DefinitionRow label={t('organisationProfiles.websiteLabel')} value={organisation.website} />
            <DefinitionRow label={t('organisationProfiles.emailLabel')} value={organisation.contactEmail} />
            <DefinitionRow label={t('organisationProfiles.locationLabel')} value={organisation.location.label} />
            <DefinitionRow
              label={t('organisationProfiles.opportunitiesLabel')}
              value={formatCount(organisation.stats.opportunityCount, 'organisationProfiles.opportunityCount', t)}
            />
            <DefinitionRow
              label={t('organisationProfiles.volunteersLabel')}
              value={formatCount(organisation.stats.volunteerCount, 'organisationProfiles.volunteerCount', t)}
            />
            <DefinitionRow
              label={t('organisationProfiles.hoursLabel')}
              value={t('organisationProfiles.hourCount', { count: formatNumber(organisation.stats.totalHours) })}
            />
            <DefinitionRow label={t('organisationProfiles.ratingLabel')} value={formatRating(organisation)} />
            <DefinitionRow label={t('organisationProfiles.typeLabel')} value={organisation.orgType} />
            <DefinitionRow label={t('organisationProfiles.statusLabel')} value={organisation.status} />
            <DefinitionRow label={t('organisationProfiles.updatedLabel')} value={formatDate(organisation.updatedAt)} />
            <DefinitionRow label={t('organisationProfiles.createdLabel')} value={formatDate(organisation.createdAt)} />
          </dl>
        </aside>
      </div>
    </article>
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
    return (
      <article className="public-panel">
        <h2>{emptyTitle}</h2>
        <p>{t('pages.publicCollection.empty')}</p>
      </article>
    );
  }

  return (
    <div className="post-list">
      {items.map((item) => (
        <article className="public-panel" key={item.slug ?? item.id}>
          <h2>
            <a href={withTenantBase(basePath, item.slug ?? item.id)}>{item.title}</a>
          </h2>
          {item.description ? <p>{item.description}</p> : null}
        </article>
      ))}
    </div>
  );
}

function DefinitionRow({ label, value }: { label: string; value?: null | string }): ReactNode {
  if (!value) {
    return null;
  }

  return (
    <div>
      <dt>{label}</dt>
      <dd>{value}</dd>
    </div>
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
    <article className="public-panel article-content">
      <h2>{item.title}</h2>
      {item.description ? <p>{item.description}</p> : null}
    </article>
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
