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
 *
 * Subtle glass surface with top border
 */
export function Footer({ children, copyright }: FooterProps) {
  const { branding } = useTenant();
  const year = new Date().getFullYear();
  const defaultCopyright = `© ${year} ${branding.name}. All rights reserved.`;

  return (
    <footer className="relative z-10 border-t border-white/10 mt-auto bg-black/20 backdrop-blur-sm">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {children ? (
          children
        ) : (
          <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div className="flex items-center gap-2">
              <span className="text-xl font-bold text-gradient">{branding.name}</span>
              <span className="text-white/30">|</span>
              <span className="text-white/50 text-sm">{branding.tagline || 'Time Banking Platform'}</span>
            </div>
            <div className="flex items-center gap-6">
              <FooterLink href="/privacy">Privacy</FooterLink>
              <FooterLink href="/terms">Terms</FooterLink>
              <FooterLink href="/contact">Contact</FooterLink>
            </div>
            <p className="text-sm text-white/30">
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
      className="text-sm text-white/50 hover:text-white transition-colors"
    >
      {children}
    </Link>
  );
}

export default Footer;
