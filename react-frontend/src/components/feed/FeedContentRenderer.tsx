// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * FeedContentRenderer — Safe HTML renderer for feed post content.
 * Handles both plain text (legacy posts) and rich HTML (new posts from ComposeEditor).
 * Sanitizes HTML with DOMPurify before rendering.
 */

import DOMPurify from 'dompurify';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';

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

/* ───────────────────────── Component ───────────────────────── */

export function FeedContentRenderer({
  content,
  truncated = false,
  detailPath,
}: FeedContentRendererProps) {
  const { t } = useTranslation('feed');

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

  // Plain text content (legacy) — render with whitespace preservation
  return (
    <p className="text-sm text-[var(--text-secondary)] whitespace-pre-wrap leading-relaxed">
      {content}
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
