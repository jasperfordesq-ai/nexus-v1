import { type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { useTenant, useFeature } from '@/contexts';
import { Hexagon } from 'lucide-react';

export interface FooterProps {
  /** Footer content/links */
  children?: ReactNode;
  /** Copyright text */
  copyright?: string;
}

/**
 * Footer - Glass-styled footer component
 * Theme-aware styling for light and dark modes
 */
export function Footer({ children, copyright }: FooterProps) {
  const { branding, tenantPath } = useTenant();
  const hasEvents = useFeature('events');
  const hasBlog = useFeature('blog');
  const year = new Date().getFullYear();
  const defaultCopyright = `© ${year} ${branding.name}. All rights reserved.`;

  return (
    <footer className="hidden md:block relative z-10 border-t border-theme-default mt-auto glass-surface backdrop-blur-sm">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {children ? (
          children
        ) : (
          <div className="space-y-8">
            {/* Footer Links Grid */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-8">
              {/* Brand */}
              <div className="col-span-2 sm:col-span-1">
                <Link to={tenantPath('/')} className="flex items-center gap-2 mb-3">
                  <Hexagon className="w-6 h-6 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
                  <span className="font-bold text-lg text-gradient">{branding.name}</span>
                </Link>
                <p className="text-sm text-theme-subtle">
                  {branding.tagline || 'Building stronger communities through the exchange of time.'}
                </p>
              </div>

              {/* Platform */}
              <div>
                <h3 className="text-sm font-semibold text-theme-primary mb-3">Platform</h3>
                <ul className="space-y-2">
                  <li><FooterLink href={tenantPath('/listings')}>Listings</FooterLink></li>
                  <li><FooterLink href={tenantPath('/members')}>Members</FooterLink></li>
                  {hasEvents && <li><FooterLink href={tenantPath('/events')}>Events</FooterLink></li>}
                  {hasBlog && <li><FooterLink href={tenantPath('/blog')}>Blog</FooterLink></li>}
                </ul>
              </div>

              {/* Support */}
              <div>
                <h3 className="text-sm font-semibold text-theme-primary mb-3">Support</h3>
                <ul className="space-y-2">
                  <li><FooterLink href={tenantPath('/help')}>Help Center</FooterLink></li>
                  <li><FooterLink href={tenantPath('/contact')}>Contact Us</FooterLink></li>
                  <li><FooterLink href={tenantPath('/about')}>About</FooterLink></li>
                </ul>
              </div>

              {/* Legal */}
              <div>
                <h3 className="text-sm font-semibold text-theme-primary mb-3">Legal</h3>
                <ul className="space-y-2">
                  <li><FooterLink href={tenantPath('/legal')}>Legal Hub</FooterLink></li>
                  <li><FooterLink href={tenantPath('/terms')}>Terms of Service</FooterLink></li>
                  <li><FooterLink href={tenantPath('/privacy')}>Privacy Policy</FooterLink></li>
                  <li><FooterLink href={tenantPath('/accessibility')}>Accessibility</FooterLink></li>
                </ul>
              </div>
            </div>

            {/* Bottom Bar */}
            <div className="border-t border-theme-default pt-6 flex flex-col sm:flex-row items-center justify-between gap-3">
              <p className="text-sm text-theme-subtle">
                {copyright || defaultCopyright}
              </p>
              <p className="text-xs text-theme-subtle">
                Powered by Project NEXUS
              </p>
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
