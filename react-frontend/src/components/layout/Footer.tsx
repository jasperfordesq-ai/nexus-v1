// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Button } from '@heroui/react';
import { useTenant, useFeature, useCookieConsent } from '@/contexts';
import { Mail, Phone, MapPin, Cookie, Bug } from 'lucide-react';
import { TenantLogo } from '@/components/branding';

export interface FooterProps {
  /** Footer content/links */
  children?: ReactNode;
  /** Copyright text override */
  copyright?: string;
}

/**
 * Footer - Glass-styled footer component
 * Shows tenant branding, contact info, and footer_text from bootstrap API.
 * Hidden on mobile (md:block) — mobile uses MobileDrawer for nav links.
 */
export function Footer({ children, copyright }: FooterProps) {
  const { t } = useTranslation('common');
  const { tenant, branding, tenantPath } = useTenant();
  const hasEvents = useFeature('events');
  const hasBlog = useFeature('blog');
  const { resetConsent } = useCookieConsent();
  const year = new Date().getFullYear();

  // Use tenant's footer_text from config if set, otherwise build a default
  const footerText = tenant?.config?.footer_text?.trim()
    || copyright
    || `© ${year} ${branding.name}. All rights reserved.`;

  const contact = tenant?.contact;

  return (
    <footer className="hidden md:block relative z-10 border-t border-theme-default mt-auto glass-surface backdrop-blur-sm">
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
                  <li><FooterLink href={tenantPath('/members')}>{t('nav.members')}</FooterLink></li>
                  {hasEvents && <li><FooterLink href={tenantPath('/events')}>{t('nav.events')}</FooterLink></li>}
                  {hasBlog && <li><FooterLink href={tenantPath('/blog')}>{t('nav.blog')}</FooterLink></li>}
                </ul>
              </div>

              {/* Support */}
              <div>
                <h3 className="text-sm font-semibold text-theme-primary mb-3">{t('footer.support')}</h3>
                <ul className="space-y-2">
                  <li><FooterLink href={tenantPath('/help')}>{t('footer.help_center')}</FooterLink></li>
                  <li><FooterLink href={tenantPath('/kb')}>{t('nav.knowledge_base', 'Knowledge Base')}</FooterLink></li>
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
                aria-label="Cookie settings"
                startContent={<Cookie className="w-3 h-3" aria-hidden="true" />}
              >
                {t('footer.cookie_settings')}
              </Button>
            </div>

            {/* Platform Attribution */}
            <div className="pt-4 flex items-center justify-center gap-2 text-[11px] text-theme-subtle/60">
              <a
                href="https://github.com/jasperfordesq-ai/nexus-v1"
                target="_blank"
                rel="noopener noreferrer"
                className="hover:text-theme-primary transition-colors"
              >
                Project NEXUS
              </a>
              <span>&middot;</span>
              <span>AGPL-3.0 &copy; 2024&ndash;{year} Jasper Ford</span>
              <span>&middot;</span>
              <Link to={tenantPath('/platform/terms')} className="hover:text-theme-primary transition-colors">
                {t('footer.terms')}
              </Link>
              <span>&middot;</span>
              <Link to={tenantPath('/platform/privacy')} className="hover:text-theme-primary transition-colors">
                {t('footer.privacy')}
              </Link>
              <span className="font-mono text-[10px] text-theme-subtle/40" title={`Built ${__BUILD_TIME__}`}>
                {__BUILD_COMMIT__}
              </span>
            </div>
          </div>
        )}
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
