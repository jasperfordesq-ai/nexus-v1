// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useMemo, useState, type ReactNode } from 'react';
import Check from 'lucide-react/icons/check';
import Copy from 'lucide-react/icons/copy';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/helpers';

type SnippetSize = 'sm' | 'md' | 'lg';

const sizeClasses: Record<SnippetSize, string> = {
  sm: 'text-xs',
  md: 'text-sm',
  lg: 'text-base',
};

export interface SnippetProps {
  children: ReactNode;
  className?: string;
  codeString?: string;
  color?: string;
  hideCopyButton?: boolean;
  size?: SnippetSize;
  symbol?: ReactNode;
  variant?: string;
}

function textFromChildren(children: ReactNode): string {
  if (typeof children === 'string' || typeof children === 'number') {
    return String(children);
  }

  if (Array.isArray(children)) {
    return children.map(textFromChildren).join('');
  }

  return '';
}

export function Snippet({
  children,
  className,
  codeString,
  color: _color,
  hideCopyButton = false,
  size = 'md',
  symbol = '$',
  variant: _variant,
}: SnippetProps) {
  const { t } = useTranslation('common');
  const [copied, setCopied] = useState(false);
  const copyText = useMemo(() => codeString ?? textFromChildren(children), [children, codeString]);

  const handleCopy = async () => {
    if (!copyText) return;

    await navigator.clipboard.writeText(copyText);
    setCopied(true);
    window.setTimeout(() => setCopied(false), 1500);
  };

  return (
    <div
      className={cn(
        'flex items-start gap-2 rounded-md bg-surface-secondary px-3 py-2 font-mono text-foreground',
        sizeClasses[size],
        className,
      )}
    >
      {symbol !== '' && (
        <span className="shrink-0 select-none text-muted" aria-hidden="true">
          {symbol}
        </span>
      )}
      <pre className="min-w-0 flex-1 overflow-auto whitespace-pre-wrap">
        <code>{children}</code>
      </pre>
      {!hideCopyButton && copyText && (
        <button
          type="button"
          className="shrink-0 rounded p-1 text-muted transition-colors hover:bg-surface-tertiary hover:text-foreground focus:outline-none focus:ring-2 focus:ring-accent"
          onClick={handleCopy}
          aria-label={t('copy_code')}
        >
          {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
        </button>
      )}
    </div>
  );
}
