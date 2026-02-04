import { type ReactNode, useState } from 'react';
import { Header, HeaderNavLink } from './Header';
import { Footer } from './Footer';
import { MobileNav, MobileNavLink, MobileNavSection } from './MobileNav';
import { GlassButton } from '../ui';

export interface AppShellProps {
  /** Main page content */
  children: ReactNode;
  /** Show ambient glow background */
  ambientGlow?: 'default' | 'strong' | 'minimal' | 'none';
  /** Custom header content (replaces default) */
  header?: ReactNode;
  /** Custom footer content (replaces default) */
  footer?: ReactNode;
  /** Hide header */
  hideHeader?: boolean;
  /** Hide footer */
  hideFooter?: boolean;
}

/**
 * AppShell - Main application layout wrapper
 *
 * Provides consistent header, footer, mobile nav, and ambient glow
 */
export function AppShell({
  children,
  ambientGlow = 'default',
  header,
  footer,
  hideHeader = false,
  hideFooter = false,
}: AppShellProps) {
  const [mobileNavOpen, setMobileNavOpen] = useState(false);

  const ambientClass = {
    default: 'ambient-glow',
    strong: 'ambient-glow-strong',
    minimal: 'ambient-glow-minimal',
    none: '',
  }[ambientGlow];

  return (
    <div className="min-h-screen flex flex-col">
      {/* Ambient Background */}
      {ambientGlow !== 'none' && <div className={ambientClass} />}

      {/* Header */}
      {!hideHeader && (
        header || (
          <Header
            onMenuClick={() => setMobileNavOpen(true)}
            actions={
              <>
                <GlassButton variant="ghost" size="sm">
                  Sign In
                </GlassButton>
                <GlassButton variant="primary" size="sm" className="hidden sm:flex">
                  Get Started
                </GlassButton>
              </>
            }
          >
            <HeaderNavLink href="/" active>Home</HeaderNavLink>
            <HeaderNavLink href="/listings">Listings</HeaderNavLink>
            <HeaderNavLink href="/events">Events</HeaderNavLink>
            <HeaderNavLink href="/groups">Groups</HeaderNavLink>
          </Header>
        )
      )}

      {/* Mobile Navigation */}
      <MobileNav isOpen={mobileNavOpen} onClose={() => setMobileNavOpen(false)}>
        <MobileNavSection>
          <MobileNavLink href="/" active onClick={() => setMobileNavOpen(false)}>
            Home
          </MobileNavLink>
          <MobileNavLink href="/listings" onClick={() => setMobileNavOpen(false)}>
            Listings
          </MobileNavLink>
          <MobileNavLink href="/events" onClick={() => setMobileNavOpen(false)}>
            Events
          </MobileNavLink>
          <MobileNavLink href="/groups" onClick={() => setMobileNavOpen(false)}>
            Groups
          </MobileNavLink>
        </MobileNavSection>
        <MobileNavSection title="Account">
          <MobileNavLink href="/login" onClick={() => setMobileNavOpen(false)}>
            Sign In
          </MobileNavLink>
          <MobileNavLink href="/register" onClick={() => setMobileNavOpen(false)}>
            Get Started
          </MobileNavLink>
        </MobileNavSection>
      </MobileNav>

      {/* Main Content */}
      <main className={`flex-1 ${!hideHeader ? 'pt-16' : ''}`}>
        {children}
      </main>

      {/* Footer */}
      {!hideFooter && (footer || <Footer />)}
    </div>
  );
}

export default AppShell;
