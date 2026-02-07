import { type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { useTenant } from '@/contexts';

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
  const { branding } = useTenant();
  const year = new Date().getFullYear();
  const defaultCopyright = `© ${year} ${branding.name}. All rights reserved.`;

  return (
    <footer className="relative z-10 border-t border-theme-default mt-auto glass-surface backdrop-blur-sm">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {children ? (
          children
        ) : (
          <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div className="flex items-center gap-2">
              <span className="text-xl font-bold text-gradient">{branding.name}</span>
              <span className="text-theme-subtle">|</span>
              <span className="text-theme-muted text-sm">{branding.tagline || 'Time Banking Platform'}</span>
            </div>
            <p className="text-sm text-theme-subtle">
              {copyright || defaultCopyright}
            </p>
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
