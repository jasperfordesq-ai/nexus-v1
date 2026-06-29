// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { CSSProperties, ReactNode } from 'react';

import { resolveAssetUrl, safeCssColor } from '../lib/assets';
import type { Translator } from '../lib/i18n';
import type { RouteOwnership } from '../lib/public-routes';
import type { BlogPostSummary, PublicRouteContent, TenantBootstrap } from '../lib/tenant-api';
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

        <section className="content-band">{renderRouteContent(route, routeSegments, content, tenantName, t)}</section>
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
    return <BlogIndex posts={content.posts} t={t} />;
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

function BlogIndex({ posts, t }: { posts: BlogPostSummary[]; t: Translator }): ReactNode {
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
            <a href={`/blog/${post.slug}`}>{post.title}</a>
          </h2>
          {post.excerpt ? <p>{post.excerpt}</p> : null}
        </article>
      ))}
    </div>
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
  const data =
    content?.kind === 'blog-detail' && content.post
      ? {
          '@context': 'https://schema.org',
          '@type': 'BlogPosting',
          headline: content.post.title,
          url: canonicalUrl,
        }
      : {
          '@context': 'https://schema.org',
          '@type': tenant?.seo?.description ? 'Organization' : 'WebPage',
          name: pageTitle || tenantName,
          url: canonicalUrl,
        };

  return <script dangerouslySetInnerHTML={{ __html: JSON.stringify(data) }} type="application/ld+json" />;
}

function getRouteTitle(route: RouteOwnership, content: PublicRouteContent | null, t: Translator): string {
  if (content?.kind === 'blog-detail' && content.post?.title) {
    return content.post.title;
  }

  if (content?.kind === 'cms-page' && content.page?.title) {
    return content.page.title;
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
