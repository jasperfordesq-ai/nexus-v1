// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PlainTextEditor — a monospace textarea for text-only emails, with
 * insert-at-cursor personalization tokens. Signals the backend (via
 * content_format='plaintext') to send a text/plain-first message.
 */

import { useRef } from 'react';
import { Textarea, Chip } from '@/components/ui';
import { useTranslation } from 'react-i18next';

interface PlainTextEditorProps {
  value: string;
  onChange: (text: string) => void;
  isDisabled?: boolean;
}

const TOKENS = [
  { token: '{{first_name}}', labelKey: 'newsletter_form.tag_first_name' },
  { token: '{{name}}', labelKey: 'newsletter_form.tag_full_name' },
  { token: '{{tenant_name}}', labelKey: 'newsletter_form.tag_tenant_name' },
  { token: '{{unsubscribe_url}}', labelKey: 'newsletter_content_editor.tag_unsubscribe_url' },
];

export function PlainTextEditor({ value, onChange, isDisabled }: PlainTextEditorProps) {
  const { t } = useTranslation('admin');
  const ref = useRef<HTMLTextAreaElement>(null);

  const insertToken = (token: string) => {
    const el = ref.current;
    if (!el) {
      onChange(value + token);
      return;
    }
    const start = el.selectionStart ?? value.length;
    const end = el.selectionEnd ?? value.length;
    const next = value.slice(0, start) + token + value.slice(end);
    onChange(next);
    // Restore caret just after the inserted token.
    requestAnimationFrame(() => {
      el.focus();
      const pos = start + token.length;
      el.setSelectionRange(pos, pos);
    });
  };

  return (
    <div className="flex flex-col gap-1.5">
      <label className="text-sm font-medium text-foreground">
        {t('newsletter_content_editor.label_plaintext')}
      </label>
      <Textarea
        ref={ref}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        isDisabled={isDisabled}
        minRows={16}
        placeholder={t('newsletter_content_editor.plaintext_placeholder')}
        className="font-mono"
        aria-label={t('newsletter_content_editor.label_plaintext')}
      />
      <div className="flex flex-wrap items-center gap-2 pt-1">
        <span className="text-xs text-muted">{t('newsletter_content_editor.insert_token')}</span>
        {TOKENS.map((tag) => (
          <Chip
            key={tag.token}
            size="sm"
            variant="soft"
            className="cursor-pointer font-mono"
            onClick={() => !isDisabled && insertToken(tag.token)}
          >
            {tag.token}
          </Chip>
        ))}
      </div>
    </div>
  );
}

export default PlainTextEditor;
