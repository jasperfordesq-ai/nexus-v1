// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { forwardRef, lazy, Suspense, useImperativeHandle, useRef } from 'react';
import { Tabs, Tab, Spinner, Textarea } from '@/components/ui';
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
import type { PageDesignBuilderHandle } from './PageDesignBuilder';

const RichTextEditor = lazy(() =>
  import('./RichTextEditor').then((m) => ({ default: m.RichTextEditor })),
);
const HtmlSourceEditor = lazy(() =>
  import('./HtmlSourceEditor').then((m) => ({ default: m.HtmlSourceEditor })),
);
const PageDesignBuilder = lazy(() =>
  import('./PageDesignBuilder').then((m) => ({ default: m.PageDesignBuilder })),
);

interface PageContentEditorProps {
  value: string;
  format: ContentFormat;
  designJson?: string | null;
  isDisabled?: boolean;
  onChange: (next: { content: string; content_format: ContentFormat; design_json?: string | null }) => void;
}

export interface PageContentEditorHandle {
  flush: () => { content: string; content_format: ContentFormat; design_json?: string | null } | null;
}

const MODE_ICON: Record<ContentFormat, React.ReactNode> = {
  plaintext: <Type size={15} />,
  richtext: <FileText size={15} />,
  html: <Code size={15} />,
  builder: <Paintbrush size={15} />,
};

export const PageContentEditor = forwardRef<PageContentEditorHandle, PageContentEditorProps>(function PageContentEditor(
  { value, format, designJson, isDisabled, onChange },
  ref,
) {
  const { t } = useTranslation('admin');
  const confirm = useConfirm();
  const designBuilderRef = useRef<PageDesignBuilderHandle | null>(null);

  const emit = (content: string, nextFormat: ContentFormat, nextDesignJson?: string | null) => {
    onChange(
      nextDesignJson === undefined
        ? { content, content_format: nextFormat }
        : { content, content_format: nextFormat, design_json: nextDesignJson },
    );
  };

  const requestModeChange = async (next: ContentFormat) => {
    if (isDisabled) return;
    if (next === format) return;

    const flushedBuilder = format === 'builder' ? designBuilderRef.current?.flush() : null;
    const currentContent = flushedBuilder?.html ?? value;
    const currentDesignJson = flushedBuilder?.designJson ?? designJson ?? null;

    if (isDestructiveSwitch(format, next, currentContent)) {
      const ok = await confirm({
        title: t('page_builder.switch_confirm_title'),
        body:
          next === 'plaintext'
            ? t('page_builder.switch_to_plain_warning')
            : next === 'richtext'
              ? t('page_builder.switch_to_richtext_warning')
              : t('page_builder.switch_to_design_warning'),
        status: 'warning',
        confirmLabel: t('page_builder.switch_confirm'),
      });
      if (!ok) return;
    }
    emit(transformContent(currentContent, format, next), next, next === 'builder' ? currentDesignJson : null);
  };

  const modeLabel = (mode: ContentFormat) =>
    ({
      plaintext: t('page_builder.mode_plaintext'),
      richtext: t('page_builder.mode_richtext'),
      html: t('page_builder.mode_html'),
      builder: t('page_builder.mode_design'),
    })[mode];

  const modeHint = (mode: ContentFormat) =>
    ({
      plaintext: t('page_builder.hint_plaintext'),
      richtext: t('page_builder.hint_richtext'),
      html: t('page_builder.hint_html'),
      builder: t('page_builder.hint_design'),
    })[mode];

  const fallback = (
    <div role="status" aria-busy="true" aria-label={t('page_builder.loading')} className="flex items-center justify-center py-16">
      <Spinner size="sm" />
    </div>
  );

  useImperativeHandle(ref, () => ({
    flush: () => {
      if (format !== 'builder') {
        return { content: value, content_format: format, design_json: null };
      }
      const flushed = designBuilderRef.current?.flush();
      if (!flushed) return { content: value, content_format: format, design_json: designJson ?? null };
      return { content: flushed.html, content_format: 'builder', design_json: flushed.designJson };
    },
  }), [designJson, format, value]);

  return (
    <div className="flex flex-col gap-4">
      <div className="rounded-xl border border-border bg-surface p-3">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <span className="text-sm font-semibold text-foreground">
            {t('page_builder.section_title')}
          </span>
          <Tabs
            selectedKey={format}
            onSelectionChange={(key) => requestModeChange(key as ContentFormat)}
            aria-label={t('page_builder.mode_switcher')}
            size="sm"
          >
            {EDITOR_MODES.map((mode) => (
              <Tab
                key={mode}
                id={mode}
                isDisabled={isDisabled}
                title={
                  <span className="flex items-center gap-1.5">
                    {MODE_ICON[mode]}
                    {modeLabel(mode)}
                  </span>
                }
              />
            ))}
          </Tabs>
        </div>
        <p className="mt-1 text-xs text-muted">{modeHint(format)}</p>
      </div>

      {format === 'plaintext' && (
        <Textarea
          label={t('page_builder.label_plaintext')}
          placeholder={t('page_builder.plaintext_placeholder')}
          minRows={12}
          value={value}
          onValueChange={(text) => emit(text, 'plaintext')}
          isDisabled={isDisabled}
          variant="secondary"
        />
      )}
      {format === 'richtext' && (
        <Suspense fallback={fallback}>
          <RichTextEditor
            label={t('content.label_content')}
            placeholder={t('content.placeholder_content')}
            value={value}
            onChange={(html) => emit(html, 'richtext')}
            isDisabled={isDisabled}
          />
        </Suspense>
      )}
      {format === 'html' && (
        <Suspense fallback={fallback}>
          <HtmlSourceEditor
            value={value}
            onChange={(html) => emit(html, 'html')}
            isDisabled={isDisabled}
            labels={{
              label: t('page_builder.label_html'),
              hint: t('page_builder.html_hint'),
              insertImage: t('page_builder.insert_image'),
              uploadFailed: t('page_builder.image_upload_failed'),
            }}
          />
        </Suspense>
      )}
      {format === 'builder' && (
        <Suspense fallback={fallback}>
          <PageDesignBuilder
            ref={designBuilderRef}
            html={value}
            designJson={designJson}
            readOnly={isDisabled}
            onChange={({ html, designJson: nextDesignJson }) => emit(html, 'builder', nextDesignJson)}
          />
        </Suspense>
      )}
    </div>
  );
});

export default PageContentEditor;
