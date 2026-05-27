// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button } from '@heroui/react';
import { GitBranch, Menu, X } from 'lucide-react';
import { type ReactNode, useState } from 'react';

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

  return (
    <div className="min-h-screen text-[var(--nexus-ink)]">
      <header className="sticky top-0 z-50 border-b border-white/10 bg-[#07080d]/88 backdrop-blur-xl">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-5 py-3">
          <button
            type="button"
            className="flex items-center gap-3 text-left"
            onClick={() => handleInternalNav('/')}
            aria-label="Go to Project NEXUS home"
          >
            <span className="grid size-10 place-items-center rounded-xl border border-white/15 bg-white/6">
              <img src="/favicon.svg" alt="" className="size-6" />
            </span>
            <span>
              <span className="block text-sm font-black tracking-[0.18em] text-white uppercase">Project NEXUS</span>
              <span className="block text-xs text-[var(--nexus-muted)]">Open-source community infrastructure</span>
            </span>
          </button>

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
                <button
                  key={item.href}
                  type="button"
                  className={`rounded-full px-4 py-2 text-sm font-semibold transition ${
                    isActive ? 'bg-white text-[#0b0d14]' : 'text-white/72 hover:bg-white/8 hover:text-white'
                  }`}
                  onClick={() => handleInternalNav(item.href)}
                >
                  {item.label}
                </button>
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
              Compare Hosting
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
                  <button
                    key={item.href}
                    type="button"
                    className="rounded-xl px-3 py-3 text-left font-semibold text-white/78"
                    onClick={() => handleInternalNav(item.href)}
                  >
                    {item.label}
                  </button>
                ),
              )}
            </nav>
          </div>
        ) : null}
      </header>

      <main>{children}</main>

      <footer className="border-t border-white/10 bg-[#07080d]">
        <div className="mx-auto grid max-w-7xl gap-10 px-5 py-12 lg:grid-cols-[1.5fr_1fr_1fr_1fr]">
          <div>
            <div className="mb-4 flex items-center gap-3">
              <img src="/favicon.svg" alt="" className="size-8" />
              <span className="font-black tracking-[0.16em] uppercase">Project NEXUS</span>
            </div>
            <p className="max-w-md text-sm leading-7 text-white/62">
              Enterprise community platform hosting for timebanking, volunteering, civic participation, and multi-community networks.
            </p>
            <p className="mt-4 text-xs text-white/45">
              Copyright © 2024-2026 Jasper Ford. Licensed under AGPL-3.0-or-later.
            </p>
          </div>
          <FooterColumn
            title="Compare"
            links={[
              ['Features', '/features'],
              ['Hosting calculator', '/hosting'],
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
  onNavigate,
}: {
  title: string;
  links: [string, string][];
  onNavigate: (href: string) => void;
}) {
  return (
    <div>
      <h2 className="mb-4 text-sm font-bold tracking-[0.16em] text-white/58 uppercase">{title}</h2>
      <div className="flex flex-col gap-3 text-sm text-white/70">
        {links.map(([label, href]) =>
          href.startsWith('/') ? (
            <button key={href} type="button" className="text-left hover:text-white" onClick={() => onNavigate(href)}>
              {label}
            </button>
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
