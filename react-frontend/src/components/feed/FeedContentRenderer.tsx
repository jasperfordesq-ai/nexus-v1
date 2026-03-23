// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * FeedContentRenderer — Safe HTML renderer for feed post content.
 * Handles both plain text (legacy posts) and rich HTML (new posts from ComposeEditor).
 * Sanitizes HTML with DOMPurify before rendering.
 */

import { Fragment } from 'react';
import DOMPurify from 'dompurify';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';
import { MentionRenderer } from '@/components/social/MentionRenderer';

/* ───────────────────────── Types ───────────────────────── */

interface FeedContentRendererProps {
  /** The post content string (plain text or HTML) */
  content: string;
  /** Whether the content has been truncated by the API */
  truncated?: boolean;
  /** Path to the detail page (used for "read more" link) */
  detailPath?: string;
}

/* ───────────────────────── Constants ───────────────────────── */

const ALLOWED_TAGS = ['p', 'br', 'strong', 'em', 'u', 'ul', 'ol', 'li', 'a'];
const ALLOWED_ATTR = ['href', 'target', 'rel'];

/** Regex to detect if a string contains HTML tags */
const HTML_TAG_REGEX = /<[a-z][\s\S]*>/i;

/* ───────────────────────── DOMPurify Configuration ───────────────────────── */

/**
 * Configure DOMPurify to force safe link attributes on all anchor tags.
 * This hook runs after DOMPurify sanitizes but before final output.
 */
function sanitizeHtml(html: string): string {
  // Create a new DOMPurify instance hook for this call
  const clean = DOMPurify.sanitize(html, {
    ALLOWED_TAGS,
    ALLOWED_ATTR,
  });

  // Post-process: ensure all <a> tags have target and rel attributes
  // DOMPurify strips unknown attributes, so we add them after sanitization
  const div = document.createElement('div');
  div.innerHTML = clean;
  const links = div.querySelectorAll('a');
  links.forEach((link) => {
    link.setAttribute('target', '_blank');
    link.setAttribute('rel', 'noopener noreferrer');
  });

  return div.innerHTML;
}

/* ───────────────────────── Hashtag & Mention Helper ───────────────────────── */

/**
 * Render plain text with clickable #hashtags and @mentions.
 */
function TextWithHashtagsAndMentions({ text, tenantPath }: { text: string; tenantPath: (p: string) => string }) {
  // Combined regex: match #hashtags and @mentions in a single pass
  const combinedRegex = /(#(\w{2,})|@([a-zA-Z0-9_.-]+))/g;
  const parts: Array<{ type: 'text' | 'hashtag' | 'mention'; value: string }> = [];
  let lastIndex = 0;
  let match: RegExpExecArray | null;

  while ((match = combinedRegex.exec(text)) !== null) {
    if (match.index > lastIndex) {
      parts.push({ type: 'text', value: text.slice(lastIndex, match.index) });
    }
    if (match[2]) {
      // Hashtag match
      parts.push({ type: 'hashtag', value: match[2] });
    } else if (match[3]) {
      // Mention match
      parts.push({ type: 'mention', value: match[3] });
    }
    lastIndex = match.index + match[0].length;
  }
  if (lastIndex < text.length) {
    parts.push({ type: 'text', value: text.slice(lastIndex) });
  }

  if (parts.length === 0) return <>{text}</>;

  return (
    <>
      {parts.map((part, idx) => (
        <Fragment key={idx}>
          {part.type === 'hashtag' ? (
            <Link
              to={tenantPath(`/feed/hashtag/${part.value}`)}
              className="text-indigo-500 hover:text-indigo-600 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium transition-colors"
              onClick={(e) => e.stopPropagation()}
            >
              #{part.value}
            </Link>
          ) : part.type === 'mention' ? (
            <MentionRenderer text={`@${part.value}`} showUserCard={false} />
          ) : (
            part.value
          )}
        </Fragment>
      ))}
    </>
  );
}

/* ───────────────────────── Component ───────────────────────── */

export function FeedContentRenderer({
  content,
  truncated = false,
  detailPath,
}: FeedContentRendererProps) {
  const { t } = useTranslation('feed');
  const { tenantPath } = useTenant();

  if (!content) {
    return null;
  }

  const isHtml = HTML_TAG_REGEX.test(content);

  if (isHtml) {
    // Rich HTML content — sanitize and render
    const sanitized = sanitizeHtml(content);

    return (
      <div>
        <div
          className="feed-content text-sm text-[var(--text-secondary)] leading-relaxed"
          dangerouslySetInnerHTML={{ __html: sanitized }}
        />
        {truncated && detailPath && (
          <Link
            to={detailPath}
            className="text-[var(--color-primary)] hover:underline text-sm font-medium"
          >
            ...{t('card.read_more', 'read more')}
          </Link>
        )}
      </div>
    );
  }

  // Plain text content (legacy) — render with whitespace preservation and clickable hashtags
  return (
    <p className="text-sm text-[var(--text-secondary)] whitespace-pre-wrap leading-relaxed">
      <TextWithHashtagsAndMentions text={content} tenantPath={tenantPath} />
      {truncated && detailPath && (
        <Link
          to={detailPath}
          className="text-[var(--color-primary)] hover:underline ml-1 text-sm font-medium"
        >
          {t('card.read_more', 'read more')}
        </Link>
      )}
    </p>
  );
}

export default FeedContentRenderer;
