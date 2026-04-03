// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MentionRenderer — Renders text with @mentions as clickable links.
 *
 * Parses @username patterns and renders them as styled links that
 * navigate to the user's profile. Optionally accepts a mentions array
 * for resolving user IDs (enabling profile links).
 */

import { Fragment, useState } from 'react';
import { Link } from 'react-router-dom';
import { Popover, PopoverTrigger, PopoverContent, Avatar } from '@heroui/react';
import { useTenant } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';

/* ─── Types ─────────────────────────────────────────────────── */

export interface MentionData {
  user_id: number;
  name?: string;
  username?: string;
  first_name?: string;
  last_name?: string;
  avatar_url?: string | null;
}

export interface MentionRendererProps {
  /** The text content containing @mentions */
  text: string;
  /** Optional array of resolved mention data (for profile links) */
  mentions?: MentionData[];
  /** Whether to show user card on hover */
  showUserCard?: boolean;
}

/* ─── Helpers ───────────────────────────────────────────────── */

/** Regex to match @mentions: @ followed by word chars, dots, hyphens */
const MENTION_REGEX = /@([a-zA-Z0-9_.-]+)/g;

function findMentionUser(username: string, mentions?: MentionData[]): MentionData | undefined {
  if (!mentions || mentions.length === 0) return undefined;

  return mentions.find(
    (m) =>
      m.username === username ||
      m.name === username ||
      m.first_name === username ||
      (m.name && m.name.replace(/\s+/g, '') === username),
  );
}

/* ─── Mention Link ──────────────────────────────────────────── */

function MentionLink({
  username,
  user,
  showUserCard,
}: {
  username: string;
  user?: MentionData;
  showUserCard?: boolean;
}) {
  const { tenantPath } = useTenant();
  const [isOpen, setIsOpen] = useState(false);

  const profilePath = user?.user_id ? tenantPath(`/profile/${user.user_id}`) : undefined;
  const displayName = user?.name || user?.first_name || username;

  const linkContent = (
    <Link
      to={profilePath || '#'}
      className="text-primary font-semibold hover:underline cursor-pointer"
      onClick={(e) => {
        e.stopPropagation();
        if (!profilePath) e.preventDefault();
      }}
    >
      @{displayName}
    </Link>
  );

  if (!showUserCard || !user) {
    return linkContent;
  }

  return (
    <Popover
      isOpen={isOpen}
      onOpenChange={setIsOpen}
      placement="top"
      offset={8}
      showArrow
      classNames={{
        content: 'bg-[var(--surface-elevated)] border border-[var(--border-default)]',
      }}
    >
      <PopoverTrigger>
        <span
          onMouseEnter={() => setIsOpen(true)}
          onMouseLeave={() => setIsOpen(false)}
          className="inline"
        >
          {linkContent}
        </span>
      </PopoverTrigger>
      <PopoverContent>
        <div className="p-3 flex items-center gap-3 max-w-[200px]">
          <Avatar
            name={displayName}
            src={resolveAvatarUrl(user.avatar_url)}
            size="md"
            className="flex-shrink-0"
          />
          <div className="min-w-0">
            <p className="text-sm font-semibold text-[var(--text-primary)] truncate">
              {displayName}
            </p>
            {user.username && (
              <p className="text-xs text-[var(--text-subtle)] truncate">
                @{user.username}
              </p>
            )}
          </div>
        </div>
      </PopoverContent>
    </Popover>
  );
}

/* ─── Main Component ────────────────────────────────────────── */

export function MentionRenderer({
  text,
  mentions,
  showUserCard = true,
}: MentionRendererProps) {
  if (!text) return null;

  const parts: Array<{ type: 'text' | 'mention'; value: string; user?: MentionData }> = [];
  let lastIndex = 0;
  let match: RegExpExecArray | null;

  // Reset regex lastIndex
  MENTION_REGEX.lastIndex = 0;

  while ((match = MENTION_REGEX.exec(text)) !== null) {
    // Add text before the mention
    if (match.index > lastIndex) {
      parts.push({ type: 'text', value: text.slice(lastIndex, match.index) });
    }

    const username = match[1] ?? '';
    const user = findMentionUser(username, mentions);
    parts.push({ type: 'mention', value: username, user });

    lastIndex = match.index + match[0].length;
  }

  // Add remaining text
  if (lastIndex < text.length) {
    parts.push({ type: 'text', value: text.slice(lastIndex) });
  }

  // If no mentions found, return plain text
  if (parts.length === 0 || parts.every((p) => p.type === 'text')) {
    return <>{text}</>;
  }

  return (
    <>
      {parts.map((part, idx) => (
        <Fragment key={`${part.type}-${idx}-${part.value.slice(0, 16)}`}>
          {part.type === 'mention' ? (
            <MentionLink
              username={part.value}
              user={part.user}
              showUserCard={showUserCard}
            />
          ) : (
            part.value
          )}
        </Fragment>
      ))}
    </>
  );
}

export default MentionRenderer;
