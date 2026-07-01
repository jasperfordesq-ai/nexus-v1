// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BrokerControlsHelp — collapsible guidance panel for the Broker Controls.
 *
 * Used in two places:
 *   1. Embedded at the bottom of BrokerDashboardPage (no page-title side effect)
 *   2. As the standalone /broker/help route via the BrokerHelpPage default export
 *
 * The presentational `BrokerControlsHelp` component does NOT call usePageTitle —
 * if it did, embedding it on the dashboard would clobber the dashboard's title.
 * The standalone wrapper `BrokerHelpPage` owns the title for the /broker/help
 * route and adds a searchable, on-brand help-center frame around the same
 * section content (client-side filter over the translated section text).
 *
 * All section content is data-driven from HELP_SECTIONS so the embedded panel,
 * the standalone page, and the search index can never drift apart.
 */

import { useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Trans, useTranslation } from 'react-i18next';
import {
  Card,
  CardBody,
  CardHeader,
  Accordion,
  AccordionItem,
  Button,
  Input,
  Separator,
} from '@/components/ui';
import { usePageTitle } from '@/hooks';
import type { LucideIcon } from 'lucide-react';
import BookOpen from 'lucide-react/icons/book-open';
import Workflow from 'lucide-react/icons/workflow';
import MessageSquareWarning from 'lucide-react/icons/message-square-warning';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import Eye from 'lucide-react/icons/eye';
import ShieldCheck from 'lucide-react/icons/shield-check';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import Scale from 'lucide-react/icons/scale';
import Phone from 'lucide-react/icons/phone';
import Database from 'lucide-react/icons/database';
import Search from 'lucide-react/icons/search';
import SearchX from 'lucide-react/icons/search-x';
import { BrokerPageShell, BrokerEmptyState, type BrokerStatColor } from '../components';

const richComponents = {
  b: <strong />,
  i: <em />,
  code: <code />,
};

// ─────────────────────────────────────────────────────────────────────────────
// Section content model — every block references the existing broker.json
// help.* keys, so restructuring the presentation never touches the copy.
// ─────────────────────────────────────────────────────────────────────────────

type HelpBlock =
  | { type: 'p'; key: string; rich?: boolean; italic?: boolean }
  | { type: 'heading'; key: string }
  | { type: 'list'; ordered?: boolean; items: ReadonlyArray<{ key: string; rich?: boolean }> };

interface HelpSectionDef {
  key: string;
  icon: LucideIcon;
  tone: BrokerStatColor;
  blocks: ReadonlyArray<HelpBlock>;
}

const HELP_SECTIONS: ReadonlyArray<HelpSectionDef> = [
  {
    key: 'overview',
    icon: BookOpen,
    tone: 'accent',
    blocks: [
      { type: 'p', key: 'help.overview.intro' },
      {
        type: 'list',
        items: [
          { key: 'help.overview.bullet_exchange', rich: true },
          { key: 'help.overview.bullet_risk_tags', rich: true },
          { key: 'help.overview.bullet_message_review', rich: true },
          { key: 'help.overview.bullet_user_monitoring', rich: true },
          { key: 'help.overview.bullet_vetting', rich: true },
          { key: 'help.overview.bullet_configuration', rich: true },
        ],
      },
      { type: 'p', key: 'help.overview.access_note', rich: true },
    ],
  },
  {
    key: 'workflow',
    icon: Workflow,
    tone: 'accent',
    blocks: [
      { type: 'p', key: 'help.workflow.intro' },
      {
        type: 'list',
        ordered: true,
        items: [
          { key: 'help.workflow.step_unreviewed', rich: true },
          { key: 'help.workflow.step_pending', rich: true },
          { key: 'help.workflow.step_alerts', rich: true },
          { key: 'help.workflow.step_vetting', rich: true },
          { key: 'help.workflow.step_activity', rich: true },
        ],
      },
      { type: 'p', key: 'help.workflow.tip', italic: true },
    ],
  },
  {
    key: 'messages',
    icon: MessageSquareWarning,
    tone: 'warning',
    blocks: [
      { type: 'p', key: 'help.messages.intro' },
      { type: 'heading', key: 'help.messages.severity_heading' },
      {
        type: 'list',
        items: [
          { key: 'help.messages.severity_high', rich: true },
          { key: 'help.messages.severity_medium', rich: true },
          { key: 'help.messages.severity_low', rich: true },
        ],
      },
      { type: 'heading', key: 'help.messages.action_heading' },
      {
        type: 'list',
        ordered: true,
        items: [
          { key: 'help.messages.action_open' },
          { key: 'help.messages.action_read' },
          { key: 'help.messages.action_mark', rich: true },
          { key: 'help.messages.action_escalate' },
        ],
      },
      { type: 'p', key: 'help.messages.retention' },
    ],
  },
  {
    key: 'monitoring',
    icon: Eye,
    tone: 'accent',
    blocks: [
      { type: 'p', key: 'help.monitoring.intro' },
      {
        type: 'list',
        items: [
          { key: 'help.monitoring.automatic', rich: true },
          { key: 'help.monitoring.manual', rich: true },
        ],
      },
      { type: 'p', key: 'help.monitoring.expiry', rich: true },
      { type: 'p', key: 'help.monitoring.risk_tags', rich: true },
    ],
  },
  {
    key: 'vetting',
    icon: ShieldCheck,
    tone: 'success',
    blocks: [
      { type: 'p', key: 'help.vetting.intro' },
      {
        type: 'list',
        items: [
          { key: 'help.vetting.type_garda', rich: true },
          { key: 'help.vetting.type_dbs', rich: true },
          { key: 'help.vetting.type_pvg', rich: true },
          { key: 'help.vetting.type_access_ni', rich: true },
          { key: 'help.vetting.type_international', rich: true },
          { key: 'help.vetting.type_other', rich: true },
        ],
      },
      { type: 'heading', key: 'help.vetting.lifecycle_heading' },
      {
        type: 'list',
        ordered: true,
        items: [
          { key: 'help.vetting.lifecycle_pending', rich: true },
          { key: 'help.vetting.lifecycle_verified', rich: true },
          { key: 'help.vetting.lifecycle_reminder', rich: true },
          { key: 'help.vetting.lifecycle_expired', rich: true },
          { key: 'help.vetting.lifecycle_rejected', rich: true },
        ],
      },
      { type: 'p', key: 'help.vetting.match_note', rich: true, italic: true },
    ],
  },
  {
    key: 'alerts',
    icon: AlertTriangle,
    tone: 'danger',
    blocks: [
      { type: 'p', key: 'help.alerts.intro' },
      {
        type: 'list',
        items: [{ key: 'help.alerts.counts_messages' }, { key: 'help.alerts.counts_incidents' }],
      },
      { type: 'heading', key: 'help.alerts.escalate_heading' },
      {
        type: 'list',
        items: [
          { key: 'help.alerts.escalate_abuse', rich: true },
          { key: 'help.alerts.escalate_child', rich: true },
          { key: 'help.alerts.escalate_vetting', rich: true },
        ],
      },
    ],
  },
  {
    key: 'legal',
    icon: Scale,
    tone: 'neutral',
    blocks: [
      { type: 'p', key: 'help.legal.disclaimer' },
      {
        type: 'list',
        items: [
          { key: 'help.legal.law_nvb', rich: true },
          { key: 'help.legal.law_children_first', rich: true },
          { key: 'help.legal.law_svga', rich: true },
          { key: 'help.legal.law_pvg', rich: true },
          { key: 'help.legal.law_svgni', rich: true },
          { key: 'help.legal.law_gdpr', rich: true },
        ],
      },
    ],
  },
  {
    key: 'data',
    icon: Database,
    tone: 'neutral',
    blocks: [
      {
        type: 'list',
        items: [
          { key: 'help.data.monitoring_status', rich: true },
          { key: 'help.data.message_copies', rich: true },
          { key: 'help.data.vetting_records', rich: true },
          { key: 'help.data.user_prefs', rich: true },
          { key: 'help.data.guardian_assignments', rich: true },
          { key: 'help.data.audit_trail', rich: true },
        ],
      },
    ],
  },
  {
    key: 'contacts',
    icon: Phone,
    tone: 'accent',
    blocks: [
      {
        type: 'list',
        items: [
          { key: 'help.contacts.technical', rich: true },
          { key: 'help.contacts.safeguarding', rich: true },
          { key: 'help.contacts.criminality', rich: true },
          { key: 'help.contacts.policy', rich: true },
        ],
      },
    ],
  },
  {
    key: 'troubleshooting',
    icon: ShieldAlert,
    tone: 'warning',
    blocks: [
      {
        type: 'list',
        items: [
          { key: 'help.troubleshooting.cant_message', rich: true },
          { key: 'help.troubleshooting.wrong_member', rich: true },
          { key: 'help.troubleshooting.vetting_stuck', rich: true },
          { key: 'help.troubleshooting.no_copies', rich: true },
        ],
      },
    ],
  },
];

/** i18n keys whose translated text makes up a section's search haystack. */
function sectionSearchKeys(section: HelpSectionDef): string[] {
  const keys = [`help.${section.key}.title`, `help.${section.key}.aria`];
  for (const block of section.blocks) {
    if (block.type === 'list') {
      keys.push(...block.items.map((item) => item.key));
    } else {
      keys.push(block.key);
    }
  }
  return keys;
}

// Tailwind JIT needs full class names at build time — no dynamic `bg-${tone}/10`.
const toneTileClass: Record<BrokerStatColor, string> = {
  accent: 'text-accent bg-accent/10',
  success: 'text-success bg-success/10',
  warning: 'text-warning bg-warning/10',
  danger: 'text-danger bg-danger/10',
  neutral: 'text-muted bg-surface-tertiary',
};

// ─────────────────────────────────────────────────────────────────────────────
// Renderers shared by the embedded panel and the standalone page
// ─────────────────────────────────────────────────────────────────────────────

function HelpBlocks({ blocks }: { blocks: ReadonlyArray<HelpBlock> }) {
  const { t } = useTranslation('broker');

  return (
    <div className="space-y-3 text-sm leading-relaxed text-muted">
      {blocks.map((block) => {
        if (block.type === 'heading') {
          return (
            <p key={block.key} className="font-medium text-foreground">
              {t(block.key)}
            </p>
          );
        }
        if (block.type === 'p') {
          return (
            <p key={block.key} className={block.italic ? 'italic text-muted' : undefined}>
              {block.rich ? <Trans t={t} i18nKey={block.key} components={richComponents} /> : t(block.key)}
            </p>
          );
        }
        const ListTag = block.ordered ? 'ol' : 'ul';
        return (
          <ListTag
            key={block.items.map((item) => item.key).join('|')}
            className={`${block.ordered ? 'list-decimal' : 'list-disc'} space-y-1.5 pl-5`}
          >
            {block.items.map((item) => (
              <li key={item.key}>
                {item.rich ? <Trans t={t} i18nKey={item.key} components={richComponents} /> : t(item.key)}
              </li>
            ))}
          </ListTag>
        );
      })}
    </div>
  );
}

function HelpAccordion({ sections }: { sections: ReadonlyArray<HelpSectionDef> }) {
  const { t } = useTranslation('broker');

  return (
    <Accordion variant="splitted" selectionMode="multiple">
      {sections.map((section) => {
        const Icon = section.icon;
        return (
          <AccordionItem
            key={section.key}
            id={section.key}
            aria-label={t(`help.${section.key}.aria`)}
            startContent={
              <span
                aria-hidden="true"
                className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset ring-current/10 ${toneTileClass[section.tone]}`}
              >
                <Icon size={15} />
              </span>
            }
            title={<span className="font-medium text-foreground">{t(`help.${section.key}.title`)}</span>}
          >
            <HelpBlocks blocks={section.blocks} />
          </AccordionItem>
        );
      })}
    </Accordion>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Embedded guidance panel (dashboard) — export name and collapsible behaviour
// are load-bearing: BrokerDashboardPage imports { BrokerControlsHelp }.
// ─────────────────────────────────────────────────────────────────────────────

export function BrokerControlsHelp() {
  const { t } = useTranslation('broker');

  return (
    <section className="mt-10">
      <Card className="rounded-2xl border border-divider/70 bg-surface shadow-sm shadow-black/[0.03]">
        <CardHeader className="flex items-center gap-3 pb-2">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-accent/10 text-accent ring-1 ring-inset ring-current/10">
            <BookOpen size={20} aria-hidden="true" />
          </div>
          <div className="min-w-0">
            <h2 className="text-lg font-semibold tracking-tight text-foreground">{t('help.title')}</h2>
            <p className="text-xs text-muted">{t('help.subtitle')}</p>
          </div>
        </CardHeader>
        <Separator />
        <CardBody className="pt-4">
          <HelpAccordion sections={HELP_SECTIONS} />
        </CardBody>
      </Card>
    </section>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Standalone /broker/help route — searchable help center
// ─────────────────────────────────────────────────────────────────────────────

export default function BrokerHelpPage() {
  const { t } = useTranslation('broker');
  usePageTitle(t('help.page_title'));

  // Deep-linkable search (?q=…) so a filtered help view can be shared/bookmarked.
  const [searchParams, setSearchParams] = useSearchParams();
  const query = searchParams.get('q') ?? '';
  const setQuery = (next: string) => {
    setSearchParams(next ? { q: next } : {}, { replace: true });
  };

  // Full-text haystack per section, built from the same i18n keys the panel
  // renders — the search can never drift from the visible copy. Tags from
  // rich strings (<b>/<code>…) are stripped before matching.
  const haystacks = useMemo(() => {
    const map = new Map<string, string>();
    for (const section of HELP_SECTIONS) {
      const text = sectionSearchKeys(section)
        .map((key) => t(key))
        .join(' ')
        .replace(/<[^>]+>/g, ' ')
        .toLowerCase();
      map.set(section.key, text);
    }
    return map;
  }, [t]);

  const normalized = query.trim().toLowerCase();
  const visibleSections = normalized
    ? HELP_SECTIONS.filter((section) => (haystacks.get(section.key) ?? '').includes(normalized))
    : HELP_SECTIONS;

  return (
    <BrokerPageShell
      title={t('help.title')}
      description={t('help.subtitle')}
      icon={BookOpen}
      color="neutral"
      toolbar={
        <div className="flex flex-col gap-2 p-1 sm:flex-row sm:items-center sm:justify-between">
          <Input
            className="w-full sm:max-w-sm"
            placeholder={t('help.search_placeholder')}
            aria-label={t('help.search_aria')}
            startContent={<Search size={16} className="text-muted" aria-hidden="true" />}
            value={query}
            onValueChange={setQuery}
            size="sm"
            variant="secondary"
            isClearable
            onClear={() => setQuery('')}
          />
          <p className="px-1 text-xs tabular-nums text-muted" aria-live="polite">
            {t('help.search_count', {
              shown: visibleSections.length,
              total: HELP_SECTIONS.length,
            })}
          </p>
        </div>
      }
    >
      {visibleSections.length === 0 ? (
        <BrokerEmptyState
          icon={SearchX}
          color="neutral"
          title={t('help.search_empty_title')}
          hint={t('help.search_empty_hint')}
          action={
            <Button size="sm" variant="tertiary" onPress={() => setQuery('')}>
              {t('help.search_clear')}
            </Button>
          }
        />
      ) : (
        <Card className="rounded-2xl border border-divider/70 bg-surface shadow-sm shadow-black/[0.03]">
          <CardBody className="p-3 sm:p-4">
            <HelpAccordion sections={visibleSections} />
          </CardBody>
        </Card>
      )}
    </BrokerPageShell>
  );
}
