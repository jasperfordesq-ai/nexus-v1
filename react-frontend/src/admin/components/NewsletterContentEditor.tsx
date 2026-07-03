// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * NewsletterContentEditor — the multi-mode authoring surface shared by the
 * newsletter compose form and the template form.
 *
 * Modes: Plain text · Rich text (existing Lexical) · HTML source (new) · Design
 * (Phase 2 placeholder). Emits ONE atomic { content, content_format } so the
 * two can never desync (which format the send pipeline uses depends on it).
 * Destructive mode switches (e.g. HTML → Rich text, which mangles through
 * Lexical) are gated by a confirm dialog.
 */

import { lazy, Suspense, useState } from 'react';
import { Tabs, Tab, Spinner } from '@/components/ui';
import { useConfirm } from '@/components/ui';
import { useTranslation } from 'react-i18next';
import Type from 'lucide-react/icons/type';
import FileText from 'lucide-react/icons/file-text';
import Code from 'lucide-react/icons/code';
import Paintbrush from 'lucide-react/icons/paintbrush';
import {
  type ContentFormat,
  EDITOR_MODES,
  isDestructiveSwitch,
  transformContent,
} from './contentFormat';
import { PlainTextEditor } from './PlainTextEditor';
import { NewsletterPreviewPane } from './NewsletterPreviewPane';
import { DesignModePlaceholder } from './DesignModePlaceholder';

const RichTextEditor = lazy(() =>
  import('./RichTextEditor').then((m) => ({ default: m.RichTextEditor })),
);
const HtmlSourceEditor = lazy(() =>
  import('./HtmlSourceEditor').then((m) => ({ default: m.HtmlSourceEditor })),
);

interface PreviewRequest {
  content: string;
  content_format: ContentFormat;
  subject?: string;
  preview_text?: string;
}

interface NewsletterContentEditorProps {
  value: string;
  format: ContentFormat;
  onChange: (next: { content: string; content_format: ContentFormat }) => void;
  label?: string;
  placeholder?: string;
  isDisabled?: boolean;
  /** Modes to expose. Defaults to plaintext|richtext|html (Phase 1). */
  modes?: ContentFormat[];
  /** Enables the live preview pane when provided. */
  onRequestPreview?: (req: PreviewRequest) => Promise<string>;
  /** Subject/preview text for a faithful preview. */
  subject?: string;
  previewText?: string;
}

const MODE_ICON: Record<ContentFormat, React.ReactNode> = {
  plaintext: <Type size={15} />,
  richtext: <FileText size={15} />,
  html: <Code size={15} />,
  builder: <Paintbrush size={15} />,
};

export function NewsletterContentEditor({
  value,
  format,
  onChange,
  placeholder,
  isDisabled,
  modes = EDITOR_MODES,
  onRequestPreview,
  subject,
  previewText,
}: NewsletterContentEditorProps) {
  const { t } = useTranslation('admin');
  const confirm = useConfirm();
  const [showPreview, setShowPreview] = useState(true);

  const emit = (content: string, nextFormat: ContentFormat) => {
    onChange({ content, content_format: nextFormat });
  };

  const requestModeChange = async (next: ContentFormat) => {
    if (next === format) return;

    if (isDestructiveSwitch(format, next, value)) {
      const ok = await confirm({
        title: t('newsletter_content_editor.switch_confirm_title'),
        body:
          next === 'plaintext'
            ? t('newsletter_content_editor.switch_to_plain_warning')
            : t('newsletter_content_editor.switch_to_richtext_warning'),
        status: 'warning',
        confirmLabel: t('newsletter_content_editor.switch_confirm'),
      });
      if (!ok) return; // stay in current mode
    }

    emit(transformContent(value, format, next), next);
  };

  const modeLabel = (m: ContentFormat) =>
    ({
      plaintext: t('newsletter_content_editor.mode_plaintext'),
      richtext: t('newsletter_content_editor.mode_richtext'),
      html: t('newsletter_content_editor.mode_html'),
      builder: t('newsletter_content_editor.mode_design'),
    })[m];

  const editorFallback = (
    <div role="status" aria-busy="true" aria-label={t('newsletter_content_editor.loading')}>
      <Spinner size="sm" className="m-4" />
    </div>
  );

  return (
    <div className="flex flex-col gap-3">
      <Tabs
        selectedKey={format}
        onSelectionChange={(key) => requestModeChange(key as ContentFormat)}
        aria-label={t('newsletter_content_editor.mode_switcher')}
        size="sm"
      >
        {modes.map((m) => (
          <Tab key={m} id={m} title={<span className="flex items-center gap-1.5">{MODE_ICON[m]}{modeLabel(m)}</span>} />
        ))}
      </Tabs>

      <div>
        {format === 'plaintext' && (
          <PlainTextEditor value={value} onChange={(text) => emit(text, 'plaintext')} isDisabled={isDisabled} />
        )}
        {format === 'richtext' && (
          <Suspense fallback={editorFallback}>
            <RichTextEditor
              label={t('newsletter_content_editor.label_richtext')}
              placeholder={placeholder}
              value={value}
              onChange={(htmlValue: string) => emit(htmlValue, 'richtext')}
              isDisabled={isDisabled}
            />
          </Suspense>
        )}
        {format === 'html' && (
          <Suspense fallback={editorFallback}>
            <HtmlSourceEditor value={value} onChange={(htmlValue) => emit(htmlValue, 'html')} isDisabled={isDisabled} />
          </Suspense>
        )}
        {format === 'builder' && <DesignModePlaceholder />}
      </div>

      {onRequestPreview && (
        <div className="rounded-lg border border-border p-3">
          <button
            type="button"
            className="mb-2 text-xs font-medium text-accent underline"
            onClick={() => setShowPreview((s) => !s)}
          >
            {showPreview
              ? t('newsletter_content_editor.hide_preview')
              : t('newsletter_content_editor.show_preview')}
          </button>
          {showPreview && (
            <NewsletterPreviewPane
              content={value}
              format={format}
              subject={subject}
              previewText={previewText}
              onRequestPreview={onRequestPreview}
            />
          )}
        </div>
      )}
    </div>
  );
}

export default NewsletterContentEditor;
