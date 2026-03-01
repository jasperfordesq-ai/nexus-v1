// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * HashtagRenderer - Renders clickable #hashtags in text content
 *
 * Parses text for #hashtags and makes them clickable links
 * to the hashtag discovery page.
 */

import { Fragment } from 'react';
import { Link } from 'react-router-dom';
import { useTenant } from '@/contexts';

/**
 * Parse text content and replace #hashtags with clickable links.
 */
export function HashtagRenderer({
  content,
  className = '',
}: {
  content: string;
  className?: string;
}) {
  const { tenantPath } = useTenant();

  // Match hashtags: # followed by word characters (letters, digits, underscore)
  const hashtagRegex = /#(\w{2,})/g;

  const parts: Array<{ type: 'text' | 'hashtag'; value: string }> = [];
  let lastIndex = 0;
  let match: RegExpExecArray | null;

  while ((match = hashtagRegex.exec(content)) !== null) {
    // Add preceding text
    if (match.index > lastIndex) {
      parts.push({ type: 'text', value: content.slice(lastIndex, match.index) });
    }
    // Add hashtag
    parts.push({ type: 'hashtag', value: match[1] });
    lastIndex = match.index + match[0].length;
  }

  // Add remaining text
  if (lastIndex < content.length) {
    parts.push({ type: 'text', value: content.slice(lastIndex) });
  }

  // If no hashtags found, return original content
  if (parts.length === 0 || (parts.length === 1 && parts[0].type === 'text')) {
    return <span className={className}>{content}</span>;
  }

  return (
    <span className={className}>
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
          ) : (
            part.value
          )}
        </Fragment>
      ))}
    </span>
  );
}

export default HashtagRenderer;
