// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Button } from '@heroui/react';
import { useTenant, useFeature, useCookieConsent } from '@/contexts';
import Mail from 'lucide-react/icons/mail';
import Phone from 'lucide-react/icons/phone';
import MapPin from 'lucide-react/icons/map-pin';
import Cookie from 'lucide-react/icons/cookie';
import Bug from 'lucide-react/icons/bug';
import { TenantLogo } from '@/components/branding';
import Sparkles from 'lucide-react/icons/sparkles';
import { RELEASE_STATUS } from '@/config/releaseStatus';
import { SourceRepositoryLink } from './SourceRepositoryLink';

const PROJECT_NEXUS_URL = 'https://project-nexus.ie';
const PROJECT_NEXUS_HOST = new URL(PROJECT_NEXUS_URL).hostname;

export interface FooterProps {
  /** Footer content/links */
  children?: ReactNode;
  /** Copyright text override */
  copyright?: string;
}

/**
 * Footer - Glass-styled footer component
 * Shows tenant branding, contact info, and footer_text from bootstrap API.
 * Desktop footer carries full navigation; mobile keeps a compact attribution strip.
 */
export function Footer({ children, copyright }: FooterProps) {
  const { t } = useTranslation('common');
  const { tenant, branding, tenantPath } = useTenant();
  const hasConnections = useFeature('connections');
  const hasEvents = useFeature('events');
  const hasBlog = useFeature('blog');
  const { resetConsent } = useCookieConsent();
  const year = new Date().getFullYear();

  // Use tenant's footer_text from config if set, otherwise build a default
  const footerText = tenant?.config?.footer_text?.trim()
    || copyright
    || `${branding.name} — ${t('footer.agpl_notice', { year })}`;

  const contact = tenant?.contact;

  return (
    <footer className="relative z-10 border-t border-theme-default mt-auto glass-surface backdrop-blur-sm" data-nosnippet>
      <div className="md:hidden px-4 py-6 pb-[calc(var(--safe-area-bottom)+5rem)]">
        <div className="flex flex-col items-center gap-4">
          {/* NEXUS banner — clickable, links to project-nexus.ie */}
          <a
            href={PROJECT_NEXUS_URL}
            target="_blank"
            rel="noopener noreferrer"
            title={t('footer.nexus_link_label')}
            className="transition-opacity hover:opacity-80"
          >
            <img
              src="/images/project-nexus-open-source-community-platform.png"
              alt={t('footer.nexus_link_label')}
              className="h-24 w-auto object-contain"
              width="1536"
              height="1024"
            />
          </a>
          <SourceRepositoryLink compact className="w-full max-w-[18rem] justify-center" />
          {/* Tenant partner logo — real or placeholder */}
          {tenant?.config?.partner_logo_url ? (
            <img
              src={tenant.config.partner_logo_url as string}
              alt={branding.name}
              className="h-14 w-auto max-w-[12rem] object-contain"
            />
          ) : (
            <div className="w-full max-w-[18rem] border-2 border-dashed border-theme-default/40 rounded-xl h-14 flex items-center justify-center">
              <span className="text-xs text-theme-subtle/40">{t('footer.tenant_logo_placeholder')}</span>
            </div>
          )}
          <div className="flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-[11px] text-theme-subtle/70">
            <span>{t('footer.agpl_notice', { year })}</span>
            <span aria-hidden="true">&middot;</span>
            <Link to={tenantPath('/platform/terms')} className="hover:text-theme-primary transition-colors">
              {t('footer.terms')}
            </Link>
            <span aria-hidden="true">&middot;</span>
            <Link to={tenantPath('/platform/privacy')} className="hover:text-theme-primary transition-colors">
              {t('footer.privacy')}
            </Link>
          </div>
        </div>
      </div>
      <div className="hidden md:block">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {children ? (
          children
        ) : (
          <div className="space-y-8">
            {/* Footer Links Grid */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-8">
              {/* Brand + Contact */}
              <div className="col-span-2 sm:col-span-1 space-y-3">
                <TenantLogo size="sm" showName />
                <p className="text-sm text-theme-subtle">
                  {branding.tagline || 'Building stronger communities through the exchange of time.'}
                </p>
                {/* Contact info from tenant bootstrap */}
                {contact && (
                  <div className="space-y-1.5 pt-1">
                    {contact.email && (
                      <a
                        href={`mailto:${contact.email}`}
                        className="flex items-center gap-1.5 text-sm text-theme-muted hover:text-theme-primary transition-colors"
                      >
                        <Mail className="w-3.5 h-3.5 flex-shrink-0" aria-hidden="true" />
                        {contact.email}
                      </a>
                    )}
                    {contact.phone && (
                      <a
                        href={`tel:${contact.phone}`}
                        className="flex items-center gap-1.5 text-sm text-theme-muted hover:text-theme-primary transition-colors"
                      >
                        <Phone className="w-3.5 h-3.5 flex-shrink-0" aria-hidden="true" />
                        {contact.phone}
                      </a>
                    )}
                    {contact.location && (
                      <p className="flex items-center gap-1.5 text-sm text-theme-muted">
                        <MapPin className="w-3.5 h-3.5 flex-shrink-0" aria-hidden="true" />
                        {contact.location}
                      </p>
                    )}
                  </div>
                )}
              </div>

              {/* Platform */}
              <div>
                <h3 className="text-sm font-semibold text-theme-primary mb-3">{t('footer.platform')}</h3>
                <ul className="space-y-2">
                  <li><FooterLink href={tenantPath('/listings')}>{t('nav.listings')}</FooterLink></li>
                  {hasConnections && <li><FooterLink href={tenantPath('/members')}>{t('nav.members')}</FooterLink></li>}
                  {hasEvents && <li><FooterLink href={tenantPath('/events')}>{t('nav.events')}</FooterLink></li>}
                  {hasBlog && <li><FooterLink href={tenantPath('/blog')}>{t('nav.blog')}</FooterLink></li>}
                </ul>
              </div>

              {/* Support */}
              <div>
                <h3 className="text-sm font-semibold text-theme-primary mb-3">{t('footer.support')}</h3>
                <ul className="space-y-2">
                  <li><FooterLink href={tenantPath('/help')}>{t('footer.help_center')}</FooterLink></li>
                  <li><FooterLink href={tenantPath('/kb')}>{t('nav.knowledge_base')}</FooterLink></li>
                  <li><FooterLink href={tenantPath('/trust-and-safety')}>{t('footer.trust_safety')}</FooterLink></li>
                  <li><FooterLink href={tenantPath('/contact')}>{t('footer.contact_us')}</FooterLink></li>
                  <li><FooterLink href={tenantPath('/about')}>{t('footer.about')}</FooterLink></li>
                  <li>
                    <a
                      href="https://project-nexus.canny.io/"
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-1.5 text-sm text-theme-muted hover:text-theme-primary transition-colors"
                    >
                      <Bug className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                      {t('footer.report_bug')}
                    </a>
                  </li>
                </ul>
              </div>

              {/* Legal */}
              <div>
                <h3 className="text-sm font-semibold text-theme-primary mb-3">{t('footer.legal')}</h3>
                <ul className="space-y-2">
                  <li><FooterLink href={tenantPath('/legal')}>{t('legal.legal_hub')}</FooterLink></li>
                  <li><FooterLink href={tenantPath('/terms')}>{t('legal.terms_of_service')}</FooterLink></li>
                  <li><FooterLink href={tenantPath('/privacy')}>{t('legal.privacy_policy')}</FooterLink></li>
                  <li><FooterLink href={tenantPath('/community-guidelines')}>{t('legal.type_community_guidelines')}</FooterLink></li>
                  <li><FooterLink href={tenantPath('/acceptable-use')}>{t('legal.type_acceptable_use')}</FooterLink></li>
                  <li><FooterLink href={tenantPath('/cookies')}>{t('legal.cookie_policy')}</FooterLink></li>
                  <li><FooterLink href={tenantPath('/accessibility')}>{t('legal.accessibility')}</FooterLink></li>
                </ul>
              </div>
            </div>

            {/* Dynamic footer pages from admin CMS */}
            {tenant?.menu_pages?.footer?.length ? (
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-8">
                <div>
                  <h3 className="text-sm font-semibold text-theme-primary mb-3">{t('footer.pages')}</h3>
                  <ul className="space-y-2">
                    {tenant.menu_pages.footer.map((p) => (
                      <li key={p.slug}><FooterLink href={tenantPath(`/page/${p.slug}`)}>{p.title}</FooterLink></li>
                    ))}
                  </ul>
                </div>
              </div>
            ) : null}

            {/* Tenant Copyright */}
            <div className="border-t border-theme-default pt-6 flex flex-col sm:flex-row items-center justify-between gap-3">
              <p className="text-sm text-theme-subtle">{footerText}</p>
              <Button
                variant="light"
                size="sm"
                onPress={resetConsent}
                className="inline-flex items-center gap-1 text-xs text-theme-subtle hover:text-theme-primary transition-colors h-auto p-0 min-w-0"
                aria-label={t('aria.cookie_settings')}
                startContent={<Cookie className="w-3 h-3" aria-hidden="true" />}
              >
                {t('footer.cookie_settings')}
              </Button>
            </div>

            {/* Release status — GA strip with Features + Changelog links */}
            <div className="border-t border-theme-default pt-4 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-center text-xs text-theme-subtle">
              <Sparkles className="w-3.5 h-3.5 shrink-0 text-success" aria-hidden="true" />
              <span>
                <span className="font-semibold text-theme-primary">{RELEASE_STATUS.stageLabel}</span>
                {' — '}
                {t('release_status.summary')}
              </span>
              <span aria-hidden="true">&middot;</span>
              <Link
                to={RELEASE_STATUS.readMorePath}
                className="underline font-medium hover:text-theme-primary transition-colors focus:outline-none focus:ring-2 focus:ring-primary rounded whitespace-nowrap"
              >
                {t('release_status.features_link')}
              </Link>
              <span aria-hidden="true">&middot;</span>
              <Link
                to={tenantPath('/changelog')}
                className="underline font-medium hover:text-theme-primary transition-colors focus:outline-none focus:ring-2 focus:ring-primary rounded whitespace-nowrap"
              >
                {t('release_status.changelog_link')}
              </Link>
            </div>

            {/* Platform Attribution — 3-column grid */}
            <div className="border-t border-theme-default pt-6 flex flex-col gap-6">
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-6 sm:gap-8 items-start">

                {/* COL 1: Community partner logo */}
                <div className="flex flex-col items-start gap-2">
                  <span className="text-[10px] font-semibold uppercase tracking-widest text-theme-subtle/50">
                    {t('footer.community_partner')}
                  </span>
                  {tenant?.config?.partner_logo_url ? (
                    <img
                      src={tenant.config.partner_logo_url as string}
                      alt={branding.name}
                      className="h-20 w-auto max-w-[12rem] object-contain"
                    />
                  ) : (
                    <div className="h-20 w-48 border-2 border-dashed border-theme-default/40 rounded-xl flex items-center justify-center">
                      <span className="text-xs text-theme-subtle/40 text-center leading-snug px-3">
                        {t('footer.tenant_logo_placeholder')}
                      </span>
                    </div>
                  )}
                </div>

                {/* COL 2: Open source */}
                <div className="flex flex-col items-start sm:items-center gap-3">
                  <span className="text-[10px] font-semibold uppercase tracking-widest text-theme-subtle/50">
                    {t('footer.open_source')}
                  </span>
                  <SourceRepositoryLink />
                  <p className="text-xs text-theme-subtle/60 text-left sm:text-center max-w-[16rem] leading-relaxed">
                    {t('footer.agpl_short')}
                  </p>
                </div>

                {/* COL 3: Powered by Project NEXUS */}
                <div className="flex flex-col items-start sm:items-end gap-2">
                  <span className="text-[10px] font-semibold uppercase tracking-widest text-theme-subtle/50">
                    {t('footer.powered_by')}
                  </span>
                  <a
                    href={PROJECT_NEXUS_URL}
                    target="_blank"
                    rel="noopener noreferrer"
                    title={t('footer.nexus_link_label')}
                    aria-label={t('footer.nexus_link_label')}
                    className="transition-opacity hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-primary rounded-lg"
                  >
                    <img
                      src="/images/project-nexus-open-source-community-platform.png"
                      alt={t('footer.nexus_link_label')}
                      className="h-32 w-auto object-contain"
                      width="1536"
                      height="1024"
                    />
                  </a>
                  <a
                    href={PROJECT_NEXUS_URL}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-xs text-theme-muted hover:text-theme-primary transition-colors"
                  >
                    {PROJECT_NEXUS_HOST} <span aria-hidden="true">↗</span>
                  </a>
                </div>

              </div>

              {/* Legal strip */}
              <div className="flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-[11px] text-theme-subtle/70">
                <span>{t('footer.agpl_notice', { year })}</span>
                <span aria-hidden="true">&middot;</span>
                <Link to={tenantPath('/platform/terms')} className="hover:text-theme-primary transition-colors">
                  {t('footer.terms')}
                </Link>
                <span aria-hidden="true">&middot;</span>
                <Link to={tenantPath('/platform/privacy')} className="hover:text-theme-primary transition-colors">
                  {t('footer.privacy')}
                </Link>
                <span
                  className="hidden"
                  aria-hidden="true"
                  data-build-commit={__BUILD_COMMIT__}
                  data-build-time={__BUILD_TIME__}
                />
              </div>
            </div>
          </div>
        )}
      </div>
      </div>
    </footer>
  );
}

/**
 * FooterLink - Subtle link styled for footer
 */
export interface FooterLinkProps {
  href: string;
  children: ReactNode;
}

export function FooterLink({ href, children }: FooterLinkProps) {
  return (
    <Link
      to={href}
      className="text-sm text-theme-muted hover:text-theme-primary transition-colors"
    >
      {children}
    </Link>
  );
}

export default Footer;
