// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button } from '@heroui/react';
import { GitBranch, Menu, X } from 'lucide-react';
import { type MouseEvent, type ReactNode, useState } from 'react';

import { legalPages } from '../data/legal';
import { salesNavItems } from '../lib/routes';

interface SiteShellProps {
  children: ReactNode;
  currentPath: string;
  onNavigate: (href: string) => void;
}

export default function SiteShell({ children, currentPath, onNavigate }: SiteShellProps) {
  const [isOpen, setIsOpen] = useState(false);

  const handleInternalNav = (href: string) => {
    setIsOpen(false);
    onNavigate(href);
  };

  const handleInternalLink = (event: MouseEvent<HTMLAnchorElement>, href: string) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.altKey || event.ctrlKey || event.shiftKey) {
      return;
    }

    event.preventDefault();
    handleInternalNav(href);
  };

  return (
    <div className="min-h-screen text-[var(--nexus-ink)]">
      <header className="sticky top-0 z-50 border-b border-white/10 bg-[var(--surface-base)]/90 backdrop-blur-xl">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-5 py-3">
          <a
            href="/"
            className="flex items-center gap-3 text-left"
            onClick={(event) => handleInternalLink(event, '/')}
            aria-label="Go to Project NEXUS home"
          >
            <span className="grid size-10 place-items-center rounded-xl border border-white/15 bg-white/6">
              <img src="/favicon.svg" alt="" className="size-6" />
            </span>
            <span>
              <span className="block text-sm font-black tracking-[0.18em] text-white uppercase">Project NEXUS</span>
              <span className="block text-xs text-[var(--nexus-muted)]">Open-source community infrastructure</span>
            </span>
          </a>

          <nav className="hidden items-center gap-1 lg:flex" aria-label="Main navigation">
            {salesNavItems.map((item) => {
              const isExternal = item.href.startsWith('http');
              const isActive = item.href === currentPath;

              if (isExternal) {
                return (
                  <a
                    key={item.href}
                    href={item.href}
                    target={item.label === 'GitHub' ? '_blank' : undefined}
                    rel={item.label === 'GitHub' ? 'noopener noreferrer' : undefined}
                    className="rounded-full px-4 py-2 text-sm font-semibold text-white/72 transition hover:bg-white/8 hover:text-white"
                  >
                    {item.label}
                  </a>
                );
              }

              return (
                <a
                  key={item.href}
                  href={item.href}
                  className={`rounded-full px-4 py-2 text-sm font-semibold transition ${
                    isActive ? 'bg-white text-[var(--text-inverse)]' : 'text-white/72 hover:bg-white/8 hover:text-white'
                  }`}
                  onClick={(event) => handleInternalLink(event, item.href)}
                >
                  {item.label}
                </a>
              );
            })}
          </nav>

          <div className="hidden items-center gap-3 lg:flex">
            <Button
              size="sm"
              variant="outline"
              onPress={() => window.open('https://github.com/jasperfordesq-ai/nexus-v1', '_blank', 'noopener,noreferrer')}
            >
              <GitBranch className="size-4" />
              Source
            </Button>
            <Button size="sm" onPress={() => handleInternalNav('/hosting')}>
              Pricing
            </Button>
          </div>

          <button
            type="button"
            className="grid size-10 place-items-center rounded-xl border border-white/15 bg-white/6 lg:hidden"
            onClick={() => setIsOpen((value) => !value)}
            aria-label="Toggle navigation"
            aria-expanded={isOpen}
          >
            {isOpen ? <X className="size-5" /> : <Menu className="size-5" />}
          </button>
        </div>

        {isOpen ? (
          <div className="border-t border-white/10 bg-[#090b11] px-5 py-4 lg:hidden">
            <nav className="flex flex-col gap-2" aria-label="Mobile navigation">
              {salesNavItems.map((item) =>
                item.href.startsWith('http') ? (
                  <a key={item.href} href={item.href} className="rounded-xl px-3 py-3 font-semibold text-white/78">
                    {item.label}
                  </a>
                ) : (
                  <a
                    key={item.href}
                    className="rounded-xl px-3 py-3 text-left font-semibold text-white/78"
                    href={item.href}
                    onClick={(event) => handleInternalLink(event, item.href)}
                  >
                    {item.label}
                  </a>
                ),
              )}
            </nav>
          </div>
        ) : null}
      </header>

      <main>{children}</main>

      <footer className="border-t border-white/10 bg-[var(--surface-base)]">
        <div className="mx-auto grid max-w-7xl gap-10 px-5 py-12 lg:grid-cols-[1.35fr_1fr_1fr_1fr_1fr]">
          <div>
            <div className="mb-4 flex items-center gap-3">
              <img src="/favicon.svg" alt="" className="size-8" />
              <span className="font-black tracking-[0.16em] uppercase">Project NEXUS</span>
            </div>
            <p className="max-w-md text-sm leading-7 text-white/62">
              Community timebanking from EUR29/month, plus full managed platform hosting for volunteering, civic participation, federation, and multi-community networks.
            </p>
            <p className="mt-4 max-w-md text-xs leading-6 text-white/52">
              Managed hosting in association with PROJECT NEXUS PLATFORM IRELAND LTD, hosting partner. Reg. Number 812763.
            </p>
            <p className="mt-4 text-xs text-white/45">
              Copyright © 2024-2026 Jasper Ford. Licensed under AGPL-3.0-or-later.
            </p>
          </div>
          <FooterColumn
            title="Compare"
            links={[
              ['Features', '/features'],
              ['Pricing and order workbench', '/hosting'],
              ['Live demo', 'https://hour-timebank.ie'],
              ['Accessible frontend', 'https://accessible.project-nexus.ie'],
            ]}
            onNavigate={handleInternalNav}
          />
          <FooterColumn
            title="Open Source"
            links={[
              ['Repository', 'https://github.com/jasperfordesq-ai/nexus-v1'],
              ['NOTICE', 'https://github.com/jasperfordesq-ai/nexus-v1/blob/main/NOTICE'],
              ['AGPL-3.0', 'https://www.gnu.org/licenses/agpl-3.0.html'],
            ]}
            onNavigate={handleInternalNav}
          />
          <FooterColumn title="Legal" links={legalPages.map((page): [string, string] => [page.label, page.path])} nativeInternalLinks onNavigate={handleInternalNav} />
          <FooterColumn
            title="Contact"
            links={[
              ['Hosting enquiry', 'mailto:jasper@hour-timebank.ie'],
              ['hOUR Timebank', 'https://hour-timebank.ie'],
              ['Timebank Global', 'https://timebank.global'],
            ]}
            onNavigate={handleInternalNav}
          />
        </div>
      </footer>
    </div>
  );
}

function FooterColumn({
  title,
  links,
  nativeInternalLinks = false,
  onNavigate,
}: {
  title: string;
  links: [string, string][];
  nativeInternalLinks?: boolean;
  onNavigate: (href: string) => void;
}) {
  const handleInternalLink = (event: MouseEvent<HTMLAnchorElement>, href: string) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.altKey || event.ctrlKey || event.shiftKey) {
      return;
    }

    event.preventDefault();
    onNavigate(href);
  };

  return (
    <div>
      <h2 className="mb-4 text-sm font-bold tracking-[0.16em] text-white/58 uppercase">{title}</h2>
      <div className="flex flex-col gap-3 text-sm text-white/70">
        {links.map(([label, href]) =>
          href.startsWith('/') ? (
            <a
              key={href}
              href={href}
              className="text-left hover:text-white"
              onClick={nativeInternalLinks ? undefined : (event) => handleInternalLink(event, href)}
            >
              {label}
            </a>
          ) : (
            <a key={href} href={href} className="hover:text-white">
              {label}
            </a>
          ),
        )}
      </div>
    </div>
  );
}
