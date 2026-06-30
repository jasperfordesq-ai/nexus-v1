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
  PublicListing,
  PublicListingsIndex,
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
  { href: 'about', labelKey: 'navigation.about' },
  { href: 'help', labelKey: 'navigation.help' },
  { href: 'contact', labelKey: 'navigation.contact' },
  { href: 'faq', labelKey: 'navigation.faq' },
  { href: 'blog', labelKey: 'navigation.blog' },
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
  const accentColor = safeCssColor(tenant?.branding?.primary_color);
  const pageTitle = getRouteTitle(route, content, t);
  const pageLead = getRouteLead(route, tenantName, t, content);
  const style = accentColor
    ? ({ '--nexus-accent': accentColor } as CSSProperties & Record<'--nexus-accent', string>)
    : undefined;

  return (
    <div className="public-shell" style={style}>
      <StructuredData
        canonicalUrl={canonicalUrl}
        content={content}
        pageTitle={pageTitle}
        tenant={tenant}
        tenantName={tenantName}
      />
      <header className="site-header">
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
        <section className="hero-band">
          <p className="eyebrow">{route.routeKey === 'home' ? t('pages.home.eyebrow') : tenantName}</p>
          <h1>{pageTitle}</h1>
          <p>{pageLead}</p>
          {route.routeKey === 'home' ? (
            <div className="action-row">
              <a className="button primary" href={withTenantBase(tenantBasePath, 'contact')}>
                {t('pages.home.primaryAction')}
              </a>
              <a className="button secondary" href={withTenantBase(tenantBasePath, 'blog')}>
                {t('pages.home.secondaryAction')}
              </a>
            </div>
          ) : null}
        </section>

        <section className="content-band">
          {renderRouteContent(route, routeSegments, content, tenantName, tenantBasePath, t)}
        </section>
      </main>

      <footer className="site-footer">
        <p>{t('footer.attribution')}</p>
        <p>{t('footer.copyright')}</p>
        <a href={canonicalUrl}>{t('metadata.canonicalLabel')}</a>
      </footer>
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
  return Number.isInteger(value) ? String(value) : String(value);
}

function compactText(values: Array<null | string | undefined>): string[] {
  return values.filter((value): value is string => Boolean(value));
}

function compactImages(values: Array<PublicListing['primaryImage']>): PublicListing['gallery'] {
  return values.filter((value): value is PublicListing['gallery'][number] => value !== null);
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
