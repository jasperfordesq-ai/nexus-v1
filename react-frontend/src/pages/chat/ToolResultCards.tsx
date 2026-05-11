// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tool result cards
 *
 * Renders the structured `tool_invocations` returned by the AI chat endpoint
 * as a row of compact, clickable result cards beside the assistant's
 * conversational reply. The model summarises in prose; the cards give the
 * user a direct way to navigate to the underlying entity.
 */

import { useTranslation } from 'react-i18next';
import { Card, CardBody, Chip } from '@heroui/react';
import MapPin from 'lucide-react/icons/map-pin';
import Tag from 'lucide-react/icons/tag';
import Calendar from 'lucide-react/icons/calendar';
import Briefcase from 'lucide-react/icons/briefcase';
import ShoppingBag from 'lucide-react/icons/shopping-bag';
import BookOpen from 'lucide-react/icons/book-open';
import User from 'lucide-react/icons/user';
import Wallet from 'lucide-react/icons/wallet';

export interface ToolInvocation {
  name: string;
  arguments: Record<string, unknown>;
  ok: boolean;
  summary: string;
  card_type: string;
  results: Array<Record<string, unknown>>;
}

interface ToolResultCardsProps {
  invocations: ToolInvocation[];
}

export function ToolResultCards({ invocations }: ToolResultCardsProps) {
  if (!invocations || invocations.length === 0) return null;

  const renderableInvocations = invocations.filter(
    (inv) => inv.ok && Array.isArray(inv.results) && inv.results.length > 0
  );

  if (renderableInvocations.length === 0) return null;

  return (
    <div className="mt-2 flex flex-col gap-2">
      {renderableInvocations.map((inv, i) => (
        <div key={`${inv.name}-${i}`} className="flex flex-col gap-1.5">
          <div className="flex flex-wrap gap-2">
            {inv.results.slice(0, 6).map((r, j) => (
              <ResultCard
                key={`${inv.card_type}-${j}-${String((r as { id?: number }).id ?? j)}`}
                cardType={inv.card_type}
                item={r}
              />
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}

interface ResultCardProps {
  cardType: string;
  item: Record<string, unknown>;
}

function ResultCard({ cardType, item }: ResultCardProps) {
  const url = typeof item.url === 'string' ? item.url : undefined;
  const card = (
    <Card
      className="bg-[var(--color-surface-elevated)] border border-[var(--border-default)] hover:border-indigo-400 transition-all cursor-pointer max-w-[20rem]"
      isPressable={Boolean(url)}
      radius="md"
      shadow="none"
    >
      <CardBody className="p-3 gap-1">{renderCardContent(cardType, item)}</CardBody>
    </Card>
  );

  return url ? (
    <a href={url} className="inline-block no-underline" aria-label={String(item.title ?? item.name ?? 'View')}>
      {card}
    </a>
  ) : (
    card
  );
}

function renderCardContent(cardType: string, item: Record<string, unknown>) {
  switch (cardType) {
    case 'listing':
      return <ListingCard item={item} />;
    case 'member':
      return <MemberCard item={item} />;
    case 'event':
      return <EventCard item={item} />;
    case 'job':
      return <JobCard item={item} />;
    case 'marketplace':
      return <MarketplaceCard item={item} />;
    case 'kb':
      return <KbCard item={item} />;
    case 'wallet':
      return <WalletCard item={item} />;
    default:
      return <GenericCard item={item} />;
  }
}

function ListingCard({ item }: { item: Record<string, unknown> }) {
  const type = String(item.type ?? '');
  return (
    <>
      <div className="flex items-start gap-2">
        <Tag className="w-3.5 h-3.5 mt-0.5 text-indigo-500 shrink-0" />
        <p className="text-sm font-semibold text-[var(--color-text)] line-clamp-2 flex-1">{String(item.title ?? '')}</p>
      </div>
      <div className="flex flex-wrap items-center gap-1.5 text-xs text-[var(--color-text-muted)]">
        {type && <Chip size="sm" variant="flat" color={type === 'offer' ? 'success' : 'warning'}>{type}</Chip>}
        {item.location ? (
          <span className="flex items-center gap-0.5"><MapPin className="w-3 h-3" />{String(item.location)}</span>
        ) : null}
        {item.hours_estimate != null ? <span>{String(item.hours_estimate)}h</span> : null}
      </div>
      {item.excerpt ? <p className="text-xs text-[var(--color-text-muted)] line-clamp-2">{String(item.excerpt)}</p> : null}
    </>
  );
}

function MemberCard({ item }: { item: Record<string, unknown> }) {
  return (
    <>
      <div className="flex items-start gap-2">
        <User className="w-3.5 h-3.5 mt-0.5 text-indigo-500 shrink-0" />
        <p className="text-sm font-semibold text-[var(--color-text)] line-clamp-1 flex-1">{String(item.name ?? '')}</p>
      </div>
      {item.tagline ? <p className="text-xs text-[var(--color-text-muted)] line-clamp-2">{String(item.tagline)}</p> : null}
      <div className="flex flex-wrap items-center gap-1.5 text-xs text-[var(--color-text-muted)]">
        {item.location ? (
          <span className="flex items-center gap-0.5"><MapPin className="w-3 h-3" />{String(item.location)}</span>
        ) : null}
        {item.skills ? <span className="line-clamp-1 max-w-[10rem]">{String(item.skills)}</span> : null}
      </div>
    </>
  );
}

function EventCard({ item }: { item: Record<string, unknown> }) {
  const { t } = useTranslation('chat');
  const start = item.start_time ? new Date(String(item.start_time)).toLocaleString([], {
    month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
  }) : null;
  return (
    <>
      <div className="flex items-start gap-2">
        <Calendar className="w-3.5 h-3.5 mt-0.5 text-indigo-500 shrink-0" />
        <p className="text-sm font-semibold text-[var(--color-text)] line-clamp-2 flex-1">{String(item.title ?? '')}</p>
      </div>
      <div className="flex flex-wrap items-center gap-1.5 text-xs text-[var(--color-text-muted)]">
        {start ? <span>{start}</span> : null}
        {item.is_online ? <Chip size="sm" variant="flat" color="primary">{t('card_online')}</Chip> : null}
        {item.location ? <span className="flex items-center gap-0.5"><MapPin className="w-3 h-3" />{String(item.location)}</span> : null}
      </div>
    </>
  );
}

function JobCard({ item }: { item: Record<string, unknown> }) {
  const { t } = useTranslation('chat');
  return (
    <>
      <div className="flex items-start gap-2">
        <Briefcase className="w-3.5 h-3.5 mt-0.5 text-indigo-500 shrink-0" />
        <p className="text-sm font-semibold text-[var(--color-text)] line-clamp-2 flex-1">{String(item.title ?? '')}</p>
      </div>
      {item.tagline ? <p className="text-xs text-[var(--color-text-muted)] line-clamp-1">{String(item.tagline)}</p> : null}
      <div className="flex flex-wrap items-center gap-1.5 text-xs text-[var(--color-text-muted)]">
        {item.is_remote ? <Chip size="sm" variant="flat" color="primary">{t('card_remote')}</Chip> : null}
        {item.location ? <span className="flex items-center gap-0.5"><MapPin className="w-3 h-3" />{String(item.location)}</span> : null}
        {item.salary ? <span>{String(item.salary)}</span> : null}
      </div>
    </>
  );
}

function MarketplaceCard({ item }: { item: Record<string, unknown> }) {
  return (
    <>
      <div className="flex items-start gap-2">
        <ShoppingBag className="w-3.5 h-3.5 mt-0.5 text-indigo-500 shrink-0" />
        <p className="text-sm font-semibold text-[var(--color-text)] line-clamp-2 flex-1">{String(item.title ?? '')}</p>
      </div>
      <div className="flex flex-wrap items-center gap-1.5 text-xs text-[var(--color-text-muted)]">
        {item.price ? <span>{String(item.price)}</span> : null}
        {item.time_credit_price != null ? <span>{String(item.time_credit_price)}h credits</span> : null}
        {item.condition ? <Chip size="sm" variant="flat">{String(item.condition)}</Chip> : null}
      </div>
    </>
  );
}

function KbCard({ item }: { item: Record<string, unknown> }) {
  return (
    <>
      <div className="flex items-start gap-2">
        <BookOpen className="w-3.5 h-3.5 mt-0.5 text-indigo-500 shrink-0" />
        <p className="text-sm font-semibold text-[var(--color-text)] line-clamp-2 flex-1">{String(item.title ?? '')}</p>
      </div>
      {item.excerpt ? <p className="text-xs text-[var(--color-text-muted)] line-clamp-3">{String(item.excerpt)}</p> : null}
    </>
  );
}

function WalletCard({ item }: { item: Record<string, unknown> }) {
  const { t } = useTranslation('chat');
  return (
    <>
      <div className="flex items-start gap-2">
        <Wallet className="w-3.5 h-3.5 mt-0.5 text-indigo-500 shrink-0" />
        <p className="text-sm font-semibold text-[var(--color-text)]">{t('card_wallet_title')}</p>
      </div>
      <p className="text-2xl font-bold text-[var(--color-text)]">{Number(item.balance ?? 0).toFixed(2)}h</p>
      <p className="text-xs text-[var(--color-text-muted)]">{t('card_wallet_transactions', { count: Number(item.recent_transactions_30d ?? 0) })}</p>
    </>
  );
}

function GenericCard({ item }: { item: Record<string, unknown> }) {
  const title = item.title ?? item.name ?? 'Result';
  return <p className="text-sm text-[var(--color-text)] line-clamp-2">{String(title)}</p>;
}
