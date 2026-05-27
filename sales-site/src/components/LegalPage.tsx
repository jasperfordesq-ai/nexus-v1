// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button, Card } from '@heroui/react';
import { ArrowRight, BookOpenText, Building2, FileText, GitBranch, Scale, ShieldCheck } from 'lucide-react';
import { type MouseEvent } from 'react';

import { findLegalPage, legalPages, type LegalPath, type LegalPageContent } from '../data/legal';

interface LegalPageProps {
  path: LegalPath;
  onNavigate: (href: string) => void;
}

const pageIcons: Record<LegalPath, typeof Scale> = {
  '/legal/terms': Scale,
  '/legal/privacy': ShieldCheck,
  '/legal/cookies': BookOpenText,
  '/legal/acceptable-use': FileText,
  '/legal/data-processing': Building2,
};

export default function LegalPage({ path, onNavigate }: LegalPageProps) {
  const page = findLegalPage(path);
  const Icon = pageIcons[page.path];
  const handleInternalLink = (event: MouseEvent<HTMLAnchorElement>, href: string) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.altKey || event.ctrlKey || event.shiftKey) {
      return;
    }

    event.preventDefault();
    onNavigate(href);
  };

  return (
    <article>
      <section className="border-b border-white/10 bg-white/[0.025]">
        <div className="mx-auto grid max-w-7xl gap-8 px-5 py-14 lg:grid-cols-[1fr_22rem] lg:py-20">
          <div>
            <p className="mb-5 flex w-fit items-center gap-2 rounded-full border border-[color:var(--color-accent)]/30 bg-[color:var(--color-accent)]/10 px-4 py-2 text-xs font-black tracking-[0.16em] text-[var(--color-accent)] uppercase">
              <Icon className="size-4" />
              {page.eyebrow}
            </p>
            <h1 className="max-w-4xl text-4xl font-black leading-[1.08] tracking-normal text-white md:text-6xl">{page.title}</h1>
            <p className="mt-6 max-w-3xl text-lg leading-8 text-white/68">{page.summary}</p>
            <div className="mt-7 flex flex-wrap gap-3">
              <Button onPress={() => onNavigate('/hosting')}>
                Hosting pricing
                <ArrowRight className="size-4" />
              </Button>
              <Button variant="outline" onPress={() => window.open('https://github.com/jasperfordesq-ai/nexus-v1', '_blank', 'noopener,noreferrer')}>
                <GitBranch className="size-4" />
                Source code
              </Button>
            </div>
          </div>

          <Card className="h-fit border border-white/10 bg-white/[0.055] p-5">
            <p className="text-sm font-black tracking-[0.16em] text-white/45 uppercase">Legal identity map</p>
            <div className="mt-5 grid gap-4">
              <IdentityRow label="Software" value="Project NEXUS open-source code" />
              <IdentityRow label="Creator" value="Jasper Ford" />
              <IdentityRow label="Licence" value="AGPL-3.0-or-later" />
              <IdentityRow label="Hosting" value="PROJECT NEXUS PLATFORM IRELAND LTD" />
              <IdentityRow label="Reg. number" value="812763" />
              <IdentityRow label="Updated" value={page.lastUpdated} />
            </div>
          </Card>
        </div>
      </section>

      <section className="border-b border-white/10">
        <div className="mx-auto max-w-7xl px-5 py-10">
          <div className="grid gap-4 md:grid-cols-2">
            {page.callouts.map((callout) => (
              <Card key={callout.title} className="border border-white/10 bg-white/[0.055] p-5">
                <h2 className="text-xl font-black text-white">{callout.title}</h2>
                <p className="mt-3 text-sm leading-7 text-white/62">{callout.body}</p>
              </Card>
            ))}
          </div>
        </div>
      </section>

      <section>
        <div className="mx-auto grid max-w-7xl gap-8 px-5 py-12 lg:grid-cols-[16rem_1fr]">
          <aside className="h-fit lg:sticky lg:top-24">
            <p className="text-xs font-black tracking-[0.16em] text-white/45 uppercase">Legal pages</p>
            <nav className="mt-4 grid gap-2" aria-label="Legal navigation">
              {legalPages.map((item) => (
                <a
                  key={item.path}
                  href={item.path}
                  className={`rounded-lg px-3 py-2 text-left text-sm font-bold transition ${
                    item.path === page.path
                      ? 'border border-[color:var(--color-accent)]/45 bg-[color:var(--color-accent)]/14 text-white shadow-[inset_3px_0_0_var(--color-accent)]'
                      : 'border border-transparent text-white/62 hover:bg-white/8 hover:text-white'
                  }`}
                  onClick={(event) => handleInternalLink(event, item.path)}
                >
                  {item.label}
                </a>
              ))}
            </nav>
          </aside>

          <div className="grid gap-5">
            {page.tables?.map((table) => (
              <LegalTableBlock key={table.title} page={page} table={table} />
            ))}
            {page.sections.map((section) => (
              <section key={section.title} className="rounded-2xl border border-white/10 bg-white/[0.04] p-5 md:p-6">
                <h2 className="text-2xl font-black text-white">{section.title}</h2>
                {section.intro ? <p className="mt-3 text-sm leading-7 text-white/62">{section.intro}</p> : null}
                <ul className="mt-4 grid gap-3">
                  {section.items.map((item) => (
                    <li key={item} className="flex gap-3 text-sm leading-7 text-white/68">
                      <span className="mt-2 size-1.5 shrink-0 rounded-full bg-[var(--color-accent)]" />
                      <span>{item}</span>
                    </li>
                  ))}
                </ul>
              </section>
            ))}
          </div>
        </div>
      </section>
    </article>
  );
}

function IdentityRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="grid grid-cols-[7.5rem_1fr] gap-3 text-sm">
      <span className="font-black text-white/38 uppercase">{label}</span>
      <span className="font-semibold text-white/76">{value}</span>
    </div>
  );
}

function LegalTableBlock({ table }: { page: LegalPageContent; table: NonNullable<LegalPageContent['tables']>[number] }) {
  return (
    <section className="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.04]">
      <div className="border-b border-white/10 p-5 md:p-6">
        <h2 className="text-2xl font-black text-white">{table.title}</h2>
      </div>
      <div className="overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead className="bg-black/25 text-xs font-black tracking-[0.12em] text-white/45 uppercase">
            <tr>
              {table.columns.map((column) => (
                <th key={column} className="px-5 py-4">
                  {column}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {table.rows.map((row) => (
              <tr key={row.join('|')} className="border-t border-white/10">
                {row.map((cell) => (
                  <td key={cell} className="px-5 py-4 leading-7 text-white/68 align-top">
                    {cell}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}
